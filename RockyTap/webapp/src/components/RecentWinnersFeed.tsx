import { useState, useEffect, useCallback, useRef } from 'react';
import {
  generateRecentLotteryWinners,
  LotteryWinner,
  randomItem,
  FIRST_NAMES,
  randomBetween,
  randomIntBetween,
  getActivityMultiplier,
} from '../lib/activityGenerator';
import styles from './RecentWinnersFeed.module.css';

interface RecentWinnersFeedProps {
  maxItems?: number;
  updateInterval?: number;
  variant?: 'default' | 'compact' | 'ticker';
  showHeader?: boolean;
  className?: string;
}

/**
 * Custom hook for IntersectionObserver-based visibility detection
 * More efficient than document.hidden for component-level visibility
 */
function useIntersectionObserver(
  ref: React.RefObject<Element>,
  options?: IntersectionObserverInit
): boolean {
  const [isIntersecting, setIsIntersecting] = useState(false);

  useEffect(() => {
    const element = ref.current;
    if (!element) return;

    // Fallback for browsers without IntersectionObserver
    if (!('IntersectionObserver' in window)) {
      setIsIntersecting(true);
      return;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        setIsIntersecting(entry.isIntersecting);
      },
      { threshold: 0.1, ...options }
    );

    observer.observe(element);

    return () => {
      observer.disconnect();
    };
  }, [ref, options]);

  return isIntersecting;
}

/**
 * Generate a new random winner
 */
function generateNewWinner(): LotteryWinner {
  const name = randomItem(FIRST_NAMES);
  // Use randomIntBetween for rank (must be an integer)
  const rank = Math.random() < 0.1 ? 1 : Math.random() < 0.25 ? randomIntBetween(2, 3) : randomIntBetween(4, 50);
  
  let prizeAmount: number;
  if (rank === 1) {
    prizeAmount = randomBetween(800, 3500);
  } else if (rank <= 3) {
    prizeAmount = randomBetween(200, 800);
  } else if (rank <= 10) {
    prizeAmount = randomBetween(50, 200);
  } else {
    prizeAmount = randomBetween(5, 50);
  }
  
  const lotteryNames = ['Weekly Grand Lottery', 'Daily Mini Draw', 'Flash Lottery', 'Weekend Special'];
  
  return {
    id: Date.now() + Math.floor(Math.random() * 10000),
    userName: name,
    maskedName: name.slice(0, 3) + '***',
    prizeAmount: Math.round(prizeAmount * 100) / 100,
    rank: Math.floor(rank),
    lotteryName: rank === 1 ? 'Weekly Grand Lottery' : randomItem(lotteryNames),
    wonAt: 'just now',
  };
}

/**
 * Get rank display
 */
function getRankDisplay(rank: number): { emoji: string; className: string } {
  switch (rank) {
    case 1:
      return { emoji: '🥇', className: styles.rank1 };
    case 2:
      return { emoji: '🥈', className: styles.rank2 };
    case 3:
      return { emoji: '🥉', className: styles.rank3 };
    default:
      return { emoji: `#${rank}`, className: styles.rankOther };
  }
}

// Reduced update interval for better performance
const DEFAULT_UPDATE_INTERVAL = 18000; // 18 seconds (increased from 12s)

export function RecentWinnersFeed({
  maxItems = 5,
  updateInterval = DEFAULT_UPDATE_INTERVAL,
  variant = 'default',
  showHeader = true,
  className = '',
}: RecentWinnersFeedProps) {
  const [winners, setWinners] = useState<LotteryWinner[]>([]);
  const [isPageVisible, setIsPageVisible] = useState(true);
  const containerRef = useRef<HTMLDivElement>(null);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  
  // Use IntersectionObserver for component visibility
  const isComponentVisible = useIntersectionObserver(containerRef);
  
  // Combined visibility check: page visible AND component in viewport
  const shouldUpdate = isPageVisible && isComponentVisible;

  // Initialize with some winners
  useEffect(() => {
    const initial = generateRecentLotteryWinners(maxItems);
    setWinners(initial);
  }, [maxItems]);

  // Add new winners periodically - only when visible
  const addWinner = useCallback(() => {
    if (!shouldUpdate) return;
    
    // Only add if activity multiplier says so (less frequent during off-hours)
    const multiplier = getActivityMultiplier();
    if (Math.random() > multiplier * 0.7) return; // Slightly less frequent
    
    const newWinner = generateNewWinner();
    setWinners(prev => {
      // Update time labels
      const updated = prev.map((w, idx) => ({
        ...w,
        wonAt: idx === 0 ? '1m ago' : 
               idx === 1 ? '3m ago' : 
               idx === 2 ? '8m ago' : 
               idx === 3 ? '15m ago' : 
               '30m ago',
      }));
      return [newWinner, ...updated].slice(0, maxItems);
    });
  }, [shouldUpdate, maxItems]);

  // Manage interval based on visibility
  useEffect(() => {
    // Clear existing interval
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    
    // Only start interval when visible
    if (shouldUpdate) {
      intervalRef.current = setInterval(addWinner, updateInterval);
    }
    
    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, [shouldUpdate, addWinner, updateInterval]);

  // Page visibility detection (for background tab)
  useEffect(() => {
    const handleVisibility = () => {
      setIsPageVisible(!document.hidden);
    };
    document.addEventListener('visibilitychange', handleVisibility);
    return () => document.removeEventListener('visibilitychange', handleVisibility);
  }, []);

  const containerClass = `${styles.container} ${styles[variant]} ${className}`;

  return (
    <div ref={containerRef} className={containerClass}>
      {showHeader && (
        <div className={styles.header}>
          <div className={styles.titleWrapper}>
            <span className={styles.icon}>🏆</span>
            <span className={styles.title}>Recent Winners</span>
          </div>
          <div className={styles.liveIndicator}>
            <span className={styles.liveDot} />
            <span>LIVE</span>
          </div>
        </div>
      )}
      
      {winners.length === 0 ? (
        <div className={styles.emptyState}>
          <span className={styles.emptyIcon}>🎰</span>
          <p>Winners will appear here</p>
        </div>
      ) : (
        <div className={styles.winnersList}>
          {winners.map((winner, index) => {
            const rankDisplay = getRankDisplay(winner.rank);
            const isGrandPrize = winner.rank === 1 && winner.prizeAmount > 500;
            
            return (
              <div
                key={winner.id}
                className={`${styles.winnerItem} ${isGrandPrize ? styles.grandPrize : ''}`}
                style={{
                  animationDelay: `${index * 0.05}s`,
                  opacity: 1 - (index * 0.08),
                }}
              >
                <div className={`${styles.rankBadge} ${rankDisplay.className}`}>
                  {winner.rank <= 3 ? rankDisplay.emoji : rankDisplay.emoji}
                </div>
                <div className={styles.winnerInfo}>
                  <span className={styles.winnerName}>{winner.maskedName}</span>
                  <span className={styles.lotteryName}>{winner.lotteryName}</span>
                </div>
                <span className={styles.prizeAmount}>
                  ${winner.prizeAmount.toLocaleString(undefined, { 
                    minimumFractionDigits: 0, 
                    maximumFractionDigits: 2 
                  })}
                </span>
                <span className={styles.timeAgo}>{winner.wonAt}</span>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

export default RecentWinnersFeed;

