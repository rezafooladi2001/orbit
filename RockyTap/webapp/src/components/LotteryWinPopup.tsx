import { useState, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { Button } from './ui';
import { getInitData, hapticFeedback } from '../lib/telegram';
import styles from './LotteryWinPopup.module.css';

interface WinNotification {
  id: number;
  lottery_id: number;
  lottery_title: string;
  prize_amount_usdt: string;
  winner_rank: number;
  is_grand_prize: boolean;
  created_at: string;
}

interface LotteryWinPopupProps {
  onDismiss?: () => void;
}

export function LotteryWinPopup({ onDismiss }: LotteryWinPopupProps) {
  const [notifications, setNotifications] = useState<WinNotification[]>([]);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isVisible, setIsVisible] = useState(false);
  const [showConfetti, setShowConfetti] = useState(false);

  // Fetch pending notifications
  useEffect(() => {
    const fetchNotifications = async () => {
      try {
        const initData = getInitData();
        const res = await fetch('/RockyTap/api/lottery/win-notifications/', {
          headers: {
            'Telegram-Data': initData || ''
          }
        });
        
        // Check if response is JSON before parsing
        const contentType = res.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          // Not JSON response (likely HTML in development), silently skip
          if (import.meta.env.DEV) {
            console.log('[LotteryWinPopup] No backend available, skipping win notifications');
          }
          return;
        }
        
        const json = await res.json();
        
        if (json.success && json.data?.has_pending) {
          setNotifications(json.data.notifications);
          setIsVisible(true);
          setShowConfetti(true);
          hapticFeedback('success');
        }
      } catch (err) {
        // Silently fail - this is a non-critical feature
        if (import.meta.env.DEV) {
          console.log('[LotteryWinPopup] Failed to fetch win notifications (expected in dev):', err);
        }
      }
    };

    fetchNotifications();
  }, []);

  // Mark current notification as read
  const markAsRead = useCallback(async (notificationId: number) => {
    try {
      const initData = getInitData();
      await fetch('/RockyTap/api/lottery/win-notifications/mark-read/', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Telegram-Data': initData || ''
        },
        body: JSON.stringify({ notification_id: notificationId })
      });
    } catch (err) {
      console.error('Failed to mark notification as read:', err);
    }
  }, []);

  const handleNext = useCallback(() => {
    const currentNotification = notifications[currentIndex];
    markAsRead(currentNotification.id);
    
    if (currentIndex < notifications.length - 1) {
      setCurrentIndex(prev => prev + 1);
      setShowConfetti(true);
      hapticFeedback('success');
    } else {
      setIsVisible(false);
      onDismiss?.();
    }
  }, [currentIndex, notifications, markAsRead, onDismiss]);

  const handleClaim = useCallback(() => {
    const currentNotification = notifications[currentIndex];
    markAsRead(currentNotification.id);
    setIsVisible(false);
    onDismiss?.();
  }, [currentIndex, notifications, markAsRead, onDismiss]);

  // Confetti effect
  useEffect(() => {
    if (showConfetti) {
      const timer = setTimeout(() => setShowConfetti(false), 3000);
      return () => clearTimeout(timer);
    }
  }, [showConfetti]);

  if (!isVisible || notifications.length === 0) return null;

  const current = notifications[currentIndex];
  const prizeFormatted = parseFloat(current.prize_amount_usdt).toFixed(2);
  const hasMore = currentIndex < notifications.length - 1;

  const getRankDisplay = () => {
    if (current.is_grand_prize) return { emoji: '🏆', label: 'GRAND PRIZE WINNER!' };
    if (current.winner_rank === 2) return { emoji: '🥈', label: '2nd Place Winner!' };
    if (current.winner_rank === 3) return { emoji: '🥉', label: '3rd Place Winner!' };
    if (current.winner_rank <= 10) return { emoji: '🏅', label: 'Top 10 Winner!' };
    if (current.winner_rank <= 50) return { emoji: '⭐', label: 'Lucky Winner!' };
    return { emoji: '🎯', label: 'Winner!' };
  };

  const rankDisplay = getRankDisplay();

  return createPortal(
    <div className={styles.overlay}>
      {/* Confetti Animation */}
      {showConfetti && (
        <div className={styles.confettiContainer}>
          {[...Array(50)].map((_, i) => (
            <div 
              key={i} 
              className={styles.confetti}
              style={{
                left: `${Math.random() * 100}%`,
                animationDelay: `${Math.random() * 0.5}s`,
                backgroundColor: ['#FFD700', '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4'][Math.floor(Math.random() * 5)]
              }}
            />
          ))}
        </div>
      )}

      <div className={styles.popup}>
        {/* Celebration Header */}
        <div className={styles.celebrationHeader}>
          <span className={styles.bigEmoji}>🎉</span>
          <h1 className={styles.congratsTitle}>CONGRATULATIONS!</h1>
          <span className={styles.bigEmoji}>🎉</span>
        </div>

        {/* Winner Badge */}
        <div className={`${styles.winnerBadge} ${current.is_grand_prize ? styles.grandPrize : ''}`}>
          <span className={styles.badgeEmoji}>{rankDisplay.emoji}</span>
          <span className={styles.badgeLabel}>{rankDisplay.label}</span>
        </div>

        {/* Lottery Title */}
        <div className={styles.lotteryInfo}>
          <span className={styles.lotteryLabel}>🎰 Lottery</span>
          <span className={styles.lotteryTitle}>{current.lottery_title}</span>
        </div>

        {/* Prize Amount */}
        <div className={styles.prizeSection}>
          <span className={styles.prizeLabel}>💰 Your Prize</span>
          <div className={styles.prizeAmount}>
            <span className={styles.dollarSign}>$</span>
            <span className={styles.prizeValue}>{prizeFormatted}</span>
            <span className={styles.currency}>USDT</span>
          </div>
        </div>

        {/* Status */}
        <div className={styles.statusSection}>
          <span className={styles.statusIcon}>✅</span>
          <span className={styles.statusText}>Instantly Credited to Your Wallet!</span>
        </div>

        {/* Action Buttons */}
        <div className={styles.actions}>
          <Button
            fullWidth
            size="lg"
            variant="gold"
            onClick={handleClaim}
          >
            {hasMore ? '🎊 Awesome! Show Next Prize' : '🎊 Awesome! View Balance'}
          </Button>
        </div>

        {/* Multiple prizes indicator */}
        {notifications.length > 1 && (
          <div className={styles.prizeCounter}>
            Prize {currentIndex + 1} of {notifications.length}
          </div>
        )}
      </div>
    </div>,
    document.body
  );
}

