import { useEffect, useState, memo } from 'react';
import { Card, CardContent, ErrorState, useToast, PullToRefresh } from '../components/ui';
import { WalletSummary } from '../components/WalletSummary';
import { LotteryIcon, AirdropIcon, TraderIcon, ReferralIcon, ChevronRightIcon } from '../components/Icons';
import { TrustBadgeBar } from '../components/TrustBadgeBar';
import { StatisticsBanner } from '../components/StatisticsBanner';
import { TelegramBranding } from '../components/TelegramBranding';
import { LiveActivityFeed } from '../components/LiveActivityFeed';
import { LiveUserCounter } from '../components/LiveUserCounter';
import { MeResponse, getMe } from '../api/client';
import { getUserInfo } from '../lib/telegram';
import { TabId } from '../components/ui/NavTabs';
import { GhidarLogo } from '../components/GhidarLogo';
import styles from './HomeScreen.module.css';

interface HomeScreenProps {
  onNavigate: (tab: TabId) => void;
}

interface FeatureCardProps {
  icon: React.ReactNode;
  title: string;
  description: string;
  badge?: 'Popular' | 'Pro' | string;
  status?: string;
  onClick: () => void;
}

/**
 * Calculate user tier based on account data
 */
function calculateUserTier(data: MeResponse | null): string {
  if (!data) return 'Level 1';
  
  const usdtBalance = parseFloat(data.wallet.usdt_balance) || 0;
  const ghdBalance = parseFloat(data.wallet.ghd_balance) || 0;
  
  // Tier logic based on balances and activity
  if (usdtBalance >= 1000 || ghdBalance >= 100000) return 'Level 5 - Diamond';
  if (usdtBalance >= 500 || ghdBalance >= 50000) return 'Level 4 - Platinum';
  if (usdtBalance >= 100 || ghdBalance >= 10000) return 'Level 3 - Gold';
  if (usdtBalance >= 10 || ghdBalance >= 1000) return 'Level 2 - Silver';
  return 'Level 1 - Bronze';
}

/**
 * Memoized Feature Card component for performance
 */
const FeatureCard = memo(function FeatureCard({ 
  icon, 
  title, 
  description, 
  badge,
  status,
  onClick 
}: FeatureCardProps) {
  // Determine badge style class based on badge type
  const getBadgeClass = () => {
    if (!badge) return '';
    if (badge === 'Popular') return styles.featureBadgePopular;
    if (badge === 'Pro') return styles.featureBadgePro;
    return styles.featureBadgePopular; // Default
  };

  return (
    <Card 
      variant="elevated" 
      className={styles.featureCard} 
      onClick={onClick}
      padding="none"
    >
      <CardContent className={styles.featureContent}>
        <div className={styles.featureIcon}>{icon}</div>
        <div className={styles.featureInfo}>
          <div className={styles.featureTitleRow}>
            <h3 className={styles.featureTitle}>{title}</h3>
            {badge && (
              <span className={`${styles.featureBadge} ${getBadgeClass()}`}>
                {badge}
              </span>
            )}
          </div>
          <p className={styles.featureDescription}>{description}</p>
          {status && (
            <div className={styles.featureStatus}>
              <span className={styles.featureStatusDot} />
              <span>{status}</span>
            </div>
          )}
        </div>
        <div className={styles.featureArrow}>
          <ChevronRightIcon size={20} />
        </div>
      </CardContent>
    </Card>
  );
});

/**
 * Loading component with proper styling
 */
function LoadingState({ message }: { message: string }) {
  return (
    <div className={styles.loadingContainer}>
      <GhidarLogo size="lg" animate />
      <p className={styles.loadingMessage}>{message}</p>
    </div>
  );
}

/**
 * Wallet skeleton loading state
 */
function WalletSkeleton() {
  return (
    <div className={styles.walletSkeleton}>
      <div className={styles.walletSkeletonContent}>
        <div className={styles.walletSkeletonBalance}>
          <div className={styles.walletSkeletonLabel} />
          <div className={styles.walletSkeletonValue} />
        </div>
        <div className={styles.walletSkeletonDivider} />
        <div className={styles.walletSkeletonBalance}>
          <div className={styles.walletSkeletonLabel} />
          <div className={styles.walletSkeletonValue} />
        </div>
      </div>
      <div className={styles.walletSkeletonActions}>
        <div className={styles.walletSkeletonButton} />
        <div className={styles.walletSkeletonButton} />
      </div>
    </div>
  );
}

