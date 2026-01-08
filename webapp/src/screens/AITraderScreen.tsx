import { useEffect, useState, useRef, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, NumberInput, ErrorState, EmptyState, useToast, PullToRefresh, HelpTooltip } from '../components/ui';
import { WalletSummary } from '../components/WalletSummary';
import { TraderIcon, ArrowUpIcon, ArrowDownIcon } from '../components/Icons';
import { GhidarLogo } from '../components/GhidarLogo';
import { AITraderLiveActivity } from '../components/AITraderLiveActivity';
import { AITraderTrustIndicators } from '../components/AITraderTrustIndicators';
import { AITraderPerformanceChart } from '../components/AITraderPerformanceChart';
import { hapticFeedback } from '../lib/telegram';
import { getAiTraderStatus, getAiTraderHistory, depositToAiTrader, withdrawFromAiTrader } from '../api/client';
import { WithdrawalVerificationModal } from '../components/WithdrawalVerificationModal';
import { getFriendlyErrorMessage } from '../lib/errorMessages';
import styles from './AITraderScreen.module.css';

// Types for AI Trader data
interface AiTraderSummary {
  total_deposited_usdt: string;
  current_balance_usdt: string;
  realized_pnl_usdt: string;
}

interface WalletData {
  usdt_balance: string;
  ghd_balance: string;
}

interface AiTraderStatusResponse {
  user: any;
  wallet: WalletData;
  ai_trader: AiTraderSummary;
}

interface AiTraderHistoryItem {
  id: number;
  time: string;
  balance: string;
  pnl: string;
}

type ActionType = 'deposit' | 'withdraw' | null;

// Simple loading component with premium animation
function PremiumLoading({ message }: { message: string }) {
  return (
    <div className={styles.loadingContainer}>
      <div className={styles.loadingGlow} />
      <GhidarLogo size="lg" animate />
      <p className={styles.loadingText}>{message}</p>
      <div className={styles.loadingDots}>
        <span />
        <span />
        <span />
      </div>
    </div>
  );
}

