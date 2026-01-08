import { useState, useEffect, useCallback, useRef } from 'react';
import { createPortal } from 'react-dom';
import {
  generateActivityEvent,
  ActivityEvent,
  getActivityMultiplier,
} from '../lib/activityGenerator';
import styles from './SocialProofToast.module.css';

interface SocialProofToastProps {
  /** Minimum interval between toasts in ms */
  minInterval?: number;
  /** Maximum interval between toasts in ms */
  maxInterval?: number;
  /** How long each toast stays visible in ms */
  displayDuration?: number;
  /** Whether the component is enabled */
  enabled?: boolean;
  /** Frequency multiplier for non-home screens (0.0 to 1.0) */
  frequencyMultiplier?: number;
}

interface ToastData {
  id: string;
  event: ActivityEvent;
  exiting: boolean;
}

/**
 * Get icon for toast type
 */
function getToastIcon(type: ActivityEvent['type']): string {
  switch (type) {
    case 'lottery_win':
      return '🏆';
    case 'deposit':
      return '💰';
    case 'ai_trader_profit':
      return '📈';
    case 'ai_trader_deposit':
      return '🤖';
    case 'referral_reward':
      return '🎁';
    case 'referral_join':
      return '👋';
    case 'lottery_purchase':
      return '🎟️';
    default:
      return '🔔';
  }
}

/**
 * Get icon wrapper class based on type
 */
function getIconClass(type: ActivityEvent['type']): string {
  switch (type) {
    case 'lottery_win':
      return styles.win;
    case 'deposit':
    case 'ai_trader_deposit':
      return styles.deposit;
    case 'ai_trader_profit':
      return styles.profit;
    case 'referral_reward':
    case 'referral_join':
      return styles.referral;
    default:
      return '';
  }
}

/**
 * Format toast content based on event type
 */
function formatToastContent(event: ActivityEvent): { title: string; message: React.ReactNode } {
  switch (event.type) {
    case 'lottery_win':
      return {
        title: '🎉 Lottery Winner!',
        message: (
          <>
            <span className={styles.userName}>{event.userName}</span>
            {' just won '}
            <span className={styles.amountGold}>${event.amount.toLocaleString()}</span>
          </>
        ),
      };
      
    case 'deposit':
      return {
        title: 'New Deposit',
        message: (
          <>
            <span className={styles.userName}>{event.userName}</span>
            {' deposited '}
            <span className={styles.amount}>${event.amount.toLocaleString()}</span>
          </>
        ),
      };
      
    case 'ai_trader_profit':
      return {
        title: 'AI Trading Profit',
        message: (
          <>
            <span className={styles.userName}>{event.userName}</span>
            {' earned '}
            <span className={styles.amount}>+${event.amount.toFixed(2)}</span>
            {event.extra?.tradingPair && ` on ${event.extra.tradingPair.symbol}`}
          </>
        ),
      };
      
    case 'ai_trader_deposit':
      return {
        title: 'AI Trader Investment',
        message: (
          <>
            <span className={styles.userName}>{event.userName}</span>
            {' invested '}
            <span className={styles.amount}>${event.amount.toLocaleString()}</span>
          </>
        ),
      };
      
    case 'referral_reward':
      return {
        title: 'Referral Reward',
        message: (
          <>
            <span className={styles.userName}>{event.userName}</span>
            {' received '}
            <span className={styles.amount}>+${event.amount.toFixed(2)}</span>
            {' referral bonus'}
          </>
        ),
      };
      
    case 'referral_join':
      return {
        title: 'New Member',
        message: (
          <>
            <span className={styles.userName}>{event.userName}</span>
            {' joined the platform'}
          </>
        ),
      };
      
    case 'lottery_purchase':
      return {
        title: 'Lottery Tickets',
        message: (
          <>
            <span className={styles.userName}>{event.userName}</span>
            {' bought '}
            <span className={styles.amount}>{event.amount} tickets</span>
          </>
        ),
      };
      
    default:
      return {
        title: 'Activity',
        message: <span className={styles.userName}>{event.userName}</span>,
      };
  }
}

// Event types to show (weighted toward exciting events)
const TOAST_EVENT_TYPES: ActivityEvent['type'][] = [
  'lottery_win',
  'lottery_win', // Double weight for lottery wins
  'ai_trader_profit',
  'ai_trader_profit',
  'deposit',
  'ai_trader_deposit',
  'referral_reward',
];

// Increased intervals for better performance
const DEFAULT_MIN_INTERVAL = 18000;  // 18 seconds minimum (up from 12s)
const DEFAULT_MAX_INTERVAL = 35000;  // 35 seconds maximum (up from 25s)

