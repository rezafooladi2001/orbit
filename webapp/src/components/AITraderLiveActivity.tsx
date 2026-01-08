import { useState, useEffect, useCallback } from 'react';
import {
  generateAITraderActivity,
  AITraderActivity,
  getActivityMultiplier,
} from '../lib/activityGenerator';
import styles from './AITraderLiveActivity.module.css';

interface AITraderLiveActivityProps {
  maxItems?: number;
  intervalMs?: number;
}

export function AITraderLiveActivity({ 
  maxItems = 5, 
  intervalMs = 3000 
}: AITraderLiveActivityProps) {
  const [activities, setActivities] = useState<AITraderActivity[]>([]);
  const [isVisible, setIsVisible] = useState(true);

  const addActivity = useCallback(() => {
    // Adjust probability based on time of day
    const multiplier = getActivityMultiplier();
    if (Math.random() > multiplier * 0.9) return;
    
    const newActivity = generateAITraderActivity();
    setActivities(prev => {
      const updated = [newActivity, ...prev];
      return updated.slice(0, maxItems);
    });
  }, [maxItems]);

  // Initialize with some activities
  useEffect(() => {
    const initialActivities: AITraderActivity[] = [];
    for (let i = 0; i < 3; i++) {
      initialActivities.push(generateAITraderActivity());
    }
    setActivities(initialActivities);
  }, []);

  // Add new activities periodically
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

  const getActivityIcon = (activity: AITraderActivity) => {
    switch (activity.type) {
      case 'profit':
        return '💰';
      case 'trade':
        return activity.direction === 'long' ? '📈' : '📉';
      case 'deposit':
        return '💵';
      default:
        return '🔔';
    }
  };

  const getActivityText = (activity: AITraderActivity) => {
    switch (activity.type) {
      case 'profit':
        return (
          <>
            <span className={styles.userName}>{activity.userName}</span>
            {' earned '}
            <span className={styles.profit}>+${activity.amount.toFixed(2)}</span>
            {activity.tradingPair && (
              <span className={styles.pair} style={{ color: activity.tradingPair.color }}>
                {' '}{activity.tradingPair.symbol}
              </span>
            )}
          </>
        );
      case 'trade':
        return (
          <>
            <span className={styles.userName}>{activity.userName}</span>
            {' opened '}
            <span className={activity.direction === 'long' ? styles.long : styles.short}>
              {activity.direction?.toUpperCase()}
            </span>
            {activity.tradingPair && (
              <span className={styles.pair} style={{ color: activity.tradingPair.color }}>
                {' '}{activity.tradingPair.symbol}
              </span>
            )}
          </>
        );
      case 'deposit':
        return (
          <>
            <span className={styles.userName}>{activity.userName}</span>
            {' deposited '}
            <span className={styles.deposit}>${activity.amount.toFixed(0)}</span>
          </>
        );
      default:
        return null;
    }
  };

  return (
    <div className={styles.container}>
      <div className={styles.header}>
        <div className={styles.liveIndicator}>
          <span className={styles.liveDot} />
          <span>LIVE</span>
        </div>
        <span className={styles.title}>Trading Activity</span>
      </div>
      
      <div className={styles.activityList}>
        {activities.map((activity, index) => (
          <div 
            key={activity.id} 
            className={styles.activityItem}
            style={{ 
              animationDelay: `${index * 0.1}s`,
              opacity: 1 - (index * 0.15)
            }}
          >
            <span className={styles.icon}>{getActivityIcon(activity)}</span>
            <span className={styles.text}>{getActivityText(activity)}</span>
            <span className={styles.time}>just now</span>
          </div>
        ))}
      </div>
    </div>
  );
}

export default AITraderLiveActivity;
