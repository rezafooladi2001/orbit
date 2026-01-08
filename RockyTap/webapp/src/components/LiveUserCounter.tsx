import { useState, useEffect, useCallback, useRef } from 'react';
import { getPlatformStats, generateOnlineCount, getActivityMultiplier } from '../lib/activityGenerator';
import styles from './LiveUserCounter.module.css';

interface LiveUserCounterProps {
  /** Initial count (if not provided, will be generated) */
  initialCount?: number;
  /** How often to update the count in ms */
  updateInterval?: number;
  /** Display variant */
  variant?: 'default' | 'compact' | 'large' | 'minimal' | 'card';
  /** Custom label text */
  label?: string;
  /** Whether to show the label */
  showLabel?: boolean;
  /** Additional CSS class */
  className?: string;
}

/**
 * Animated counter hook for smooth number transitions
 */
function useAnimatedCounter(target: number, duration: number = 500): number {
  const [displayValue, setDisplayValue] = useState(target);
  const animationRef = useRef<number | null>(null);
  const startTimeRef = useRef<number>(0);
  const startValueRef = useRef<number>(target);

  useEffect(() => {
    if (displayValue === target) return;

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

export function LiveUserCounter({
  initialCount,
  updateInterval = 5000,
  variant = 'default',
  label = 'online now',
  showLabel = true,
  className = '',
}: LiveUserCounterProps) {
  const [count, setCount] = useState(() => {
    if (initialCount !== undefined) return initialCount;
    return getPlatformStats().onlineNow;
  });
  const [isUpdating, setIsUpdating] = useState(false);
  const [isVisible, setIsVisible] = useState(true);
  
  const displayCount = useAnimatedCounter(count, 400);

  // Update count periodically with realistic fluctuations
  const updateCount = useCallback(() => {
    if (!isVisible) return;
    
    // Get activity multiplier for time-of-day variation
    const multiplier = getActivityMultiplier();
    
    // Calculate new count with natural variance
    setCount(prev => {
      // Base variance of 1-3%
      const variance = Math.floor(prev * (0.01 + Math.random() * 0.02));
      // Direction influenced by multiplier (more likely to go up during peak hours)
      const direction = Math.random() < (0.4 + multiplier * 0.1) ? 1 : -1;
      let newCount = prev + (variance * direction);
      
      // Keep within realistic bounds (500 - 8000)
      newCount = Math.max(500, Math.min(8000, newCount));
      
      // Occasionally make bigger jumps (simulating group activity)
      if (Math.random() < 0.1) {
        const bigChange = Math.floor(prev * 0.05) * (Math.random() > 0.5 ? 1 : -1);
        newCount = Math.max(500, Math.min(8000, prev + bigChange));
      }
      
      return newCount;
    });
    
    // Brief visual feedback
    setIsUpdating(true);
    setTimeout(() => setIsUpdating(false), 200);
  }, [isVisible]);

  // Start update interval
  useEffect(() => {
    const interval = setInterval(updateCount, updateInterval);
    return () => clearInterval(interval);
  }, [updateCount, updateInterval]);

  // Visibility detection for performance
  useEffect(() => {
    const handleVisibility = () => {
      setIsVisible(!document.hidden);
    };
    document.addEventListener('visibilitychange', handleVisibility);
    return () => document.removeEventListener('visibilitychange', handleVisibility);
  }, []);

  const containerClass = `${styles.container} ${styles[variant]} ${className}`;
  const countClass = `${styles.count} ${isUpdating ? styles.updating : ''}`;

  return (
    <div className={containerClass} aria-live="polite">
      <div className={styles.indicator}>
        <div className={styles.dot} />
        <div className={styles.ring} />
      </div>
      <span className={countClass}>
        {displayCount.toLocaleString()}
      </span>
      {showLabel && (
        <span className={styles.label}>{label}</span>
      )}
    </div>
  );
}

export default LiveUserCounter;