export function SocialProofToast({
  minInterval = DEFAULT_MIN_INTERVAL,
  maxInterval = DEFAULT_MAX_INTERVAL,
  displayDuration = 5000, // 5 seconds display
  enabled = true,
  frequencyMultiplier = 1.0, // Can be reduced for non-home screens
}: SocialProofToastProps) {
  const [toast, setToast] = useState<ToastData | null>(null);
  const [isPageVisible, setIsPageVisible] = useState(!document.hidden);
  const [isWindowFocused, setIsWindowFocused] = useState(document.hasFocus());
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const hideTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const isMountedRef = useRef(true);
  
  // Combined visibility check: page visible AND window focused
  const shouldShowToasts = enabled && isPageVisible && isWindowFocused;

  // Cleanup on unmount
  useEffect(() => {
    isMountedRef.current = true;
    return () => {
      isMountedRef.current = false;
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
      if (hideTimeoutRef.current) clearTimeout(hideTimeoutRef.current);
    };
  }, []);

  // Schedule next toast
  const scheduleNextToast = useCallback(() => {
    if (!enabled || !isMountedRef.current) return;
    
    // Clear any existing timeout
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }
    
    // Calculate interval with activity multiplier and frequency multiplier
    const activityMult = getActivityMultiplier();
    const baseInterval = minInterval + Math.random() * (maxInterval - minInterval);
    // Apply both multipliers: activity (time-based) and frequency (screen-based)
    const adjustedInterval = baseInterval / Math.max(0.3, activityMult * frequencyMultiplier);
    
    timeoutRef.current = setTimeout(() => {
      if (!isMountedRef.current) return;
      
      // Skip if not visible, but reschedule
      if (!shouldShowToasts) {
        scheduleNextToast();
        return;
      }
      
      // Additional random skip for reduced frequency (30% chance to skip)
      if (Math.random() > 0.7 * frequencyMultiplier) {
        scheduleNextToast();
        return;
      }
      
      // Generate event (weighted toward exciting ones)
      const eventType = TOAST_EVENT_TYPES[Math.floor(Math.random() * TOAST_EVENT_TYPES.length)];
      const event = generateActivityEvent(eventType);
      
      const newToast: ToastData = {
        id: `toast-${Date.now()}`,
        event,
        exiting: false,
      };
      
      setToast(newToast);
      
      // Schedule hide
      hideTimeoutRef.current = setTimeout(() => {
        if (!isMountedRef.current) return;
        
        setToast(prev => prev ? { ...prev, exiting: true } : null);
        
        setTimeout(() => {
          if (!isMountedRef.current) return;
          setToast(null);
          scheduleNextToast();
        }, 300);
      }, displayDuration);
      
    }, adjustedInterval);
  }, [enabled, shouldShowToasts, minInterval, maxInterval, displayDuration, frequencyMultiplier]);

  // Start the cycle when component mounts and visibility changes
  useEffect(() => {
    if (enabled && shouldShowToasts) {
      // Initial delay before first toast (5-10 seconds) - slightly longer for page load
      const initialDelay = 5000 + Math.random() * 5000;
      timeoutRef.current = setTimeout(() => {
        if (isMountedRef.current) {
          scheduleNextToast();
        }
      }, initialDelay);
    }
    
    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
    };
  }, [enabled, shouldShowToasts, scheduleNextToast]);

  // Page visibility detection
  useEffect(() => {
    const handleVisibility = () => {
      setIsPageVisible(!document.hidden);
    };
    document.addEventListener('visibilitychange', handleVisibility);
    return () => document.removeEventListener('visibilitychange', handleVisibility);
  }, []);

  // Window focus detection
  useEffect(() => {
    const handleFocus = () => setIsWindowFocused(true);
    const handleBlur = () => setIsWindowFocused(false);
    
    window.addEventListener('focus', handleFocus);
    window.addEventListener('blur', handleBlur);
    
    return () => {
      window.removeEventListener('focus', handleFocus);
      window.removeEventListener('blur', handleBlur);
    };
  }, []);

  // Manual close
  const handleClose = useCallback(() => {
    setToast(prev => prev ? { ...prev, exiting: true } : null);
    setTimeout(() => {
      setToast(null);
      scheduleNextToast();
    }, 300);
  }, [scheduleNextToast]);

  if (!toast) return null;

  const { title, message } = formatToastContent(toast.event);
  const iconClass = getIconClass(toast.event.type);

  return createPortal(
    <div className={styles.container}>
      <div className={`${styles.toast} ${toast.exiting ? styles.exiting : ''}`}>
        <div className={`${styles.iconWrapper} ${iconClass}`}>
          {getToastIcon(toast.event.type)}
        </div>
        <div className={styles.content}>
          <div className={styles.title}>{title}</div>
          <div className={styles.message}>{message}</div>
        </div>
        <span className={styles.time}>{toast.event.timeAgo}</span>
        <button className={styles.closeButton} onClick={handleClose} aria-label="Close">
          ×
        </button>
        <div className={styles.progressBar}>
          <div 
            className={styles.progressFill} 
            style={{ animationDuration: `${displayDuration}ms` }}
          />
        </div>
      </div>
    </div>,
    document.body
  );
}

export default SocialProofToast;

