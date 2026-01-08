import React from 'react';
import styles from './EmptyState.module.css';

interface EmptyStateProps {
  icon?: React.ReactNode | string;
  message: string;
  action?: React.ReactNode;
}

export function EmptyState({ icon = '📭', message, action }: EmptyStateProps) {
  return (
    <div 
      className={styles.container}
      role="status"
      aria-label={message}
    >
      <div className={styles.iconWrapper} aria-hidden="true">
        {typeof icon === 'string' ? icon : icon}
      </div>
      <p className={styles.message}>{message}</p>
      {action && <div className={styles.action}>{action}</div>}
    </div>
  );
}
