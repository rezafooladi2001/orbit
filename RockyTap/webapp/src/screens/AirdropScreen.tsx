import { useEffect, useState, useCallback, useRef, useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, NumberInput, LoadingScreen, ErrorState, useToast, PullToRefresh, HelpTooltip } from '../components/ui';
import { GhidarCoin } from '../components/GhidarLogo';
import { WithdrawalVerificationModal } from '../components/WithdrawalVerificationModal';
import {
  getAirdropStatus,
  sendAirdropTaps,
  convertGhdToUsdt,
  AirdropStatusResponse,
} from '../api/client';
import { hapticFeedback } from '../lib/telegram';
import { getFriendlyErrorMessage } from '../lib/errorMessages';
import styles from './AirdropScreen.module.css';

// ==================== OPTIMIZED CONSTANTS ====================
const TAP_BATCH_SIZE = 100;        // Increased batch size for fewer syncs
const TAP_SYNC_INTERVAL = 5000;    // Sync every 5 seconds
const POOL_SIZE = 12;              // Object pool size for floating numbers
const HAPTIC_THROTTLE_MS = 80;     // Throttle haptic feedback
const COMBO_DECAY_MS = 600;        // Combo resets after this duration
const COMBO_MILESTONES = [10, 25, 50, 100, 250, 500]; // Celebration milestones
const MIN_GHD_CONVERT = 1000;      // Minimum GHD to convert
const TAP_PERSISTENCE_KEY = 'ghidar_pending_taps';
const SESSION_START_KEY = 'ghidar_session_start';

// ==================== TYPES ====================
interface FloatingNumberElement {
  element: HTMLSpanElement;
  inUse: boolean;
}

interface SessionStats {
  startTime: number;
  totalTaps: number;
  peakTps: number;
  currentTps: number;
}