export function HomeScreen({ onNavigate }: HomeScreenProps) {
  const [data, setData] = useState<MeResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [errorDetails, setErrorDetails] = useState<string>('');
  const { showError: showToastError } = useToast();

  const telegramUser = getUserInfo();

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    console.log('[HomeScreen] loadData called');
    
    try {
      setLoading(true);
      setError(null);
      setErrorDetails('');
      
      // Use the API client with fallback support
      const meData = await getMe();
      console.log('[HomeScreen] /me/ response:', meData);
      
      setData(meData);
      
    } catch (err) {
      console.error('[HomeScreen] loadData error:', err);
      const errMsg = err instanceof Error ? err.message : 'Network error';
      setError(errMsg);
      setErrorDetails(String(err));
      showToastError(errMsg);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <LoadingState message="Loading your wallet..." />;
  }

  if (error) {
    return (
      <div className={styles.errorContainer}>
        <ErrorState message={error} onRetry={loadData} />
        {errorDetails && (
          <div className={styles.errorDebug}>
            <span className={styles.errorDebugTitle}>Debug Info:</span>
            {errorDetails}
          </div>
        )}
      </div>
    );
  }

  const userTier = calculateUserTier(data);
  
  // Format member since date
  const memberSince = data?.user.joining_date
    ? new Date(data.user.joining_date * 1000).toLocaleDateString(undefined, { 
        month: 'short', 
        year: 'numeric' 
      })
    : 'Today';

  return (
    <PullToRefresh onRefresh={loadData}>
      <div className={styles.container}>
        {/* Trust Badge Bar */}
        <section className={styles.trustSection}>
          <TrustBadgeBar variant="compact" showLabels={true} />
        </section>

        {/* Wallet Summary */}
        <section className={styles.walletSection}>
          {data ? (
            <WalletSummary
              usdtBalance={data.wallet.usdt_balance}
              ghdBalance={data.wallet.ghd_balance}
              onBalanceChange={loadData}
            />
          ) : (
            <WalletSkeleton />
          )}
        </section>

        {/* Feature Cards */}
        <section className={styles.features}>
          <h2 className={styles.sectionTitle}>Explore Features</h2>
          
          <div className={styles.featureGrid}>
            <FeatureCard
              icon={<AirdropIcon size={24} color="var(--brand-primary)" />}
              title="GHD Airdrop"
              description="Mine GHD tokens by tapping and convert to USDT"
              badge="Popular"
              status={data ? `${parseFloat(data.wallet.ghd_balance).toLocaleString()} GHD` : undefined}
              onClick={() => onNavigate('airdrop')}
            />

            <FeatureCard
              icon={<LotteryIcon size={24} color="var(--brand-gold)" />}
              title="Lottery"
              description="Buy tickets and win big prizes in our draws"
              status="Active draw"
              onClick={() => onNavigate('lottery')}
            />

            <FeatureCard
              icon={<TraderIcon size={24} color="var(--info)" />}
              title="AI Trader"
              description="Let our AI trade for you and earn passive income"
              badge="Pro"
              onClick={() => onNavigate('trader')}
            />

            <FeatureCard
              icon={<ReferralIcon size={24} color="var(--success)" />}
              title="Referral Program"
              description="Invite friends and earn USDT commissions"
              onClick={() => onNavigate('referral')}
            />
          </div>
        </section>

        {/* Platform Statistics */}
        <section className={styles.statsSection}>
          <StatisticsBanner />
        </section>

        {/* Live Activity Feed - Social Proof */}
        <section className={styles.activitySection}>
          <LiveActivityFeed maxItems={5} intervalMs={4000} variant="default" />
        </section>

        {/* User Stats with Live Counter */}
        <section className={styles.userStatsSection}>
          <div className={styles.liveCounterWrapper}>
            <LiveUserCounter variant="default" />
          </div>
          <div className={styles.statsGrid}>
            <div className={styles.stat}>
              <span className={styles.statValue}>{memberSince}</span>
              <span className={styles.statLabel}>Member Since</span>
            </div>
            <div className={styles.statDivider} />
            <div className={styles.stat}>
              <span className={styles.statValue}>{userTier}</span>
              <span className={styles.statLabel}>Account Tier</span>
            </div>
          </div>
        </section>
      </div>
    </PullToRefresh>
  );
}
