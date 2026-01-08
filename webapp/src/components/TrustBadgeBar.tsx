import React from 'react';
import styles from './TrustBadgeBar.module.css';

interface TrustBadge {
  id: string;
  icon: React.ReactNode;
  label: string;
  description?: string;
}

interface TrustBadgeBarProps {
  variant?: 'compact' | 'full';
  showLabels?: boolean;
  className?: string;
}

// Custom SVG icons for better visual quality
const LockIcon = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
  </svg>
);

const ShieldIcon = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
    <path d="M9 12l2 2 4-4"/>
  </svg>
);

const CheckCircleIcon = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
    <polyline points="22 4 12 14.01 9 11.01"/>
  </svg>
);

const BoltIcon = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
  </svg>
);

const defaultBadges: TrustBadge[] = [
  {
    id: 'ssl',
    icon: <LockIcon />,
    label: 'SSL Encrypted',
    description: 'All connections are secured with SSL encryption',
  },
  {
    id: 'secure',
    icon: <ShieldIcon />,
    label: 'Secure',
    description: 'Bank-level security and encryption',
  },
  {
    id: 'verified',
    icon: <CheckCircleIcon />,
    label: 'Verified',
    description: 'Verified Telegram Mini App',
  },
  {
    id: 'telegram',
    icon: <BoltIcon />,
    label: 'Telegram',
    description: 'Powered by Telegram',
  },
];

export function TrustBadgeBar({ 
  variant = 'compact', 
  showLabels = true,
  className = '' 
}: TrustBadgeBarProps) {
  const containerClass = `${styles.container} ${styles[variant]} ${className}`;

  if (variant === 'compact') {
    return (
      <div className={containerClass} role="region" aria-label="Trust indicators">
        {defaultBadges.map((badge) => (
          <div 
            key={badge.id} 
            className={`${styles.badge} ${styles[`badge${badge.id.charAt(0).toUpperCase() + badge.id.slice(1)}`]}`} 
            title={badge.description}
          >
            <span className={styles.icon} aria-hidden="true">{badge.icon}</span>
            {showLabels && <span className={styles.label}>{badge.label}</span>}
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className={containerClass} role="region" aria-label="Trust indicators">
      {defaultBadges.map((badge) => (
        <div key={badge.id} className={styles.badgeFull}>
          <div className={styles.badgeHeader}>
            <span className={styles.iconFull} aria-hidden="true">{badge.icon}</span>
            {showLabels && <span className={styles.labelFull}>{badge.label}</span>}
          </div>
          {badge.description && (
            <p className={styles.description}>{badge.description}</p>
          )}
        </div>
      ))}
    </div>
  );
}
