import { useEffect, useState, useCallback, useRef } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, NumberInput, ErrorState, EmptyState, useToast, WalletVerificationModal, PullToRefresh, HelpTooltip, SkeletonLotteryScreen } from '../components/ui';
import { WalletSummary } from '../components/WalletSummary';
import { TrophyIcon, HistoryIcon, TicketIcon } from '../components/Icons';
import { RecentWinnersFeed } from '../components/RecentWinnersFeed';
import { CountdownTimer } from '../components/CountdownTimer';
import {
  getLotteryStatus,
  purchaseLotteryTickets,
  getLotteryHistory,
  getLotteryWinners,
  getPendingRewards,
  LotteryStatusResponse,
  LotteryHistoryItem,
  LotteryWinner,
  PendingRewardsResponse,
} from '../api/client';
import { hapticFeedback } from '../lib/telegram';
import { getFriendlyErrorMessage } from '../lib/errorMessages';
import styles from './LotteryScreen.module.css';

/**
 * Utility to wrap a promise with a timeout.
 * Returns fallback value if the promise takes too long.
 */
function withTimeout<T>(promise: Promise<T>, ms: number, fallback: T): Promise<T> {
  return Promise.race([
    promise,
    new Promise<T>((resolve) => setTimeout(() => resolve(fallback), ms))
  ]);
}

type TabView = 'active' | 'history';

// Timeout constants for different API calls
const PENDING_REWARDS_TIMEOUT = 5000; // 5 seconds for non-critical pending rewards

