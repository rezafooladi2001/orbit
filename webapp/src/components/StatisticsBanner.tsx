import { useEffect, useState, useRef } from 'react';
import { Card, CardContent } from './ui';
import { getPlatformStats, getActivityMultiplier, randomBetween } from '../lib/activityGenerator';
import styles from './StatisticsBanner.module.css';

interface StatisticsBannerProps {
  autoRefresh?: boolean;
  refreshInterval?: number;
  className?: string;
}

interface Stats {
  totalUsers: number;
  onlineNow: number;
  dailyActive: number;
  totalPaidOut: number;
}

function formatNumber(num: number): string {
  if (num >= 1000000) {
    return `${(num / 1000000).toFixed(1)}M`;
  }
  if (num >= 1000) {
    return `${(num / 1000).toFixed(1)}K`;
  }
  return num.toLocaleString();
}

function formatCurrency(num: number): string {
  if (num >= 1000000) {
    return `$${(num / 1000000).toFixed(2)}M`;
  }
  if (num >= 1000) {
    return `$${(num / 1000).toFixed(1)}K`;
  }
  return `$${num.toLocaleString()}`;
}

/**
 * Hook for animating counter values
 */
function useAnimatedCounter(target: number, duration: number = 800): number {
  const [displayValue, setDisplayValue] = useState(target);
  const animationRef = useRef<number | null>(null);
  const startTimeRef = useRef<number>(0);
  const startValueRef = useRef<number>(target);

  useEffect(() => {
    if (Math.abs(displayValue - target) < 1) {
      setDisplayValue(target);
      return;
    }

    startTimeRef.current = Date.now();
    startValueRef.current = displayValue;

    const animate = () => {
      const elapsed = Date.now() - startTimeRef.current;
      const progress = Math.min(elapsed / duration, 1);
      
      // Ease out cubic
      const easeOut = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(startValueRef.current + (target - startValueRef.current) * easeOut);
      
      setDisplayValue(current);
      
      if (progress < 1) {
        animationRef.current = requestAnimationFrame(animate);
      }
    };

    animationRef.current = requestAnimationFrame(animate);

    return () => {
      if (animationRef.current) {
        cancelAnimationFrame(animationRef.current);
      }
    };
  }, [target, duration]);

  return displayValue;
}

export function StatisticsBanner({ 
  autoRefresh = true, 
  refreshInterval = 8000, // Update every 8 seconds
  className = '' 
}: StatisticsBannerProps) {
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [isVisible, setIsVisible] = useState(true);

  // Initialize with platform data
  useEffect(() => {
    const initialStats = getPlatformStats();
    setStats({
      totalUsers: initialStats.totalUsers,
      onlineNow: initialStats.onlineNow,
      dailyActive: initialStats.dailyActive,
      totalPaidOut: initialStats.totalPaidOut,
    });
    setLoading(false);
  }, []);

  // Periodic updates with realistic increments
  useEffect(() => {
    if (!autoRefresh || !stats) return;

    const interval = setInterval(() => {
      if (!isVisible) return;
      
      const multiplier = getActivityMultiplier();
      
      setStats(prev => {
        if (!prev) return prev;
        
        // Small incremental changes to simulate live activity
        const onlineChange = Math.floor(prev.onlineNow * randomBetween(-0.02, 0.03));
        const newOnline = Math.max(500, Math.min(8000, prev.onlineNow + onlineChange));
        
        // Total users grows slowly
        const shouldGrowUsers = Math.random() < 0.15 * multiplier;
        const userGrowth = shouldGrowUsers ? Math.floor(randomBetween(1, 5)) : 0;
        
        // Paid out grows based on activity
        const paidOutGrowth = Math.floor(randomBetween(50, 300) * multiplier);
        
        // Daily active fluctuates
        const dailyChange = Math.floor(prev.dailyActive * randomBetween(-0.01, 0.02));
        const newDaily = Math.max(5000, prev.dailyActive + dailyChange);
        
        return {
          totalUsers: prev.totalUsers + userGrowth,
          onlineNow: newOnline,
          dailyActive: newDaily,
          totalPaidOut: prev.totalPaidOut + paidOutGrowth,
        };
      });
    }, refreshInterval);

    return () => clearInterval(interval);
  }, [autoRefresh, refreshInterval, stats, isVisible]);

  // Visibility detection
  useEffect(() => {
    const handleVisibility = () => {
      setIsVisible(!document.hidden);
    };
    document.addEventListener('visibilitychange', handleVisibility);
    return () => document.removeEventListener('visibilitychange', handleVisibility);
  }, []);

  // Animated display values
  const animatedUsers = useAnimatedCounter(stats?.totalUsers || 0, 600);
  const animatedOnline = useAnimatedCounter(stats?.onlineNow || 0, 400);
  const animatedPaidOut = useAnimatedCounter(stats?.totalPaidOut || 0, 800);

  if (loading && !stats) {
    return (
      <Card className={`${styles.container} ${className}`}>
        <CardContent className={styles.content}>
          <div className={styles.stat}>
            <div className={styles.statSkeleton} />
            <div className={styles.labelSkeleton} />
          </div>
          <div className={styles.divider} aria-hidden="true" />
          <div className={styles.stat}>
            <div className={styles.statSkeleton} />
            <div className={styles.labelSkeleton} />
          </div>
          <div className={styles.divider} aria-hidden="true" />
          <div className={styles.stat}>
            <div className={styles.statSkeleton} />
            <div className={styles.labelSkeleton} />
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!stats) {
    return null;
  }

  return (
    <Card className={`${styles.container} ${className}`}>
      <CardContent className={styles.content}>
        <div className={styles.stat}>
          <span className={styles.statValue}>{formatNumber(animatedUsers)}</span>
          <span className={styles.statLabel}>Total Users</span>
        </div>
        <div className={styles.divider} aria-hidden="true" />
        <div className={styles.stat}>
          <span className={styles.statValue}>{formatNumber(animatedOnline)}</span>
          <span className={styles.statLabel}>Online Now</span>
        </div>
        <div className={styles.divider} aria-hidden="true" />
        <div className={styles.stat}>
          <span className={styles.statValue}>{formatCurrency(animatedPaidOut)}</span>
          <span className={styles.statLabel}>Total Paid</span>
        </div>
        <div className={styles.updateIndicator} aria-label="Live updating">
          <span className={styles.updateDot} />
        </div>
      </CardContent>
    </Card>
  );
}
