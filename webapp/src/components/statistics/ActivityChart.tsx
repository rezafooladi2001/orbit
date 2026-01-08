import { Card, CardContent, CardHeader, CardTitle } from '../ui';
import styles from './ActivityChart.module.css';

interface ActivityDataPoint {
  date: string;
  taps: number;
  earnings: number;
}

interface ActivityChartProps {
  data: ActivityDataPoint[];
}

export function ActivityChart({ data }: ActivityChartProps) {
  const maxValue = Math.max(...data.map(d => Math.max(d.taps, d.earnings)), 1);

  return (
    <Card variant="elevated">
      <CardHeader>
        <CardTitle>Activity Over Time</CardTitle>
      </CardHeader>
      <CardContent>
        <div className={styles.chart}>
          <div className={styles.chartBars}>
            {data.map((point, index) => {
              const tapHeight = (point.taps / maxValue) * 100;
              const earningsHeight = (point.earnings / maxValue) * 100;
              const date = new Date(point.date);
              const dayLabel = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

              return (
                <div key={index} className={styles.barGroup}>
                  <div className={styles.bars}>
                    <div
                      className={`${styles.bar} ${styles.tapBar}`}
                      style={{ height: `${tapHeight}%` }}
                      title={`${point.taps} taps`}
                    />
                    <div
                      className={`${styles.bar} ${styles.earningsBar}`}
                      style={{ height: `${earningsHeight}%` }}
                      title={`$${point.earnings.toFixed(2)}`}
                    />
                  </div>
                  <span className={styles.barLabel}>{dayLabel}</span>
                </div>
              );
            })}
          </div>
          <div className={styles.legend}>
            <div className={styles.legendItem}>
              <div className={`${styles.legendColor} ${styles.tapColor}`} />
              <span>Taps</span>
            </div>
            <div className={styles.legendItem}>
              <div className={`${styles.legendColor} ${styles.earningsColor}`} />
              <span>Earnings</span>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