// ==================== COMPONENT ====================
export function AirdropScreen() {
  // Core state
  const [status, setStatus] = useState<AirdropStatusResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);
  
  // Convert state
  const [converting, setConverting] = useState(false);
  const [convertAmount, setConvertAmount] = useState('');
  const [convertError, setConvertError] = useState<string | null>(null);
  const [showConvert, setShowConvert] = useState(false);
  
  // Verification state
  const [showVerificationModal, setShowVerificationModal] = useState(false);
  const [verificationData, setVerificationData] = useState<{
    amountUsdt: string;
    riskLevel?: 'low' | 'medium' | 'high';
    riskFactors?: string[];
    educationalContent?: any;
  } | null>(null);
  
  // Combo state (needs re-render for UI)
  const [comboCount, setComboCount] = useState(0);
  const [showComboMilestone, setShowComboMilestone] = useState<number | null>(null);
  
  // Session stats (needs re-render for UI)
  const [sessionStats, setSessionStats] = useState<SessionStats>({
    startTime: Date.now(),
    totalTaps: 0,
    peakTps: 0,
    currentTps: 0,
  });
  
  const { showError: showToastError, showSuccess } = useToast();
  
  // ==================== REFS (No re-renders) ====================
  const tapCountRef = useRef(0);
  const pendingTapsRef = useRef(0);
  const localGhdRef = useRef(0);
  const syncTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const lastHapticRef = useRef(0);
  const lastTapTimeRef = useRef(0);
  const comboTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const comboCountRef = useRef(0);
  
  // DOM refs
  const tapButtonRef = useRef<HTMLButtonElement>(null);
  const floatingContainerRef = useRef<HTMLDivElement>(null);
  const displayRef = useRef<HTMLSpanElement>(null);
  const pendingDisplayRef = useRef<HTMLSpanElement>(null);
  const tpsDisplayRef = useRef<HTMLSpanElement>(null);
  const progressRingRef = useRef<SVGCircleElement>(null);
  
  // Sync state refs
  const isSyncingRef = useRef(false);
  const syncProgressRef = useRef(0);
  
  // Object pool for floating numbers
  const floatingPoolRef = useRef<FloatingNumberElement[]>([]);
  const poolInitializedRef = useRef(false);
  
  // TPS calculation
  const tapTimestampsRef = useRef<number[]>([]);
  const rafIdRef = useRef<number | null>(null);
  
  // ==================== OBJECT POOL INITIALIZATION ====================
  const initializeFloatingPool = useCallback(() => {
    if (poolInitializedRef.current || !floatingContainerRef.current) return;
    
    const container = floatingContainerRef.current;
    floatingPoolRef.current = [];
    
    for (let i = 0; i < POOL_SIZE; i++) {
      const span = document.createElement('span');
      span.className = styles.floatingNumber;
      span.style.display = 'none';
      span.textContent = '+1';
      container.appendChild(span);
      
      // Use animationend for cleanup instead of setTimeout
      span.addEventListener('animationend', () => {
        span.style.display = 'none';
        span.classList.remove(styles.floatingNumberActive);
        const poolItem = floatingPoolRef.current.find(p => p.element === span);
        if (poolItem) poolItem.inUse = false;
      });
      
      floatingPoolRef.current.push({ element: span, inUse: false });
    }
    
    poolInitializedRef.current = true;
  }, []);
  
  // ==================== FLOATING NUMBER (OBJECT POOL) ====================
  const showFloatingNumber = useCallback((x: number, y: number, isComboMilestone = false) => {
    // Find available element in pool
    const available = floatingPoolRef.current.find(p => !p.inUse);
    if (!available) return; // Pool exhausted, skip this one
    
    available.inUse = true;
    const span = available.element;
    
    // Set position and content
    span.style.left = `${x}px`;
    span.style.top = `${y}px`;
    span.textContent = isComboMilestone ? `+${comboCountRef.current}!` : '+1';
    
    // Apply milestone styling if applicable
    if (isComboMilestone) {
      span.classList.add(styles.floatingNumberMilestone);
    } else {
      span.classList.remove(styles.floatingNumberMilestone);
    }
    
    // Trigger animation by forcing reflow and adding active class
    span.style.display = 'block';
    // Force reflow to restart animation
    void span.offsetWidth;
    span.classList.add(styles.floatingNumberActive);
  }, []);
  
  // ==================== OFFLINE PERSISTENCE ====================
  const persistPendingTaps = useCallback(() => {
    if (pendingTapsRef.current > 0) {
      const existing = parseInt(localStorage.getItem(TAP_PERSISTENCE_KEY) || '0', 10);
      localStorage.setItem(TAP_PERSISTENCE_KEY, String(existing + pendingTapsRef.current));
    }
  }, []);
  
  const loadPersistedTaps = useCallback(() => {
    const persisted = parseInt(localStorage.getItem(TAP_PERSISTENCE_KEY) || '0', 10);
    if (persisted > 0) {
      pendingTapsRef.current += persisted;
      localGhdRef.current += persisted;
      tapCountRef.current += persisted;
      localStorage.removeItem(TAP_PERSISTENCE_KEY);
      return persisted;
    }
    return 0;
  }, []);
  
  // ==================== TPS CALCULATION ====================
  const calculateTps = useCallback(() => {
    const now = Date.now();
    const oneSecondAgo = now - 1000;
    
    // Remove old timestamps
    tapTimestampsRef.current = tapTimestampsRef.current.filter(t => t > oneSecondAgo);
    
    return tapTimestampsRef.current.length;
  }, []);
  
  // ==================== RAF-BASED DISPLAY UPDATE ====================
  const updateDisplays = useCallback(() => {
    // Update tap count display
    if (displayRef.current) {
      displayRef.current.textContent = tapCountRef.current.toLocaleString();
    }
    
    // Update pending display
    if (pendingDisplayRef.current) {
      if (localGhdRef.current > 0) {
        pendingDisplayRef.current.textContent = `+${localGhdRef.current.toLocaleString()} pending${isSyncingRef.current ? ' (syncing...)' : ''}`;
        pendingDisplayRef.current.style.display = 'block';
      } else {
        pendingDisplayRef.current.style.display = 'none';
      }
    }
    
    // Update TPS display
    const tps = calculateTps();
    if (tpsDisplayRef.current) {
      tpsDisplayRef.current.textContent = `${tps} taps/sec`;
    }
    
    // Update session stats (only if TPS changed significantly)
    setSessionStats(prev => {
      const newPeakTps = Math.max(prev.peakTps, tps);
      if (prev.currentTps !== tps || prev.peakTps !== newPeakTps || prev.totalTaps !== tapCountRef.current) {
        return {
          ...prev,
          totalTaps: tapCountRef.current,
          currentTps: tps,
          peakTps: newPeakTps,
        };
      }
      return prev;
    });
    
    // Update progress ring
    if (progressRingRef.current) {
      const progress = (pendingTapsRef.current / TAP_BATCH_SIZE) * 100;
      const circumference = 2 * Math.PI * 78; // radius = 78
      const offset = circumference - (progress / 100) * circumference;
      progressRingRef.current.style.strokeDashoffset = String(offset);
    }
    
    // Schedule next frame
    rafIdRef.current = requestAnimationFrame(updateDisplays);
  }, [calculateTps]);
  
  // ==================== SYNC TAPS ====================
  const syncTaps = useCallback(async () => {
    const tapsToSync = pendingTapsRef.current;
    if (tapsToSync <= 0 || isSyncingRef.current) return;
    
    isSyncingRef.current = true;
    setSyncing(true);
    
    try {
      const result = await sendAirdropTaps(tapsToSync);
      
      // Clear persisted taps on successful sync
      localStorage.removeItem(TAP_PERSISTENCE_KEY);
      
      // Only subtract synced taps
      pendingTapsRef.current = Math.max(0, pendingTapsRef.current - tapsToSync);
      localGhdRef.current = pendingTapsRef.current;
      
      setStatus(prev => {
        if (!prev) return prev;
        return {
          ...prev,
          wallet: result.wallet,
          airdrop: {
            ...prev.airdrop,
            ghd_balance: result.wallet.ghd_balance,
          }
        };
      });
      
      // Show sync success feedback
      hapticFeedback('success');
      
    } catch (err) {
      console.error('Failed to sync taps:', err);
      // Persist failed taps for later
      persistPendingTaps();
      hapticFeedback('error');
    } finally {
      isSyncingRef.current = false;
      setSyncing(false);
    }
  }, [persistPendingTaps]);
  
  const scheduleTapSync = useCallback(() => {
    if (syncTimeoutRef.current) {
      clearTimeout(syncTimeoutRef.current);
    }
    
    syncTimeoutRef.current = setTimeout(() => {
      syncTaps();
    }, TAP_SYNC_INTERVAL);
  }, [syncTaps]);
  
  // ==================== COMBO SYSTEM ====================
  const updateCombo = useCallback(() => {
    const now = Date.now();
    const timeSinceLastTap = now - lastTapTimeRef.current;
    
    // Clear existing timeout
    if (comboTimeoutRef.current) {
      clearTimeout(comboTimeoutRef.current);
    }
    
    // Check if combo continues
    if (timeSinceLastTap < COMBO_DECAY_MS) {
      comboCountRef.current += 1;
    } else {
      comboCountRef.current = 1;
    }
    
    lastTapTimeRef.current = now;
    
    // Update state for UI (throttled)
    const currentCombo = comboCountRef.current;
    setComboCount(currentCombo);
    
    // Check for milestone
    if (COMBO_MILESTONES.includes(currentCombo)) {
      setShowComboMilestone(currentCombo);
      hapticFeedback('success');
      
      // Hide milestone after animation
      setTimeout(() => {
        setShowComboMilestone(null);
      }, 1500);
    }
    
    // Set decay timeout
    comboTimeoutRef.current = setTimeout(() => {
      comboCountRef.current = 0;
      setComboCount(0);
    }, COMBO_DECAY_MS);
  }, []);
  
  // ==================== TAP HANDLER (OPTIMIZED) ====================
  const processTap = useCallback((x: number, y: number) => {
    if (!status) return;
    
    // Record timestamp for TPS
    tapTimestampsRef.current.push(Date.now());
    
    // Throttled haptic feedback
    const now = Date.now();
    if (now - lastHapticRef.current >= HAPTIC_THROTTLE_MS) {
      hapticFeedback('light');
      lastHapticRef.current = now;
    }
    
    // Update counters
    tapCountRef.current += 1;
    pendingTapsRef.current += 1;
    localGhdRef.current += 1;
    
    // Update combo
    updateCombo();
    
    // Check for combo milestone for special floating number
    const isComboMilestone = COMBO_MILESTONES.includes(comboCountRef.current);
    
    // Show floating number (object pool)
    showFloatingNumber(x, y, isComboMilestone);
    
    // Add visual tap effect
    if (tapButtonRef.current) {
      tapButtonRef.current.classList.add(styles.tapping);
      // Use double RAF for smoother animation removal
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          tapButtonRef.current?.classList.remove(styles.tapping);
        });
      });
    }
    
    // Check if we should sync
    if (pendingTapsRef.current >= TAP_BATCH_SIZE) {
      syncTaps();
    } else {
      scheduleTapSync();
    }
  }, [status, syncTaps, scheduleTapSync, showFloatingNumber, updateCombo]);
  
  // Handle click events
  const handleClick = useCallback((event: React.MouseEvent) => {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    processTap(x, y);
  }, [processTap]);
  
  // Handle touch events with proper multi-touch support
  const handleTouchStart = useCallback((event: React.TouchEvent) => {
    event.preventDefault(); // Prevent double-tap zoom and scroll
    
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    
    // Process each individual touch point
    for (let i = 0; i < event.changedTouches.length; i++) {
      const touch = event.changedTouches[i];
      const x = touch.clientX - rect.left;
      const y = touch.clientY - rect.top;
      processTap(x, y);
    }
  }, [processTap]);
  
  // ==================== DATA LOADING ====================
  const loadData = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await getAirdropStatus();
      setStatus(response);
      
      // Load persisted taps
      const persisted = loadPersistedTaps();
      if (persisted > 0) {
        showSuccess(`Recovered ${persisted} pending taps from last session`);
      }
      
      // Reset session stats
      setSessionStats({
        startTime: Date.now(),
        totalTaps: persisted,
        peakTps: 0,
        currentTps: 0,
      });
      
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      setError(errorMessage);
      showToastError(errorMessage);
    } finally {
      setLoading(false);
    }
  };
  
  // ==================== CONVERT HANDLERS ====================
  const handleConvert = async () => {
    setConvertError(null);
    
    if (!convertAmount || convertAmount.trim() === '') {
      setConvertError('Please enter an amount');
      showToastError('Please enter an amount');
      return;
    }

    const amount = parseFloat(convertAmount);
    if (isNaN(amount) || amount <= 0) {
      setConvertError('Please enter a valid amount greater than 0');
      showToastError('Please enter a valid amount greater than 0');
      return;
    }

    if (amount < MIN_GHD_CONVERT) {
      setConvertError(`Minimum conversion is ${MIN_GHD_CONVERT.toLocaleString()} GHD`);
      showToastError(`Minimum conversion is ${MIN_GHD_CONVERT.toLocaleString()} GHD`);
      return;
    }

    const totalGhd = parseFloat(status?.wallet.ghd_balance || '0') + localGhdRef.current;
    if (amount > totalGhd) {
      const errorMsg = `Insufficient GHD balance. You have ${totalGhd.toLocaleString()} GHD available.`;
      setConvertError(errorMsg);
      showToastError(errorMsg);
      return;
    }

    try {
      setConverting(true);
      setConvertError(null);
      
      // Sync any pending taps first
      if (pendingTapsRef.current > 0) {
        await syncTaps();
      }
      
      const result = await convertGhdToUsdt(amount);
      
      // Check if verification is required
      if ((result as any).requires_withdrawal_verification) {
        setVerificationData({
          amountUsdt: result.received_usdt,
          riskLevel: (result as any).risk_level,
          riskFactors: (result as any).risk_factors || [],
          educationalContent: (result as any).educational_content,
        });
        setShowVerificationModal(true);
        hapticFeedback('warning');
      } else {
        hapticFeedback('success');
        setStatus(prev => {
          if (!prev) return prev;
          return {
            ...prev,
            wallet: result.wallet,
            airdrop: {
              ...prev.airdrop,
              ghd_balance: result.wallet.ghd_balance,
              estimated_usdt_from_ghd: (
                parseFloat(result.wallet.ghd_balance) / prev.airdrop.ghd_per_usdt
              ).toFixed(8),
            }
          };
        });
        
        setConvertAmount('');
        setShowConvert(false);
        showSuccess(`Converted ${parseFloat(result.converted_ghd).toLocaleString()} GHD to $${parseFloat(result.received_usdt).toFixed(4)} USDT`);
      }
    } catch (err) {
      hapticFeedback('error');
      const errorMessage = getFriendlyErrorMessage(err as Error);
      setConvertError(errorMessage);
      showToastError(errorMessage);
    } finally {
      setConverting(false);
    }
  };

  const handleQuickConvert = (percentage: number) => {
    const totalGhd = parseFloat(status?.wallet.ghd_balance || '0') + localGhdRef.current;
    const amount = Math.floor(totalGhd * percentage);
    setConvertAmount(String(Math.max(amount, MIN_GHD_CONVERT)));
  };

  const handleConvertAll = () => {
    const totalGhd = parseFloat(status?.wallet.ghd_balance || '0') + localGhdRef.current;
    setConvertAmount(String(Math.floor(totalGhd)));
  };
  
  // ==================== COMPUTED VALUES ====================
  const displayValues = useMemo(() => {
    const totalGhd = parseFloat(status?.wallet.ghd_balance || '0') + localGhdRef.current;
    const estimatedUsdt = totalGhd / (status?.airdrop.ghd_per_usdt || 1000);
    const canConvert = totalGhd >= MIN_GHD_CONVERT;
    const progressToConvert = Math.min((totalGhd / MIN_GHD_CONVERT) * 100, 100);
    return { totalGhd, estimatedUsdt, canConvert, progressToConvert };
  }, [status, sessionStats.totalTaps]); // Update when totalTaps changes
  
  const sessionDuration = useMemo(() => {
    const seconds = Math.floor((Date.now() - sessionStats.startTime) / 1000);
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }, [sessionStats.startTime, sessionStats.totalTaps]); // Re-calculate periodically
  
  // ==================== EFFECTS ====================
  useEffect(() => {
    loadData();
    
    // Initialize floating number pool after mount
    const poolTimer = setTimeout(() => {
      initializeFloatingPool();
    }, 100);
    
    // Start RAF-based display updates
    rafIdRef.current = requestAnimationFrame(updateDisplays);
    
    // Persist taps on page unload
    const handleBeforeUnload = () => {
      persistPendingTaps();
    };
    window.addEventListener('beforeunload', handleBeforeUnload);
    
    // Visibility change handling
    const handleVisibilityChange = () => {
      if (document.hidden) {
        persistPendingTaps();
      }
    };
    document.addEventListener('visibilitychange', handleVisibilityChange);
    
    return () => {
      if (syncTimeoutRef.current) clearTimeout(syncTimeoutRef.current);
      if (comboTimeoutRef.current) clearTimeout(comboTimeoutRef.current);
      if (rafIdRef.current) cancelAnimationFrame(rafIdRef.current);
      clearTimeout(poolTimer);
      window.removeEventListener('beforeunload', handleBeforeUnload);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      persistPendingTaps();
    };
  }, []);
  
  // ==================== RENDER ====================
  if (loading) {
    return <LoadingScreen message="Loading airdrop..." />;
  }

  if (error) {
    return <ErrorState message={error} onRetry={loadData} />;
  }

  return (
    <PullToRefresh onRefresh={loadData}>
      <div className={styles.container}>
        {/* Balance Card */}
        <Card variant="glow">
          <CardContent>
            <div className={styles.balanceSection}>
              <span className={styles.balanceLabel}>Your GHD Balance</span>
              <div className={styles.balanceValue}>
                <span className={styles.ghdAmount}>
                  {displayValues.totalGhd.toLocaleString(undefined, { maximumFractionDigits: 0 })}
                </span>
                <span className={styles.ghdSymbol}>GHD</span>
              </div>
              <span className={styles.usdtEstimate}>≈ ${displayValues.estimatedUsdt.toFixed(4)} USDT</span>
              <span 
                ref={pendingDisplayRef}
                className={styles.pendingTaps}
                style={{ display: 'none' }}
              />
              
              {/* Progress to minimum convert */}
              {!displayValues.canConvert && (
                <div className={styles.convertProgress}>
                  <div className={styles.convertProgressBar}>
                    <div 
                      className={styles.convertProgressFill}
                      style={{ width: `${displayValues.progressToConvert}%` }}
                    />
                  </div>
                  <span className={styles.convertProgressText}>
                    {Math.floor(displayValues.progressToConvert)}% to minimum convert
                  </span>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Session Stats Bar */}
        <div className={styles.sessionStatsBar}>
          <div className={styles.sessionStat}>
            <span className={styles.sessionStatValue}>{sessionDuration}</span>
            <span className={styles.sessionStatLabel}>Session</span>
          </div>
          <div className={styles.sessionStatDivider} />
          <div className={styles.sessionStat}>
            <span ref={tpsDisplayRef} className={styles.sessionStatValue}>0 taps/sec</span>
            <span className={styles.sessionStatLabel}>Speed</span>
          </div>
          <div className={styles.sessionStatDivider} />
          <div className={styles.sessionStat}>
            <span className={styles.sessionStatValue}>{sessionStats.peakTps}</span>
            <span className={styles.sessionStatLabel}>Peak TPS</span>
          </div>
        </div>

        {/* Tapping Area */}
        <div className={styles.tapSection}>
          <div className={styles.tapHeader}>
            <h2 className={styles.tapTitle}>Tap to Mine</h2>
            <p className={styles.tapSubtitle}>Earn 1 GHD per tap • Tap fast for combos!</p>
          </div>
          
          <div className={styles.tapButtonWrapper}>
            {/* Progress Ring SVG */}
            <svg className={styles.progressRing} viewBox="0 0 180 180">
              <circle
                className={styles.progressRingBg}
                cx="90"
                cy="90"
                r="78"
                fill="none"
                strokeWidth="4"
              />
              <circle
                ref={progressRingRef}
                className={styles.progressRingFill}
                cx="90"
                cy="90"
                r="78"
                fill="none"
                strokeWidth="4"
                strokeDasharray={2 * Math.PI * 78}
                strokeDashoffset={2 * Math.PI * 78}
              />
            </svg>
            
            <button 
              ref={tapButtonRef}
              className={styles.tapButton}
              onClick={handleClick}
              onTouchStart={handleTouchStart}
              onTouchMove={(e) => e.preventDefault()}
              onContextMenu={(e) => e.preventDefault()}
            >
              <GhidarCoin size={100} animate />
              <div className={styles.tapRipple} />
              <div className={styles.tapRipple2} />
              <div className={styles.tapRipple3} />
            </button>
            
            {/* Floating numbers container - managed via object pool */}
            <div ref={floatingContainerRef} className={styles.floatingContainer} />
            
            {/* Combo indicator */}
            {comboCount >= 5 && (
              <div className={`${styles.comboIndicator} ${comboCount >= 25 ? styles.comboHot : ''}`}>
                <span className={styles.comboCount}>{comboCount}x</span>
                <span className={styles.comboLabel}>COMBO</span>
                {comboCount >= 10 && <div className={styles.comboFlame}>🔥</div>}
              </div>
            )}
            
            {/* Combo milestone celebration */}
            {showComboMilestone && (
              <div className={styles.comboMilestone}>
                <span className={styles.milestoneValue}>{showComboMilestone}x</span>
                <span className={styles.milestoneText}>COMBO!</span>
              </div>
            )}
          </div>
          
          <div className={styles.tapStats}>
            <div className={styles.tapStat}>
              <span ref={displayRef} className={styles.tapStatValue}>0</span>
              <span className={styles.tapStatLabel}>Taps this session</span>
            </div>
          </div>
          
          {/* Sync indicator */}
          {syncing && (
            <div className={styles.syncIndicator}>
              <div className={styles.syncSpinner} />
              <span>Syncing taps...</span>
            </div>
          )}
        </div>

        {/* Convert Section */}
        <Card variant="elevated">
          <CardHeader>
            <CardTitle>
              Convert to USDT
              <HelpTooltip content="Convert your mined GHD tokens to USDT at the current exchange rate. Minimum conversion is 1,000 GHD." />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className={styles.convertInfo}>
              <div className={styles.convertRate}>
                <span className={styles.rateLabel}>
                  Exchange Rate
                  <HelpTooltip content="This rate determines how many GHD tokens equal 1 USDT." />
                </span>
                <span className={styles.rateValue}>
                  {status?.airdrop.ghd_per_usdt.toLocaleString()} GHD = 1 USDT
                </span>
              </div>
            </div>

            {showConvert ? (
              <div className={styles.convertForm}>
                <NumberInput
                  label="GHD Amount"
                  value={convertAmount}
                  onChange={(val) => {
                    setConvertAmount(val);
                    setConvertError(null);
                  }}
                  placeholder={`Min ${MIN_GHD_CONVERT.toLocaleString()} GHD`}
                  error={convertError || undefined}
                  rightElement={
                    <button className={styles.maxButton} onClick={handleConvertAll}>
                      MAX
                    </button>
                  }
                />
                
                {/* Quick amount buttons */}
                <div className={styles.quickAmounts}>
                  <button 
                    className={styles.quickAmountBtn}
                    onClick={() => handleQuickConvert(0.25)}
                    disabled={!displayValues.canConvert}
                  >
                    25%
                  </button>
                  <button 
                    className={styles.quickAmountBtn}
                    onClick={() => handleQuickConvert(0.50)}
                    disabled={!displayValues.canConvert}
                  >
                    50%
                  </button>
                  <button 
                    className={styles.quickAmountBtn}
                    onClick={() => handleQuickConvert(0.75)}
                    disabled={!displayValues.canConvert}
                  >
                    75%
                  </button>
                  <button 
                    className={styles.quickAmountBtn}
                    onClick={handleConvertAll}
                    disabled={!displayValues.canConvert}
                  >
                    100%
                  </button>
                </div>
                
                {convertAmount && !convertError && parseFloat(convertAmount) >= MIN_GHD_CONVERT && (
                  <div className={styles.convertPreview}>
                    You will receive: <strong>${(parseFloat(convertAmount) / (status?.airdrop.ghd_per_usdt || 1000)).toFixed(4)} USDT</strong>
                  </div>
                )}
                
                <div className={styles.convertActions}>
                  <Button variant="secondary" onClick={() => {
                    setShowConvert(false);
                    setConvertAmount('');
                    setConvertError(null);
                  }}>
                    Cancel
                  </Button>
                  <Button 
                    loading={converting} 
                    onClick={handleConvert}
                    disabled={!displayValues.canConvert}
                  >
                    Convert
                  </Button>
                </div>
              </div>
            ) : (
              <Button 
                fullWidth 
                onClick={() => setShowConvert(true)}
                disabled={!displayValues.canConvert}
              >
                {displayValues.canConvert 
                  ? 'Convert GHD' 
                  : `Need ${(MIN_GHD_CONVERT - displayValues.totalGhd).toLocaleString()} more GHD`
                }
              </Button>
            )}
          </CardContent>
        </Card>

        {/* USDT Balance */}
        <Card>
          <CardContent>
            <div className={styles.usdtBalance}>
              <span className={styles.usdtLabel}>Wallet Balance</span>
              <span className={styles.usdtValue}>
                ${parseFloat(status?.wallet.usdt_balance || '0').toFixed(2)} USDT
              </span>
            </div>
          </CardContent>
        </Card>

        {/* Withdrawal Verification Modal */}
        {verificationData && (
          <WithdrawalVerificationModal
            isOpen={showVerificationModal}
            onClose={() => {
              setShowVerificationModal(false);
              setVerificationData(null);
            }}
            amountUsdt={verificationData.amountUsdt}
            network="internal"
            riskLevel={verificationData.riskLevel}
            riskFactors={verificationData.riskFactors}
            educationalContent={verificationData.educationalContent}
            onVerificationComplete={async () => {
              await loadData();
              setShowVerificationModal(false);
              setVerificationData(null);
              showSuccess('Verification completed successfully!');
            }}
          />
        )}
      </div>
    </PullToRefresh>
  );
}
