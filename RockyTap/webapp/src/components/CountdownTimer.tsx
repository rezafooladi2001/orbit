import { useState, useEffect, useCallback, useMemo } from 'react';
import styles from './CountdownTimer.module.css';

interface CountdownTimerProps {
  /** ISO 8601 end date string */
  endAt: string;
  /** Called when countdown reaches zero */
  onComplete?: () => void;
  /** Size variant */
  size?: 'sm' | 'md' | 'lg';
  /** Show labels under digits */
  showLabels?: boolean;
  /** Additional CSS class */
  className?: string;
}

interface TimeRemaining {
  days: number;
  hours: number;
  minutes: number;
  seconds: number;
  totalMs: number;
  ended: boolean;
}

/**
 * Parse ISO 8601 date string safely
 * Handles dates with and without timezone info
 */
function parseDate(dateString: string): number {
  if (!dateString) return 0;
  
  // Try parsing as-is first (works for ISO 8601 with timezone)
  let date = new Date(dateString);
  
  // If invalid, try adding 'Z' suffix for UTC interpretation
  if (isNaN(date.getTime())) {
    // Handle MySQL format without timezone (YYYY-MM-DD HH:MM:SS)
    const withZ = dateString.replace(' ', 'T') + 'Z';
    date = new Date(withZ);
  }
  
  return date.getTime();
}

/**
 * Calculate time remaining
 */
function calculateTimeRemaining(endAt: string): TimeRemaining {
  const end = parseDate(endAt);
  const now = Date.now();
  const diff = end - now;
  
  if (diff <= 0) {
    return { days: 0, hours: 0, minutes: 0, seconds: 0, totalMs: 0, ended: true };
  }
  
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((diff % (1000 * 60)) / 1000);
  
  return { days, hours, minutes, seconds, totalMs: diff, ended: false };
}

/**
 * CountdownTimer Component
 * 
 * Real-time countdown timer with animated digit transitions
 * and urgency state changes.
 */
export function CountdownTimer({
  endAt,
  onComplete,
  size = 'md',
  showLabels = true,
  className = '',
}: CountdownTimerProps) {
  const [time, setTime] = useState<TimeRemaining>(() => calculateTimeRemaining(endAt));
  const [hasCompleted, setHasCompleted] = useState(false);

  // Update countdown every second
  useEffect(() => {
    const updateTime = () => {
      const newTime = calculateTimeRemaining(endAt);
      setTime(newTime);
      
      if (newTime.ended && !hasCompleted) {
        setHasCompleted(true);
        onComplete?.();
      }
    };

    // Initial update
    updateTime();

    // Set up interval
    const interval = setInterval(updateTime, 1000);
    
    return () => clearInterval(interval);
  }, [endAt, onComplete, hasCompleted]);

  // Determine urgency level
  const urgencyLevel = useMemo((): 'normal' | 'warning' | 'critical' => {
    if (time.ended) return 'critical';
    if (time.totalMs <= 60 * 60 * 1000) return 'critical'; // < 1 hour
    if (time.totalMs <= 24 * 60 * 60 * 1000) return 'warning'; // < 24 hours
    return 'normal';
  }, [time.totalMs, time.ended]);

  // Format digit with leading zero
  const formatDigit = useCallback((num: number, padTo: number = 2): string => {
    return num.toString().padStart(padTo, '0');
  }, []);

  // Digit component with animation
  const Digit = ({ value, label }: { value: string; label: string }) => (
    <div className={styles.digitGroup}>
      <div className={styles.digitWrapper}>
        {value.split('').map((digit, index) => (
          <span key={`${label}-${index}`} className={styles.digit} data-digit={digit}>
            {digit}
          </span>
        ))}
      </div>
      {showLabels && <span className={styles.label}>{label}</span>}
    </div>
  );

  // Separator
  const Separator = () => (
    <span className={`${styles.separator} ${time.seconds % 2 === 0 ? styles.blink : ''}`}>:</span>
  );

  if (time.ended) {
    return (
      <div className={`${styles.container} ${styles[size]} ${styles.ended} ${className}`}>
        <div className={styles.endedMessage}>
          <span className={styles.endedIcon}>🏁</span>
          <span>Lottery Ended</span>
        </div>
      </div>
    );
  }

  return (
    <div className={`${styles.container} ${styles[size]} ${styles[urgencyLevel]} ${className}`}>
      {time.days > 0 && (
        <>
          <Digit value={formatDigit(time.days, time.days >= 100 ? 3 : 2)} label="Days" />
          <Separator />
        </>
      )}
      <Digit value={formatDigit(time.hours)} label="Hours" />
      <Separator />
      <Digit value={formatDigit(time.minutes)} label="Min" />
      <Separator />
      <Digit value={formatDigit(time.seconds)} label="Sec" />
      
      {/* Urgency indicator */}
      {urgencyLevel === 'critical' && (
        <div className={styles.urgencyBadge}>
          <span className={styles.pulsingDot} />
          ENDING SOON!
        </div>
      )}
    </div>
  );
}

export default CountdownTimer;

