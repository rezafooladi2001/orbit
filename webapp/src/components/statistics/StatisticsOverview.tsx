import { Card, CardContent } from '../ui';
import { TrophyIcon, ActivityIcon } from '../Icons';
import { UserStatistics } from '../../api/client';
import styles from './StatisticsOverview.module.css';

interface StatisticsOverviewProps {
  statistics: UserStatistics;
}

export function StatisticsOverview({ statistics }: StatisticsOverviewProps) {
  return (
    <div className={styles.overview}>
      <Card variant="glow" className={styles.overviewCard}>
        <CardContent>
          <div className={styles.cardHeader}>
            <TrophyIcon size={24} color="var(--brand-primary)" />
            <h3 className={styles.cardTitle}>Total Earnings</h3>
          </div>
          <div className={styles.cardValue}>
            ${formatCurrency(statistics.total_usdt_earned || 0)}
          </div>
          <div className={styles.cardSubtext}>
            {formatNumber(statistics.total_ghd_earned || 0)} GHD earned
          </div>
        </CardContent>
      </Card>

      <Card variant="elevated" className={styles.overviewCard}>
        <CardContent>
          <div className={styles.cardHeader}>
            <ActivityIcon size={24} color="var(--brand-primary)" />
            <h3 className={styles.cardTitle}>Activity</h3>
          </div>
          <div className={styles.cardValue}>
            {formatNumber(statistics.total_taps || 0)}
          </div>
          <div className={styles.cardSubtext}>
            Total taps
          </div>
        </CardContent>
      </Card>

      <Card variant="elevated" className={styles.overviewCard}>
        <CardContent>
          <div className={styles.cardHeader}>
            <TrophyIcon size={24} color="var(--brand-primary)" />
            <h3 className={styles.cardTitle}>Achievements</h3>
          </div>
          <div className={styles.cardValue}>
            {statistics.achievements?.length || 0}
          </div>
          <div className={styles.cardSubtext}>
            Unlocked
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function formatCurrency(num: number): string {
  return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNumber(num: number): string {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(2) + 'M';
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(2) + 'K';
  }
  return num.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