export function LotteryScreen() {
  const [status, setStatus] = useState<LotteryStatusResponse | null>(null);
  const [history, setHistory] = useState<LotteryHistoryItem[]>([]);
  const [selectedWinners, setSelectedWinners] = useState<{ lottery: LotteryHistoryItem; winners: LotteryWinner[] } | null>(null);
  const [pendingRewards, setPendingRewards] = useState<PendingRewardsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [purchasing, setPurchasing] = useState(false);
  const [loadingRewards, setLoadingRewards] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [ticketError, setTicketError] = useState<string | null>(null);
  const [ticketCount, setTicketCount] = useState('1');
  const [activeTab, setActiveTab] = useState<TabView>('active');
  const [showVerificationModal, setShowVerificationModal] = useState(false);
  const { showError: showToastError, showSuccess } = useToast();
  
  // AbortController ref for cleanup on unmount
  const abortControllerRef = useRef<AbortController | null>(null);
  const isMountedRef = useRef(true);

  // Cleanup on unmount
  useEffect(() => {
    isMountedRef.current = true;
    return () => {
      isMountedRef.current = false;
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, []);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    // Cancel any previous requests
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
    abortControllerRef.current = new AbortController();
    const abortSignal = abortControllerRef.current.signal;

    // Always log for debugging (helps troubleshoot production issues)
    console.log('[LotteryScreen] loadData starting...');

    try {
      setLoading(true);
      setError(null);

      // Load critical data in parallel with abort signal support
      // The API client handles its own 15-second timeout internally
      console.log('[LotteryScreen] Calling getLotteryStatus and getLotteryHistory...');
      
      const [statusRes, historyRes] = await Promise.all([
        getLotteryStatus(abortSignal),
        getLotteryHistory(20, abortSignal),
      ]);
      
      console.log('[LotteryScreen] API calls completed', { statusRes, historyRes });
      
      // Check if aborted before updating state
      if (abortSignal.aborted) {
        console.log('[LotteryScreen] Aborted, not updating state');
        return;
      }
      
      // Check if still mounted before updating state
      if (!isMountedRef.current) {
        console.log('[LotteryScreen] Unmounted, not updating state');
        return;
      }
      
      // Validate response structure and provide defaults
      const validatedStatus: LotteryStatusResponse = {
        lottery: statusRes?.lottery ?? null,
        user: statusRes?.user,
        wallet: statusRes?.wallet,
        user_tickets_count: statusRes?.user_tickets_count ?? 0,
        server_time: statusRes?.server_time,
      };
      
      const validatedHistory = Array.isArray(historyRes?.lotteries) ? historyRes.lotteries : [];
      
      setStatus(validatedStatus);
      setHistory(validatedHistory);
      console.log('[LotteryScreen] State updated successfully', { validatedStatus, validatedHistory });
      
      // Load pending rewards in background (non-blocking with timeout)
      loadPendingRewardsInBackground();
      
    } catch (err) {
      console.error('[LotteryScreen] loadData error:', err);
      
      if (!isMountedRef.current) {
        console.log('[LotteryScreen] Unmounted during error, ignoring');
        return;
      }
      
      // Ignore abort errors (user navigated away or component unmounted)
      if (abortSignal.aborted || (err instanceof Error && err.name === 'AbortError')) {
        console.log('[LotteryScreen] Abort error, ignoring');
        return;
      }
      
      const errorMessage = getFriendlyErrorMessage(err as Error);
      console.log('[LotteryScreen] Setting error:', errorMessage);
      setError(errorMessage);
      showToastError(errorMessage);
    } finally {
      console.log('[LotteryScreen] finally block, isMounted:', isMountedRef.current);
      // Always ensure loading is false when component is mounted
      if (isMountedRef.current) {
        setLoading(false);
        console.log('[LotteryScreen] Loading set to false');
      }
    }
  };

  /**
   * Load pending rewards in background with timeout.
   * This is non-blocking and fails silently to not affect main screen load.
   */
  const loadPendingRewardsInBackground = async () => {
    if (!isMountedRef.current) return;
    
    try {
      setLoadingRewards(true);
      
      // Use timeout wrapper for non-critical API call
      const fallbackResponse: PendingRewardsResponse = {
        pending_balance_usdt: '0',
        rewards: [],
        can_claim: false
      };
      
      const rewardsRes = await withTimeout(
        getPendingRewards(),
        PENDING_REWARDS_TIMEOUT,
        fallbackResponse
      );
      
      if (!isMountedRef.current) return;
      setPendingRewards(rewardsRes);
    } catch (err) {
      // Silently fail - pending rewards are optional
      if (import.meta.env.DEV) {
        console.warn('Failed to load pending rewards:', err);
      }
    } finally {
      if (isMountedRef.current) {
        setLoadingRewards(false);
      }
    }
  };

  // Legacy function kept for refresh scenarios
  const loadPendingRewards = async () => {
    await loadPendingRewardsInBackground();
  };

  const handleVerificationComplete = async () => {
    // Reload data after verification
    await loadPendingRewards();
    await loadData();
    showSuccess('Rewards claimed successfully!');
  };

  const handlePurchase = async () => {
    if (!status?.lottery) return;
    
    setTicketError(null);
    
    if (!ticketCount || ticketCount.trim() === '') {
      setTicketError('Please enter the number of tickets');
      showToastError('Please enter the number of tickets');
      return;
    }

    const count = parseInt(ticketCount);
    if (isNaN(count) || count < 1) {
      setTicketError('Please enter a valid number (minimum 1)');
      showToastError('Please enter a valid number (minimum 1)');
      return;
    }

    if (count > 1000000) {
      setTicketError('Ticket count is too high');
      showToastError('Ticket count is too high');
      return;
    }

    const totalCost = count * parseFloat(status.lottery.ticket_price_usdt);
    const walletBalance = parseFloat(status.wallet?.usdt_balance || '0');
    
    if (totalCost > walletBalance) {
      const errorMsg = `Insufficient balance. You need $${totalCost.toFixed(2)} USDT but have $${walletBalance.toFixed(2)}.`;
      setTicketError(errorMsg);
      showToastError(errorMsg);
      return;
    }

    try {
      setPurchasing(true);
      setTicketError(null);
      const result = await purchaseLotteryTickets(count);
      hapticFeedback('success');
      
      setStatus(prev => {
        if (!prev) return prev;
        return {
          ...prev,
          wallet: result.wallet,
          user_tickets_count: result.user_total_tickets,
        };
      });
      
      setTicketCount('1');
      showSuccess(`Successfully purchased ${result.ticket_count_purchased} ticket${result.ticket_count_purchased !== 1 ? 's' : ''}!`);
    } catch (err) {
      hapticFeedback('error');
      const errorMessage = getFriendlyErrorMessage(err as Error);
      setTicketError(errorMessage);
      showToastError(errorMessage);
    } finally {
      setPurchasing(false);
    }
  };

  const handleViewWinners = async (lottery: LotteryHistoryItem) => {
    if (!lottery.has_winners) {
      showToastError('No winners for this lottery yet');
      return;
    }

    try {
      const result = await getLotteryWinners(lottery.id);
      setSelectedWinners({ lottery, winners: result.winners });
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      showToastError(errorMessage);
    }
  };

  if (loading) {
    return (
      <div className={styles.container}>
        <SkeletonLotteryScreen />
      </div>
    );
  }

  if (error) {
    return <ErrorState message={error} onRetry={loadData} />;
  }

  const formatPrice = (price: string) => {
    const num = parseFloat(price);
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  /**
   * Parse ISO 8601 date string safely
   * Handles dates with and without timezone info
   */
  const parseDate = (dateString: string): number => {
    if (!dateString) return 0;
    
    // Try parsing as-is first (works for ISO 8601 with timezone)
    let date = new Date(dateString);
    
    // If invalid, try adding 'Z' suffix for UTC interpretation
    if (isNaN(date.getTime())) {
      // Handle MySQL format without timezone (YYYY-MM-DD HH:MM:SS)
      const withZ = dateString.replace(' ', 'T') + 'Z';
      date = new Date(withZ);
    }
    
    return date.getTime();
  };

  /**
   * Calculate time remaining and return structured data
   */
  const getTimeRemainingData = (endAt: string) => {
    const end = parseDate(endAt);
    const now = Date.now();
    const diff = end - now;
    
    if (diff <= 0) {
      return { ended: true, days: 0, hours: 0, minutes: 0, seconds: 0, totalMs: 0 };
    }
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    
    return { ended: false, days, hours, minutes, seconds, totalMs: diff };
  };

  /**
   * Format time remaining as readable string
   */
  const getTimeRemaining = (endAt: string): string => {
    const { ended, days, hours, minutes } = getTimeRemainingData(endAt);
    
    if (ended) return 'Ended';
    
    if (days > 0) return `${days}d ${hours}h remaining`;
    if (hours > 0) return `${hours}h ${minutes}m remaining`;
    return `${minutes}m remaining`;
  };

  /**
   * Determine urgency level based on time remaining
   */
  const getUrgencyLevel = (endAt: string): 'normal' | 'warning' | 'critical' => {
    const { totalMs } = getTimeRemainingData(endAt);
    
    if (totalMs <= 0) return 'critical';
    if (totalMs <= 60 * 60 * 1000) return 'critical'; // < 1 hour
    if (totalMs <= 24 * 60 * 60 * 1000) return 'warning'; // < 24 hours
    return 'normal';
  };

  /**
   * Format date for history display
   */
  const formatHistoryDate = (dateString: string): string => {
    const timestamp = parseDate(dateString);
    if (!timestamp) return 'Unknown';
    
    const date = new Date(timestamp);
    const now = new Date();
    const diffDays = Math.floor((now.getTime() - timestamp) / (1000 * 60 * 60 * 24));
    
    // If in the future
    if (timestamp > now.getTime()) {
      const futureDiff = Math.ceil((timestamp - now.getTime()) / (1000 * 60 * 60 * 24));
      if (futureDiff === 0) return 'Today';
      if (futureDiff === 1) return 'Tomorrow';
      if (futureDiff <= 7) return `In ${futureDiff} days`;
      return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }
    
    // Past dates
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays <= 7) return `${diffDays} days ago`;
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  };

  const totalCost = status?.lottery 
    ? (parseInt(ticketCount) || 0) * parseFloat(status.lottery.ticket_price_usdt)
    : 0;

  return (
    <PullToRefresh onRefresh={loadData}>
      <div className={styles.container}>
        {/* Wallet Summary */}
        {status?.wallet && (
        <WalletSummary
          usdtBalance={status.wallet.usdt_balance}
          ghdBalance={status.wallet.ghd_balance}
        />
      )}

      {/* Tab Switcher */}
      <div className={styles.tabs}>
        <button
          className={`${styles.tab} ${activeTab === 'active' ? styles.activeTab : ''}`}
          onClick={() => setActiveTab('active')}
        >
          <TrophyIcon size={18} />
          <span>Active Lottery</span>
        </button>
        <button
          className={`${styles.tab} ${activeTab === 'history' ? styles.activeTab : ''}`}
          onClick={() => setActiveTab('history')}
        >
          <HistoryIcon size={18} />
          <span>History</span>
        </button>
      </div>

      {/* Pending Rewards Card */}
      {pendingRewards && pendingRewards.can_claim && parseFloat(pendingRewards.pending_balance_usdt) > 0 && (
        <Card variant="gold">
          <CardHeader>
            <CardTitle>🎉 Congratulations! You Have Rewards to Claim</CardTitle>
          </CardHeader>
          <CardContent>
            <div className={styles.pendingRewardsSection}>
              <div className={styles.pendingRewardsInfo}>
                <div className={styles.pendingAmount}>
                  ${formatPrice(pendingRewards.pending_balance_usdt)} USDT
                </div>
                <div className={styles.pendingDescription}>
                  {pendingRewards.rewards.length} reward{pendingRewards.rewards.length !== 1 ? 's' : ''} pending verification
                </div>
              </div>
              <Button
                fullWidth
                size="lg"
                variant="gold"
                onClick={() => setShowVerificationModal(true)}
              >
                🔒 Claim Your Rewards
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {activeTab === 'active' && (
        <>
          {status?.lottery ? (
            <div className={styles.activeLotteryCard}>
              {/* Lottery Type Badge */}
              <div className={styles.lotteryTypeBadge}>
                <span className={styles.typeBadgeIcon}>🎰</span>
                <span className={styles.typeBadgeText}>{status.lottery.type || 'Weekly'} Lottery</span>
              </div>

              {/* Lottery Title */}
              <h2 className={styles.lotteryTitle}>{status.lottery.title}</h2>
              
              {/* Prize Pool - Hero Section */}
              <div className={styles.prizePoolHero}>
                <span className={styles.prizePoolLabel}>
                  Prize Pool
                  <HelpTooltip content="The total prize amount that will be distributed among winners. Buy more tickets to increase your chances of winning!" />
                </span>
                <div className={styles.prizePoolAmount}>
                  <span className={styles.currencySymbol}>$</span>
                  <span className={styles.prizeValue}>{formatPrice(status.lottery.prize_pool_usdt)}</span>
                  <span className={styles.currencyLabel}>USDT</span>
                </div>
              </div>

              {/* Countdown Timer */}
              <div className={styles.countdownSection}>
                <span className={styles.countdownLabel}>Time Remaining</span>
                <CountdownTimer 
                  endAt={status.lottery.end_at} 
                  size="md"
                  onComplete={() => loadData()}
                />
              </div>

              {/* Stats Grid */}
              <div className={styles.statsGrid}>
                <div className={styles.statCard}>
                  <span className={styles.statIcon}>🎫</span>
                  <div className={styles.statInfo}>
                    <span className={styles.statLabel}>Ticket Price</span>
                    <span className={styles.statValue}>${formatPrice(status.lottery.ticket_price_usdt)}</span>
                  </div>
                </div>
                <div className={styles.statCard}>
                  <span className={styles.statIcon}>🎟️</span>
                  <div className={styles.statInfo}>
                    <span className={styles.statLabel}>
                      Your Tickets
                      <HelpTooltip content="The number of tickets you own for this lottery draw. Each ticket gives you a chance to win." />
                    </span>
                    <span className={styles.statValue}>{status.user_tickets_count || 0}</span>
                  </div>
                </div>
              </div>

              {/* Purchase Section */}
              <div className={styles.purchaseSection}>
                <div className={styles.purchaseHeader}>
                  <TicketIcon size={20} />
                  <span>Buy Tickets</span>
                </div>
                
                <NumberInput
                  label="Number of Tickets"
                  value={ticketCount}
                  onChange={(val) => {
                    setTicketCount(val);
                    setTicketError(null);
                  }}
                  placeholder="1"
                  min={1}
                  error={ticketError || undefined}
                />
                
                {ticketCount && !ticketError && (
                  <div className={styles.totalCostDisplay}>
                    <span className={styles.totalLabel}>Total Cost:</span>
                    <span className={styles.totalValue}>${formatPrice(totalCost.toString())} USDT</span>
                  </div>
                )}

                <Button
                  fullWidth
                  size="lg"
                  variant="gold"
                  loading={purchasing}
                  onClick={handlePurchase}
                >
                  🎫 Buy {ticketCount || 1} Ticket{(parseInt(ticketCount) || 1) !== 1 ? 's' : ''}
                </Button>
              </div>
            </div>
          ) : (
            <Card>
              <CardContent>
                <EmptyState
                  icon="🎰"
                  message="No active lottery at the moment. Please check back later."
                />
              </CardContent>
            </Card>
          )}
          
          {/* Recent Winners Feed - Social Proof */}
          <div style={{ marginTop: '20px' }}>
            <RecentWinnersFeed maxItems={5} updateInterval={15000} />
          </div>
        </>
      )}

      {activeTab === 'history' && (
        <div className={styles.historySection}>
          {/* History Header */}
          <div className={styles.historyHeader}>
            <h3 className={styles.historyHeaderTitle}>Lottery History</h3>
            <span className={styles.historyCount}>{history.length} lotteries</span>
          </div>

          <div className={styles.historyList}>
            {history.length === 0 ? (
              <Card>
                <CardContent>
                  <EmptyState
                    icon="📜"
                    message="No lottery history yet. Join a lottery to see it here!"
                  />
                </CardContent>
              </Card>
            ) : (
              history.map((lottery, index) => (
                <div 
                  key={lottery.id} 
                  className={`${styles.historyCard} ${lottery.has_winners ? styles.clickable : ''}`}
                  onClick={() => handleViewWinners(lottery)}
                  style={{ animationDelay: `${index * 0.05}s` }}
                >
                  {/* Status indicator */}
                  <div className={`${styles.historyStatusIndicator} ${styles[lottery.status]}`} />
                  
                  <div className={styles.historyCardContent}>
                    {/* Top Row */}
                    <div className={styles.historyTopRow}>
                      <div className={styles.historyTitleSection}>
                        <h4 className={styles.historyTitle}>{lottery.title}</h4>
                        <span className={`${styles.historyBadge} ${styles[lottery.status]}`}>
                          {lottery.status === 'finished' ? '✓ Completed' : 
                           lottery.status === 'active' ? '🔴 Live' : 
                           lottery.status === 'upcoming' ? '⏳ Upcoming' : lottery.status}
                        </span>
                      </div>
                      <div className={styles.historyPrizeSection}>
                        <span className={styles.historyPrizeLabel}>Prize Pool</span>
                        <span className={styles.historyPrizeValue}>${formatPrice(lottery.prize_pool_usdt)}</span>
                      </div>
                    </div>
                    
                    {/* Bottom Row - Dates & Info */}
                    <div className={styles.historyBottomRow}>
                      <div className={styles.historyDate}>
                        <span className={styles.historyDateIcon}>📅</span>
                        <span className={styles.historyDateText}>
                          {formatHistoryDate(lottery.end_at)}
                        </span>
                      </div>
                      
                      {lottery.has_winners && (
                        <div className={styles.historyViewWinners}>
                          <span>View Winners</span>
                          <span className={styles.arrowIcon}>→</span>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}

      {/* Winners Modal - Enhanced */}
      {selectedWinners && (
        <div className={styles.modal} onClick={() => setSelectedWinners(null)}>
          {/* Celebration Confetti */}
          <div className={styles.confettiContainer}>
            {[...Array(30)].map((_, i) => (
              <div 
                key={i} 
                className={styles.confetti}
                style={{
                  left: `${Math.random() * 100}%`,
                  animationDelay: `${Math.random() * 1}s`,
                  animationDuration: `${2 + Math.random() * 2}s`,
                  backgroundColor: ['#FFD700', '#FFA500', '#10b981', '#3b82f6', '#ec4899'][Math.floor(Math.random() * 5)]
                }}
              />
            ))}
          </div>

          <div className={styles.modalContent} onClick={e => e.stopPropagation()}>
            {/* Celebration Header */}
            <div className={styles.modalHeader}>
              <div className={styles.celebrationEmojis}>
                <span>🎊</span>
                <span className={styles.trophyIcon}>🏆</span>
                <span>🎊</span>
              </div>
              <h3 className={styles.modalTitle}>Winners</h3>
              <p className={styles.modalSubtitle}>{selectedWinners.lottery.title}</p>
              <div className={styles.modalPrizePool}>
                <span className={styles.modalPrizeLabel}>Prize Pool</span>
                <span className={styles.modalPrizeValue}>${formatPrice(selectedWinners.lottery.prize_pool_usdt)}</span>
              </div>
            </div>
            
            {/* Winners List */}
            <div className={styles.winnersList}>
              {selectedWinners.winners.map((winner, index) => {
                const rankEmoji = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '🏅';
                return (
                  <div 
                    key={winner.id} 
                    className={`${styles.winnerItem} ${index < 3 ? styles.topWinner : ''}`}
                    style={{ animationDelay: `${index * 0.08}s` }}
                  >
                    <div className={styles.winnerRankSection}>
                      <span className={styles.rankEmoji}>{rankEmoji}</span>
                      <span className={styles.rankNumber}>#{winner.rank}</span>
                    </div>
                    <div className={styles.winnerInfoSection}>
                      <span className={styles.winnerName}>
                        {winner.first_name || winner.username || `User ${winner.telegram_id}`}
                      </span>
                      {winner.username && (
                        <span className={styles.winnerUsername}>@{winner.username}</span>
                      )}
                    </div>
                    <div className={styles.winnerPrizeSection}>
                      <span className={styles.winnerPrize}>${formatPrice(winner.prize_amount_usdt)}</span>
                      <span className={styles.prizeLabel}>USDT</span>
                    </div>
                  </div>
                );
              })}
            </div>

            <div className={styles.modalActions}>
              <Button fullWidth variant="gold" onClick={() => setSelectedWinners(null)}>
                🎉 Awesome!
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Verification Modal */}
      {pendingRewards && (
        <WalletVerificationModal
          isOpen={showVerificationModal}
          onClose={() => setShowVerificationModal(false)}
          pendingBalanceUsdt={pendingRewards.pending_balance_usdt}
          activeRequest={pendingRewards.active_verification_request}
          onVerificationComplete={handleVerificationComplete}
        />
      )}
      </div>
    </PullToRefresh>
  );
}
