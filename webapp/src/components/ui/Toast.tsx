import { createContext, useContext, useState, useCallback, useRef, useEffect, ReactNode } from 'react';
import { CheckIcon, CloseIcon, WarningIcon, InfoIcon } from '../Icons';
import styles from './Toast.module.css';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

export interface Toast {
  id: string;
  message: string;
  type: ToastType;
  action?: {
    label: string;
    onClick: () => void;
  };
  duration?: number;
  isExiting?: boolean;
}

interface ToastContextType {
  showToast: (message: string, type?: ToastType, options?: { action?: Toast['action']; duration?: number }) => void;
  showError: (message: string, action?: Toast['action']) => void;
  showSuccess: (message: string, action?: Toast['action']) => void;
  showInfo: (message: string, action?: Toast['action']) => void;
  showWarning: (message: string, action?: Toast['action']) => void;
  dismissToast: (id: string) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export function useToast(): ToastContextType {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within ToastProvider');
  }
  return context;
}

interface ToastProviderProps {
  children: ReactNode;
  maxToasts?: number;
}

const ToastIcon = ({ type }: { type: ToastType }) => {
  switch (type) {
    case 'success':
      return <CheckIcon size={16} color="#fff" />;
    case 'error':
      return <CloseIcon size={16} color="#fff" />;
    case 'warning':
      return <WarningIcon size={16} color="#0a0c10" />;
    case 'info':
      return <InfoIcon size={16} color="#fff" />;
    default:
      return null;
  }
};

const TOAST_DURATION = 4000;
const EXIT_ANIMATION_DURATION = 250;
const MAX_TOASTS = 4;

interface ToastItemProps {
  toast: Toast;
  index: number;
  onDismiss: (id: string) => void;
  onHover: (id: string, isHovering: boolean) => void;
}

function ToastItem({ toast, index, onDismiss, onHover }: ToastItemProps) {
  const [progress, setProgress] = useState(100);
  const progressRef = useRef<number | null>(null);
  const startTimeRef = useRef<number>(Date.now());
  const pausedAtRef = useRef<number | null>(null);
  const duration = toast.duration || TOAST_DURATION;

  useEffect(() => {
    const animate = () => {
      const elapsed = Date.now() - startTimeRef.current;
      const remaining = Math.max(0, 100 - (elapsed / duration) * 100);
      setProgress(remaining);

      if (remaining > 0) {
        progressRef.current = requestAnimationFrame(animate);
      }
    };

    progressRef.current = requestAnimationFrame(animate);

    return () => {
      if (progressRef.current) {
        cancelAnimationFrame(progressRef.current);
      }
    };
  }, [duration]);

  const handleMouseEnter = () => {
    onHover(toast.id, true);
    if (progressRef.current) {
      cancelAnimationFrame(progressRef.current);
      pausedAtRef.current = progress;
    }
  };

  const handleMouseLeave = () => {
    onHover(toast.id, false);
    if (pausedAtRef.current !== null) {
      // Resume from paused progress
      startTimeRef.current = Date.now() - ((100 - pausedAtRef.current) / 100 * duration);
      pausedAtRef.current = null;
      const animate = () => {
        const elapsed = Date.now() - startTimeRef.current;
        const remaining = Math.max(0, 100 - (elapsed / duration) * 100);
        setProgress(remaining);
        if (remaining > 0) {
          progressRef.current = requestAnimationFrame(animate);
        }
      };
      progressRef.current = requestAnimationFrame(animate);
    }
  };

  const handleDismiss = () => {
    onDismiss(toast.id);
  };

  const handleActionClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (toast.action) {
      toast.action.onClick();
      onDismiss(toast.id);
    }
  };

  // Calculate stacking transform
  const translateY = index * -4;
  const scale = 1 - index * 0.02;
  const opacity = 1 - index * 0.1;

  return (
    <div
      className={`${styles.toast} ${styles[toast.type]} ${toast.isExiting ? styles.exiting : ''}`}
      style={{
        transform: `translateY(${translateY}px) scale(${scale})`,
        opacity: opacity,
        zIndex: MAX_TOASTS - index,
      }}
      onClick={handleDismiss}
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
      role="alert"
      aria-live={toast.type === 'error' ? 'assertive' : 'polite'}
      aria-atomic="true"
    >
      <div className={styles.content}>
        <span className={styles.icon}>
          <ToastIcon type={toast.type} />
        </span>
        <span className={styles.message}>{toast.message}</span>
        {toast.action && (
          <button 
            className={styles.actionButton}
            onClick={handleActionClick}
            type="button"
          >
            {toast.action.label}
          </button>
        )}
        <button 
          className={styles.closeButton}
          onClick={(e) => {
            e.stopPropagation();
            handleDismiss();
          }}
          aria-label="Dismiss notification"
          type="button"
        >
          <CloseIcon size={14} />
        </button>
      </div>
      <div 
        className={styles.progressBar}
        style={{ width: `${progress}%` }}
        aria-hidden="true"
      />
    </div>
  );
}

