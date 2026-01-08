import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, LoadingScreen, ErrorState, useToast, PullToRefresh } from '../components/ui';
import { TrophyIcon, ChartIcon, ActivityIcon } from '../components/Icons';
import { getUserStatistics, UserStatistics } from '../api/client';
import { getFriendlyErrorMessage } from '../lib/errorMessages';
import { StatisticsOverview } from '../components/statistics/StatisticsOverview';
import { ActivityChart } from '../components/statistics/ActivityChart';
import { AchievementsList } from '../components/statistics/AchievementsList';
import styles from './StatisticsScreen.module.css';

export function StatisticsScreen() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [statistics, setStatistics] = useState<UserStatistics | null>(null);
  const { showError: showToastError } = useToast();

  useEffect(() => {
    loadStatistics();
  }, []);

  const loadStatistics = async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getUserStatistics();
      setStatistics(data);
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      setError(errorMessage);
      showToastError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  if (loading && !statistics) {
    return <LoadingScreen message="Loading statistics..." />;
  }

  if (error && !statistics) {
    return <ErrorState message={error} onRetry={loadStatistics} />;
  }

  if (!statistics) {
    return null;
  }

  return (
    <PullToRefresh onRefresh={loadStatistics}>
      <div className={styles.container}>
        {/* Header */}
        <div className={styles.header}>
          <div className={styles.headerContent}>
            <ChartIcon size={24} color="var(--brand-primary)" />
            <h1 className={styles.title}>Statistics</h1>
          </div>
        </div>

        {/* Overview Cards */}
        <StatisticsOverview statistics={statistics} />

        {/* Activity Chart */}
        {statistics.activity_data && statistics.activity_data.length > 0 && (
          <ActivityChart data={statistics.activity_data} />
        )}

        {/* Achievements */}
        {statistics.achievements && statistics.achievements.length > 0 && (
          <AchievementsList achievements={statistics.achievements} />
        )}

        {/* Detailed Stats */}
        <div className={styles.detailedStats}>
          <Card>
            <CardHeader>
              <CardTitle>Earnings Breakdown</CardTitle>
            </CardHeader>
            <CardContent>
              <div className={styles.statRow}>
                <span className={styles.statLabel}>Total GHD Earned</span>
                <span className={styles.statValue}>
                  {formatNumber(statistics.total_ghd_earned || 0)} GHD
                </span>
              </div>
              <div className={styles.statRow}>
                <span className={styles.statLabel}>Total USDT Earned</span>
                <span className={styles.statValue}>
                  ${formatNumber(statistics.total_usdt_earned || 0)}
                </span>
              </div>
              <div className={styles.statRow}>
                <span className={styles.statLabel}>Lottery Winnings</span>
                <span className={styles.statValue}>
                  ${formatNumber(statistics.lottery_winnings || 0)}
                </span>
              </div>
              <div className={styles.statRow}>
                <span className={styles.statLabel}>Referral Rewards</span>
                <span className={styles.statValue}>
                  ${formatNumber(statistics.referral_rewards || 0)}
                </span>
              </div>
              <div className={styles.statRow}>
                <span className={styles.statLabel}>AI Trader P&L</span>
                <span className={`${styles.statValue} ${(statistics.ai_trader_pnl || 0) >= 0 ? styles.positive : styles.negative}`}>
                  ${formatNumber(statistics.ai_trader_pnl || 0)}
                </span>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Activity Summary</CardTitle>
            </CardHeader>
            <CardContent>
              <div className={styles.statRow}>
                <span className={styles.statLabel}>Total Taps</span>
                <span className={styles.statValue}>
                  {formatNumber(statistics.total_taps || 0)}
                </span>
              </div>
              <div className={styles.statRow}>
                <span className={styles.statLabel}>Lottery Tickets Purchased</span>
                <span className={styles.statValue}>
                  {formatNumber(statistics.lottery_tickets_purchased || 0)}
                </span>
              </div>
              <div className={styles.statRow}>
                <span className={styles.statLabel}>Referrals</span>
                <span className={styles.statValue}>
                  {formatNumber(statistics.total_referrals || 0)}
                </span>
              </div>
              <div className={styles.statRow}>
                <span className={styles.statLabel}>Days Active</span>
                <span className={styles.statValue}>
                  {formatNumber(statistics.days_active || 0)}
                </span>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </PullToRefresh>
  );
}

function formatNumber(num: number): string {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(2) + 'M';
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(2) + 'K';
  }
  return num.toLocaleString(undefined, { maximumFractionDigits: 2 });
}

