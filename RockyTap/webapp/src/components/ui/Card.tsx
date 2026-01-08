import React from 'react';
import styles from './Card.module.css';

interface CardProps {
  children: React.ReactNode;
  variant?: 'default' | 'elevated' | 'bordered' | 'glow' | 'gold';
  padding?: 'none' | 'sm' | 'md' | 'lg';
  className?: string;
  onClick?: () => void;
  /** Accessible label for the card (required for clickable cards) */
  'aria-label'?: string;
}

export function Card({
  children,
  variant = 'default',
  padding = 'md',
  className = '',
  onClick,
  'aria-label': ariaLabel,
}: CardProps) {
  const Component = onClick ? 'button' : 'div';
  
  return (
    <Component
      className={`${styles.card} ${styles[variant]} ${styles[`padding-${padding}`]} ${onClick ? styles.clickable : ''} ${className}`}
      onClick={onClick}
      role={onClick ? 'button' : undefined}
      tabIndex={onClick ? 0 : undefined}
      aria-label={ariaLabel}
      type={onClick ? 'button' : undefined}
    >
      {children}
    </Component>
  );
}

interface CardHeaderProps {
  children: React.ReactNode;
  className?: string;
}

export function CardHeader({ children, className = '' }: CardHeaderProps) {
  return (
    <div className={`${styles.header} ${className}`}>
      {children}
    </div>
  );
}

interface CardTitleProps {
  children: React.ReactNode;
  className?: string;
}

export function CardTitle({ children, className = '' }: CardTitleProps) {
  return (
    <h3 className={`${styles.title} ${className}`} role="heading" aria-level={3}>
      {children}
    </h3>
  );
}

interface CardContentProps {
  children: React.ReactNode;
  className?: string;
}

export function CardContent({ children, className = '' }: CardContentProps) {
  return (
    <div className={`${styles.content} ${className}`}>
      {children}
    </div>
  );
}
