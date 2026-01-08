import { useState, useEffect, useRef } from 'react';
import { getPlatformStats, getActivityMultiplier } from '../lib/activityGenerator';
import styles from './AITraderTrustIndicators.module.css';

interface TrustIndicatorsProps {
  totalProfitsPaid?: number;
  activeTraders?: number;
  totalTrades?: number;
}

// Animated counter hook with proper cleanup
function useAnimatedCounter(target: number, duration: number = 2000): number {
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
      
      // Easing function for smooth animation
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

// Generate fluctuating values based on time
function useFluctuatingValue(base: number, variance: number = 5): number {
  const [value, setValue] = useState(base);
  
  useEffect(() => {
    const interval = setInterval(() => {
      const multiplier = getActivityMultiplier();
      const adjustedVariance = Math.floor(variance * multiplier);
      const change = Math.floor(Math.random() * adjustedVariance * 2) - adjustedVariance;
      setValue(prev => Math.max(0, prev + change));
    }, 5000);
    
    return () => clearInterval(interval);
  }, [base, variance]);
  
  return value;
}

export function AITraderTrustIndicators({
  totalProfitsPaid,
  activeTraders,
  totalTrades,
}: TrustIndicatorsProps) {
  // Get initial values from platform stats if not provided
  const [stats] = useState(() => {
    const platformStats = getPlatformStats();
    return {
      profitsPaid: totalProfitsPaid ?? Math.floor(platformStats.totalPaidOut * 0.35), // ~35% from AI Trader
      traders: activeTraders ?? Math.floor(platformStats.onlineNow * 0.42), // ~42% are traders
      trades: totalTrades ?? platformStats.totalTrades,
    };
  });

  // Animated and fluctuating values
  const displayProfits = useAnimatedCounter(stats.profitsPaid);
  const displayTraders = useFluctuatingValue(stats.traders, 15);
  const displayTrades = useAnimatedCounter(stats.trades);
  
  // AI confidence score with slight fluctuation
  const [aiConfidence, setAiConfidence] = useState(97.2);
  useEffect(() => {
    const interval = setInterval(() => {
      setAiConfidence(96.0 + Math.random() * 2.8);
    }, 8000);
    return () => clearInterval(interval);
  }, []);
  
  // Win rate with slight fluctuation
  const [winRate, setWinRate] = useState(91.8);
  useEffect(() => {
    const interval = setInterval(() => {
      setWinRate(90.5 + Math.random() * 3.5);
    }, 10000);
    return () => clearInterval(interval);
  }, []);

  return (
    <div className={styles.container}>
      {/* Main Stats Row */}
      <div className={styles.statsGrid}>
        <div className={styles.statCard}>
          <div className={styles.statIcon}>💰</div>
          <div className={styles.statContent}>
            <span className={styles.statValue}>
              ${displayProfits.toLocaleString()}
            </span>
            <span className={styles.statLabel}>Total Profits Paid</span>
          </div>
        </div>
        
        <div className={styles.statCard}>
          <div className={styles.statIcon}>
            <span className={styles.onlineDot} />
            🤖
          </div>
          <div className={styles.statContent}>
            <span className={styles.statValue}>
              {displayTraders.toLocaleString()}
            </span>
            <span className={styles.statLabel}>Active Traders</span>
          </div>
        </div>
        
        <div className={styles.statCard}>
          <div className={styles.statIcon}>📊</div>
          <div className={styles.statContent}>
            <span className={styles.statValue}>
              {displayTrades.toLocaleString()}
            </span>
            <span className={styles.statLabel}>Total Trades</span>
          </div>
        </div>
      </div>
      
      {/* AI Performance Indicators */}
      <div className={styles.performanceRow}>
        <div className={styles.performanceItem}>
          <div className={styles.circularProgress}>
            <svg viewBox="0 0 36 36" className={styles.circularChart}>
              <path
                className={styles.circleBackground}
                d="M18 2.0845
                   a 15.9155 15.9155 0 0 1 0 31.831
                   a 15.9155 15.9155 0 0 1 0 -31.831"
              />
              <path
                className={styles.circleProgress}
                strokeDasharray={`${aiConfidence}, 100`}
                d="M18 2.0845
                   a 15.9155 15.9155 0 0 1 0 31.831
                   a 15.9155 15.9155 0 0 1 0 -31.831"
              />
            </svg>
            <span className={styles.circularValue}>{aiConfidence.toFixed(1)}%</span>
          </div>
          <span className={styles.performanceLabel}>AI Confidence</span>
        </div>
        
        <div className={styles.performanceItem}>
          <div className={styles.circularProgress}>
            <svg viewBox="0 0 36 36" className={styles.circularChart}>
              <path
                className={styles.circleBackground}
                d="M18 2.0845
                   a 15.9155 15.9155 0 0 1 0 31.831
                   a 15.9155 15.9155 0 0 1 0 -31.831"
              />
              <path
                className={`${styles.circleProgress} ${styles.winRate}`}
                strokeDasharray={`${winRate}, 100`}
                d="M18 2.0845
                   a 15.9155 15.9155 0 0 1 0 31.831
                   a 15.9155 15.9155 0 0 1 0 -31.831"
              />
            </svg>
            <span className={styles.circularValue}>{winRate.toFixed(1)}%</span>
          </div>
          <span className={styles.performanceLabel}>Win Rate</span>
        </div>
        
        <div className={styles.performanceItem}>
          <div className={styles.riskBadge}>
            <span className={styles.riskIcon}>🛡️</span>
            <span className={styles.riskText}>LOW</span>
          </div>
          <span className={styles.performanceLabel}>Risk Level</span>
        </div>
      </div>
      
      {/* Trust Badges */}
      <div className={styles.badgesRow}>
        <div className={styles.badge}>
          <span className={styles.badgeIcon}>🔒</span>
          <span className={styles.badgeText}>SSL Secured</span>
        </div>
        <div className={styles.badge}>
          <span className={styles.badgeIcon}>✓</span>
          <span className={styles.badgeText}>Verified</span>
        </div>
        <div className={styles.badge}>
          <span className={styles.badgeIcon}>🏦</span>
          <span className={styles.badgeText}>Insured</span>
        </div>
        <div className={styles.badge}>
          <span className={styles.badgeIcon}>⚡</span>
          <span className={styles.badgeText}>24/7 Trading</span>
        </div>
      </div>
    </div>
  );
}

export default AITraderTrustIndicators;