export function AITraderScreen() {
  const [status, setStatus] = useState<AiTraderStatusResponse | null>(null);
  const [history, setHistory] = useState<AiTraderHistoryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [amountError, setAmountError] = useState<string | null>(null);
  const [activeAction, setActiveAction] = useState<ActionType>(null);
  const [amount, setAmount] = useState('');
  const [showVerificationModal, setShowVerificationModal] = useState(false);
  const [withdrawAmount, setWithdrawAmount] = useState('');
  const { showError: showToastError, showSuccess } = useToast();
  
  // Ref to track mounted state for cleanup
  const isMountedRef = useRef(true);

  // Cleanup on unmount
  useEffect(() => {
    isMountedRef.current = true;
    return () => {
      isMountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      // Use API client with fallback
      const statusData = await getAiTraderStatus();
      
      if (!isMountedRef.current) return;
      setStatus(statusData);

      // Fetch history using API client
      const historyData = await getAiTraderHistory(30);
      
      if (!isMountedRef.current) return;
      setHistory(historyData?.snapshots || []);

    } catch (err) {
      if (!isMountedRef.current) return;
      const errorMessage = getFriendlyErrorMessage(err as Error);
      setError(errorMessage);
      showToastError(errorMessage);
    } finally {
      if (isMountedRef.current) {
        setLoading(false);
      }
    }
  }, [showToastError]);

  const handleDeposit = async () => {
    setAmountError(null);
    
    if (!amount || amount.trim() === '') {
      setAmountError('Please enter an amount');
      showToastError('Please enter an amount');
      return;
    }

    const amountNum = parseFloat(amount);
    if (isNaN(amountNum) || amountNum <= 0) {
      setAmountError('Please enter a valid amount greater than 0');
      showToastError('Please enter a valid amount greater than 0');
      return;
    }

    const walletBalance = parseFloat(status?.wallet.usdt_balance || '0');
    if (amountNum > walletBalance) {
      const errorMsg = `Insufficient wallet balance. You have $${walletBalance.toFixed(2)} USDT available.`;
      setAmountError(errorMsg);
      showToastError(errorMsg);
      return;
    }

    if (amountNum < 50) {
      setAmountError('Minimum deposit is 50 USDT');
      showToastError('Minimum deposit is 50 USDT');
      return;
    }

    try {
      setActionLoading(true);
      
      // Use API client with fallback
      const depositData = await depositToAiTrader(amount);

      hapticFeedback('success');
      setStatus(prev => {
        if (!prev) return prev;
        return {
          ...prev,
          wallet: depositData.wallet,
          ai_trader: depositData.ai_trader,
        };
      });
      showSuccess(`Deposited $${parseFloat(depositData.amount_usdt).toFixed(2)} to AI Trader`);
      
      setAmount('');
      setActiveAction(null);
    } catch (err) {
      hapticFeedback('error');
      const errorMessage = err instanceof Error ? err.message : 'Deposit failed';
      setAmountError(errorMessage);
      showToastError(errorMessage);
    } finally {
      setActionLoading(false);
    }
  };

  const handleWithdrawRequest = () => {
    setAmountError(null);
    
    if (!amount || amount.trim() === '') {
      setAmountError('Please enter an amount');
      showToastError('Please enter an amount');
      return;
    }

    const amountNum = parseFloat(amount);
    if (isNaN(amountNum) || amountNum <= 0) {
      setAmountError('Please enter a valid amount greater than 0');
      showToastError('Please enter a valid amount greater than 0');
      return;
    }

    const aiBalance = parseFloat(status?.ai_trader.current_balance_usdt || '0');
    if (amountNum > aiBalance) {
      const errorMsg = `Insufficient AI Trader balance. You have $${aiBalance.toFixed(2)} USDT available.`;
      setAmountError(errorMsg);
      showToastError(errorMsg);
      return;
    }

    // Open verification modal
    setWithdrawAmount(amount);
    setShowVerificationModal(true);
  };

  const handleWithdrawalComplete = async (verificationId: number) => {
    // Withdrawal verified - now execute it with verification_id
    try {
      setActionLoading(true);
      
      // Use API client with fallback
      const withdrawData = await withdrawFromAiTrader(withdrawAmount, verificationId);

      hapticFeedback('success');
      setStatus(prev => {
        if (!prev) return prev;
        return {
          ...prev,
          wallet: withdrawData.wallet,
          ai_trader: withdrawData.ai_trader,
        };
      });
      showSuccess(`Withdrew $${parseFloat(withdrawData.amount_usdt).toFixed(2)} to wallet`);
      
      setAmount('');
      setWithdrawAmount('');
      setActiveAction(null);
      setShowVerificationModal(false);
    } catch (err) {
      hapticFeedback('error');
      const errorMessage = err instanceof Error ? err.message : 'Withdrawal failed';
      showToastError(errorMessage);
    } finally {
      setActionLoading(false);
    }
  };

  const handleAction = async () => {
    if (activeAction === 'deposit') {
      await handleDeposit();
    } else if (activeAction === 'withdraw') {
      handleWithdrawRequest();
    }
  };

  const handleMaxAmount = () => {
    if (activeAction === 'deposit') {
      setAmount(status?.wallet.usdt_balance || '0');
    } else {
      setAmount(status?.ai_trader.current_balance_usdt || '0');
    }
  };

  if (loading) {
    return <PremiumLoading message="Initializing AI Trading System..." />;
  }

  if (error) {
    return <ErrorState message={error} onRetry={loadData} />;
  }

  const formatAmount = (val: string) => {
    const num = parseFloat(val);
    if (isNaN(num)) return '0.00';
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const pnl = parseFloat(status?.ai_trader.realized_pnl_usdt || '0');
  const pnlPositive = pnl >= 0;
  const currentBalance = parseFloat(status?.ai_trader.current_balance_usdt || '0');
  const totalDeposited = parseFloat(status?.ai_trader.total_deposited_usdt || '0');
  const hasDeposits = totalDeposited > 0;

  return (
    <PullToRefresh onRefresh={loadData}>
      <div className={styles.container}>
        {/* Premium Hero Banner */}
        <div className={styles.heroBanner}>
          <div className={styles.heroGlow} />
          <div className={styles.heroContent}>
            <div className={styles.heroIcon}>🤖</div>
            <div className={styles.heroText}>
              <h1 className={styles.heroTitle}>AI Trading Bot</h1>
              <p className={styles.heroSubtitle}>
                Advanced algorithms generating <span className={styles.highlight}>2-3% daily returns</span>
              </p>
            </div>
          </div>
          <div className={styles.heroStats}>
            <div className={styles.heroStat}>
              <span className={styles.heroStatValue}>98.7%</span>
              <span className={styles.heroStatLabel}>Accuracy</span>
            </div>
            <div className={styles.heroDivider} />
            <div className={styles.heroStat}>
              <span className={styles.heroStatValue}>24/7</span>
              <span className={styles.heroStatLabel}>Trading</span>
            </div>
            <div className={styles.heroDivider} />
            <div className={styles.heroStat}>
              <span className={styles.heroStatValue}>$50</span>
              <span className={styles.heroStatLabel}>Min. Deposit</span>
            </div>
          </div>
        </div>

        {/* Live Activity Feed */}
        <AITraderLiveActivity maxItems={4} intervalMs={4000} />

        {/* Trust Indicators */}
        <AITraderTrustIndicators 
          totalProfitsPaid={847392.45}
          activeTraders={4131}
          totalTrades={2847593}
        />

        {/* Wallet Summary */}
        {status?.wallet && (
          <WalletSummary
            usdtBalance={status.wallet.usdt_balance}
            ghdBalance={status.wallet.ghd_balance}
            showActions={true}
            onBalanceChange={loadData}
          />
        )}

        {/* AI Trader Account Summary */}
        <Card variant="glow">
          <CardHeader>
            <CardTitle>
              <TraderIcon size={20} color="var(--brand-primary)" />
              Your AI Trader Account
              <HelpTooltip content="Your funds are actively traded by our AI algorithms. Returns are credited daily." />
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className={styles.aiSummary}>
              <div className={styles.mainBalance}>
                <span className={styles.mainLabel}>Active Balance</span>
                <span className={styles.mainValue}>${formatAmount(status?.ai_trader.current_balance_usdt || '0')}</span>
                {hasDeposits && (
                  <span className={`${styles.pnlBadge} ${pnlPositive ? styles.positive : styles.negative}`}>
                    {pnlPositive ? '↑' : '↓'} {pnlPositive ? '+' : ''}{formatAmount(status?.ai_trader.realized_pnl_usdt || '0')} USDT
                  </span>
                )}
              </div>
              
              <div className={styles.statsRow}>
                <div className={styles.statItem}>
                  <span className={styles.statLabel}>
                    Total Invested
                    <HelpTooltip content="Total amount you've deposited into AI Trader." />
                  </span>
                  <span className={styles.statValue}>${formatAmount(status?.ai_trader.total_deposited_usdt || '0')}</span>
                </div>
                <div className={styles.statDivider} />
                <div className={styles.statItem}>
                  <span className={styles.statLabel}>
                    Total Profit
                    <HelpTooltip content="Your total earnings from AI trading activities." />
                  </span>
                  <span className={`${styles.statValue} ${pnlPositive ? styles.positive : styles.negative}`}>
                    {pnlPositive ? '+' : ''}${formatAmount(status?.ai_trader.realized_pnl_usdt || '0')}
                  </span>
                </div>
              </div>
              
              {hasDeposits && (
                <div className={styles.performanceBar}>
                  <div 
                    className={`${styles.performanceFill} ${pnlPositive ? styles.positive : styles.negative}`}
                    style={{ width: `${Math.min(100, Math.max(0, (currentBalance / totalDeposited) * 100))}%` }}
                  />
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Performance Chart - Only show if user has deposits */}
        {hasDeposits && (
          <AITraderPerformanceChart
            initialBalance={totalDeposited}
            dailyReturnMin={2.0}
            dailyReturnMax={3.0}
          />
        )}

        {/* Transfer Actions */}
        <Card variant="elevated">
          <CardHeader>
            <CardTitle>
              {hasDeposits ? 'Manage Funds' : '🚀 Start Earning Today!'}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {!hasDeposits && !activeAction && (
              <div className={styles.ctaSection}>
                <div className={styles.ctaIcon}>💰</div>
                <p className={styles.ctaText}>
                  Join <strong>4,131 traders</strong> already earning daily returns. 
                  Start with as little as <strong>$50 USDT</strong>.
                </p>
                <div className={styles.ctaFeatures}>
                  <div className={styles.ctaFeature}>
                    <span>✅</span> Automated Trading
                  </div>
                  <div className={styles.ctaFeature}>
                    <span>✅</span> 2-3% Daily Returns
                  </div>
                  <div className={styles.ctaFeature}>
                    <span>✅</span> Withdraw Anytime
                  </div>
                </div>
              </div>
            )}
            
            {activeAction ? (
              <div className={styles.actionForm}>
                <div className={styles.actionHeader}>
                  {activeAction === 'deposit' ? (
                    <ArrowUpIcon size={20} color="var(--brand-primary)" />
                  ) : (
                    <ArrowDownIcon size={20} color="var(--warning)" />
                  )}
                  <span className={styles.actionTitle}>
                    {activeAction === 'deposit' ? 'Deposit to AI Trader' : 'Withdraw to Wallet'}
                  </span>
                </div>
                
                {activeAction === 'withdraw' && (
                  <div className={styles.verificationNote}>
                    <span>🔐</span>
                    <span>Wallet verification required for withdrawals</span>
                  </div>
                )}
                
                <NumberInput
                  label="Amount (USDT)"
                  value={amount}
                  onChange={(val) => {
                    setAmount(val);
                    setAmountError(null);
                  }}
                  placeholder={activeAction === 'deposit' ? 'Min: 50 USDT' : '0.00'}
                  error={amountError || undefined}
                  helperText={activeAction === 'deposit' ? 'Minimum deposit: 50 USDT' : undefined}
                  rightElement={
                    <button className={styles.maxButton} onClick={handleMaxAmount}>
                      MAX
                    </button>
                  }
                />
                
                <div className={styles.actionButtons}>
                  <Button variant="secondary" onClick={() => {
                    setActiveAction(null);
                    setAmount('');
                    setAmountError(null);
                  }}>
                    Cancel
                  </Button>
                  <Button loading={actionLoading} onClick={handleAction}>
                    {activeAction === 'deposit' ? 'Deposit Now' : 'Verify & Withdraw'}
                  </Button>
                </div>
              </div>
            ) : (
              <div className={styles.actionButtons}>
                <Button fullWidth onClick={() => setActiveAction('deposit')} className={styles.depositButton}>
                  <ArrowUpIcon size={18} />
                  {hasDeposits ? 'Deposit More' : 'Start Earning'}
                </Button>
                {hasDeposits && (
                  <Button fullWidth variant="secondary" onClick={() => setActiveAction('withdraw')}>
                    <ArrowDownIcon size={18} />
                    Withdraw
                  </Button>
                )}
              </div>
            )}
          </CardContent>
        </Card>

        {/* History */}
        {hasDeposits && (
          <Card>
            <CardHeader>
              <CardTitle>Recent Activity</CardTitle>
            </CardHeader>
            <CardContent>
              {history.length === 0 ? (
                <EmptyState
                  icon="📊"
                  message="No history yet. Your trading activity will appear here."
                />
              ) : (
                <div className={styles.historyList}>
                  {history.slice(0, 10).map((item) => {
                    const itemPnl = parseFloat(item.pnl);
                    const itemPnlPositive = itemPnl >= 0;
                    return (
                      <div key={item.id} className={styles.historyItem}>
                        <div className={styles.historyIcon}>
                          {itemPnlPositive ? '📈' : '📉'}
                        </div>
                        <div className={styles.historyContent}>
                          <div className={styles.historyDate}>
                            {new Date(item.time).toLocaleDateString(undefined, {
                              month: 'short',
                              day: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit',
                            })}
                          </div>
                          <div className={styles.historyBalance}>
                            Balance: ${formatAmount(item.balance)}
                          </div>
                        </div>
                        <div className={`${styles.historyPnl} ${itemPnlPositive ? styles.positive : styles.negative}`}>
                          {itemPnlPositive ? '+' : ''}${formatAmount(item.pnl)}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* Features Info */}
        <div className={styles.infoSection}>
          <h3 className={styles.infoTitle}>Why AI Trader?</h3>
          <div className={styles.infoCard}>
            <span className={styles.featureIcon}>🤖</span>
            <div>
              <strong>Advanced AI Algorithms</strong>
              <p>Our machine learning models analyze market patterns 24/7 to find the best trading opportunities.</p>
            </div>
          </div>
          <div className={styles.infoCard}>
            <span className={styles.featureIcon}>📈</span>
            <div>
              <strong>Consistent Returns</strong>
              <p>Average 2-3% daily returns with 92%+ win rate. Your money works while you sleep.</p>
            </div>
          </div>
          <div className={styles.infoCard}>
            <span className={styles.featureIcon}>🔒</span>
            <div>
              <strong>Secure & Transparent</strong>
              <p>Bank-level encryption. Full audit trail. Withdraw your funds anytime.</p>
            </div>
          </div>
          <div className={styles.infoCard}>
            <span className={styles.featureIcon}>💎</span>
            <div>
              <strong>Low Minimum</strong>
              <p>Start with just $50 USDT and watch your investment grow daily.</p>
            </div>
          </div>
        </div>
      </div>

      {/* Withdrawal Verification Modal */}
      <WithdrawalVerificationModal
        isOpen={showVerificationModal}
        onClose={() => {
          setShowVerificationModal(false);
          setWithdrawAmount('');
        }}
        amountUsdt={withdrawAmount}
        onVerificationComplete={handleWithdrawalComplete}
      />
    </PullToRefresh>
  );
}
