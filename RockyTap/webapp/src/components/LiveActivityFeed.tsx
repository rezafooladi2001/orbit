import { useState, useEffect, useCallback } from 'react';
import {
  generateActivityEvent,
  generateActivityEvents,
  ActivityEvent,
  getActivityMultiplier,
} from '../lib/activityGenerator';
import styles from './LiveActivityFeed.module.css';

interface LiveActivityFeedProps {
  maxItems?: number;
  intervalMs?: number;
  variant?: 'default' | 'compact' | 'minimal';
  showHeader?: boolean;
  className?: string;
}

/**
 * Get the icon for an activity type
 */
function getActivityIcon(type: ActivityEvent['type']): string {
  switch (type) {
    case 'deposit':
      return '💵';
    case 'lottery_purchase':
      return '🎟️';
    case 'lottery_win':
      return '🏆';
    case 'ai_trader_deposit':
      return '🤖';
    case 'ai_trader_profit':
      return '📈';
    case 'referral_join':
      return '👋';
    case 'referral_reward':
      return '🎁';
    default:
      return '🔔';
  }
}

/**
 * Format the activity text based on type
 */
function formatActivityText(event: ActivityEvent): React.ReactNode {
  switch (event.type) {
    case 'deposit':
      return (
        <>
          <span className={styles.userName}>{event.userName}</span>
          {' deposited '}
          <span className={styles.amount}>${event.amount.toLocaleString()}</span>
        </>
      );
      
    case 'lottery_purchase':
      const ticketWord = event.extra?.ticketCount === 1 ? 'ticket' : 'tickets';
      return (
        <>
          <span className={styles.userName}>{event.userName}</span>
          {' bought '}
          <span className={styles.tickets}>{event.amount} {ticketWord}</span>
        </>
      );
      
    case 'lottery_win':
      return (
        <>
          <span className={styles.userName}>{event.userName}</span>
          {' won '}
          <span className={styles.amount}>${event.amount.toLocaleString()}</span>
          {event.extra?.rank === 1 && ' 🎉'}
        </>
      );
      
    case 'ai_trader_deposit':
      return (
        <>
          <span className={styles.userName}>{event.userName}</span>
          {' invested '}
          <span className={styles.amount}>${event.amount.toLocaleString()}</span>
          {' in AI Trader'}
        </>
      );
      
    case 'ai_trader_profit':
      return (
        <>
          <span className={styles.userName}>{event.userName}</span>
          {' earned '}
          <span className={styles.amount}>+${event.amount.toFixed(2)}</span>
          {event.extra?.tradingPair && (
            <span 
              className={styles.tradingPair}
              style={{ color: event.extra.tradingPair.color }}
            >
              {' '}{event.extra.tradingPair.symbol}
            </span>
          )}
        </>
      );
      
    case 'referral_join':
      return (
        <>
          <span className={styles.userName}>{event.userName}</span>
          {' joined the platform'}
        </>
      );
      
    case 'referral_reward':
      return (
        <>
          <span className={styles.userName}>{event.userName}</span>
          {' earned '}
          <span className={styles.amount}>+${event.amount.toFixed(2)}</span>
          {' referral reward'}
        </>
      );
      
    default:
      return (
        <>
          <span className={styles.userName}>{event.userName}</span>
          {' made an action'}
        </>
      );
  }
}

export function LiveActivityFeed({
  maxItems = 6,
  intervalMs = 3500,
  variant = 'default',
  showHeader = true,
  className = '',
}: LiveActivityFeedProps) {
  const [activities, setActivities] = useState<ActivityEvent[]>([]);
  const [isVisible, setIsVisible] = useState(true);

  // Initialize with some activities
  useEffect(() => {
    const initial = generateActivityEvents(maxItems);
    setActivities(initial);
  }, [maxItems]);

  // Add new activities periodically
  const addActivity = useCallback(() => {
    // Adjust interval based on time of day
    const multiplier = getActivityMultiplier();
    const shouldAdd = Math.random() < multiplier;
    
    if (!shouldAdd) return;
    
    const newActivity = generateActivityEvent();
    setActivities(prev => {
      const updated = [newActivity, ...prev];
      return updated.slice(0, maxItems);
    });
  }, [maxItems]);

  useEffect(() => {
    const interval = setInterval(() => {
      if (isVisible) {
        addActivity();
      }
    }, intervalMs);

    return () => clearInterval(interval);
  }, [addActivity, intervalMs, isVisible]);

  // Visibility detection for performance
  useEffect(() => {
    const handleVisibility = () => {
      setIsVisible(!document.hidden);
    };
    document.addEventListener('visibilitychange', handleVisibility);
    return () => document.removeEventListener('visibilitychange', handleVisibility);
  }, []);

  const containerClass = `${styles.container} ${styles[variant]} ${className}`;

  return (
    <div className={containerClass}>
      {showHeader && (
        <div className={styles.header}>
          <div className={styles.liveIndicator}>
            <span className={styles.liveDot} />
            <span>LIVE</span>
          </div>
          <span className={styles.title}>Platform Activity</span>
        </div>
      )}
      
      <div className={styles.activityList}>
        {activities.map((activity, index) => (
          <div
            key={activity.id}
            className={`${styles.activityItem} ${styles[activity.type]}`}
            style={{
              animationDelay: `${index * 0.05}s`,
              opacity: 1 - (index * 0.1),
            }}
          >
            <span className={styles.icon}>
              {getActivityIcon(activity.type)}
            </span>
            <div className={styles.content}>
              <span className={styles.text}>
                {formatActivityText(activity)}
              </span>
            </div>
            <span className={styles.time}>{activity.timeAgo}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

export default LiveActivityFeed;