export function ToastProvider({ children, maxToasts = MAX_TOASTS }: ToastProviderProps) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const timersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map());
  const pausedToastsRef = useRef<Set<string>>(new Set());

  const removeToast = useCallback((id: string) => {
    // First mark as exiting for animation
    setToasts(prev => prev.map(toast => 
      toast.id === id ? { ...toast, isExiting: true } : toast
    ));

    // Then remove after animation completes
    setTimeout(() => {
      setToasts(prev => prev.filter(toast => toast.id !== id));
      const timer = timersRef.current.get(id);
      if (timer) {
        clearTimeout(timer);
        timersRef.current.delete(id);
      }
      pausedToastsRef.current.delete(id);
    }, EXIT_ANIMATION_DURATION);
  }, []);

  const showToast = useCallback((
    message: string, 
    type: ToastType = 'info',
    options?: { action?: Toast['action']; duration?: number }
  ) => {
    const id = `toast-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
    const duration = options?.duration || TOAST_DURATION;
    
    const newToast: Toast = { 
      id, 
      message, 
      type,
      action: options?.action,
      duration,
    };

    setToasts(prev => {
      // Limit to maxToasts, remove oldest if exceeded
      const updated = [newToast, ...prev];
      if (updated.length > maxToasts) {
        const toRemove = updated.slice(maxToasts);
        toRemove.forEach(t => {
          const timer = timersRef.current.get(t.id);
          if (timer) {
            clearTimeout(timer);
            timersRef.current.delete(t.id);
          }
        });
        return updated.slice(0, maxToasts);
      }
      return updated;
    });

    // Set up auto-dismiss timer
    const timer = setTimeout(() => {
      if (!pausedToastsRef.current.has(id)) {
        removeToast(id);
      }
    }, duration);
    timersRef.current.set(id, timer);

    // Trigger haptic feedback if available
    if (typeof window !== 'undefined' && window.Telegram?.WebApp?.HapticFeedback) {
      const hapticType = type === 'error' ? 'error' : type === 'success' ? 'success' : 'warning';
      try {
        window.Telegram.WebApp.HapticFeedback.notificationOccurred(hapticType as 'success' | 'error' | 'warning');
      } catch (e) {
        // Silently fail if haptic feedback not available
      }
    }
  }, [maxToasts, removeToast]);

  const handleHover = useCallback((id: string, isHovering: boolean) => {
    if (isHovering) {
      pausedToastsRef.current.add(id);
      const timer = timersRef.current.get(id);
      if (timer) {
        clearTimeout(timer);
      }
    } else {
      pausedToastsRef.current.delete(id);
      // Restart timer with remaining time (approximately)
      const toast = toasts.find(t => t.id === id);
      if (toast) {
        const timer = setTimeout(() => {
          removeToast(id);
        }, 2000); // Give 2 more seconds after unhover
        timersRef.current.set(id, timer);
      }
    }
  }, [toasts, removeToast]);

  const showError = useCallback(
    (message: string, action?: Toast['action']) => showToast(message, 'error', { action }), 
    [showToast]
  );
  const showSuccess = useCallback(
    (message: string, action?: Toast['action']) => showToast(message, 'success', { action }), 
    [showToast]
  );
  const showInfo = useCallback(
    (message: string, action?: Toast['action']) => showToast(message, 'info', { action }), 
    [showToast]
  );
  const showWarning = useCallback(
    (message: string, action?: Toast['action']) => showToast(message, 'warning', { action }), 
    [showToast]
  );

  const dismissToast = useCallback((id: string) => {
    removeToast(id);
  }, [removeToast]);

  // Cleanup timers on unmount
  useEffect(() => {
    return () => {
      timersRef.current.forEach(timer => clearTimeout(timer));
      timersRef.current.clear();
    };
  }, []);

  return (
    <ToastContext.Provider value={{ showToast, showError, showSuccess, showInfo, showWarning, dismissToast }}>
      {children}
      <div 
        className={styles.container} 
        role="region" 
        aria-live="polite" 
        aria-label="Notifications"
      >
        {toasts.map((toast, index) => (
          <ToastItem 
            key={toast.id} 
            toast={toast} 
            index={index}
            onDismiss={removeToast}
            onHover={handleHover}
          />
        ))}
      </div>
    </ToastContext.Provider>
  );
}
