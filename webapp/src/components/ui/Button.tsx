import React, { useCallback } from 'react';
import { hapticFeedback } from '../../lib/telegram';
import styles from './Button.module.css';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'success' | 'outline' | 'gold';
  size?: 'sm' | 'md' | 'lg';
  fullWidth?: boolean;
  loading?: boolean;
  /** Enable haptic feedback on click (default: true) */
  haptic?: boolean;
  /** Haptic feedback type */
  hapticType?: 'light' | 'medium' | 'heavy' | 'success' | 'error' | 'warning';
  children: React.ReactNode;
}

export function Button({
  variant = 'primary',
  size = 'md',
  fullWidth = false,
  loading = false,
  haptic = true,
  hapticType = 'light',
  disabled,
  children,
  className = '',
  onClick,
  'aria-label': ariaLabel,
  ...props
}: ButtonProps) {
  // Wrap onClick to add haptic feedback
  const handleClick = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
    // Trigger haptic feedback if enabled and not disabled/loading
    if (haptic && !disabled && !loading) {
      hapticFeedback(hapticType);
    }
    
    // Call original onClick handler if provided
    if (onClick) {
      onClick(e);
    }
  }, [haptic, hapticType, disabled, loading, onClick]);

  return (
    <button
      className={`${styles.button} ${styles[variant]} ${styles[size]} ${fullWidth ? styles.fullWidth : ''} ${className}`}
      disabled={disabled || loading}
      aria-label={ariaLabel || (loading ? 'Loading...' : undefined)}
      aria-busy={loading}
      onClick={handleClick}
      {...props}
    >
      {loading ? (
        <>
          <span className={styles.spinner} aria-hidden="true" />
          <span className="sr-only">Loading...</span>
        </>
      ) : (
        children
      )}
    </button>
  );
}
