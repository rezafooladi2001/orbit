import React from 'react';
import styles from './TelegramBranding.module.css';

interface TelegramBrandingProps {
  variant?: 'badge' | 'text' | 'full';
  showIcon?: boolean;
  className?: string;
  linkToDocs?: boolean;
}

export function TelegramBranding({ 
  variant = 'text', 
  showIcon = true,
  className = '',
  linkToDocs = false 
}: TelegramBrandingProps) {
  const content = (
    <>
      {showIcon && (
        <span className={styles.icon} aria-hidden="true">
          ⚡
        </span>
      )}
      <span className={styles.textContent}>Powered by Telegram</span>
    </>
  );

  const baseClassName = `${styles.branding} ${styles[variant]} ${className}`;

  if (linkToDocs) {
    return (
      <a
        href="https://core.telegram.org/bots/webapps"
        target="_blank"
        rel="noopener noreferrer"
        className={baseClassName}
        aria-label="Learn more about Telegram Mini Apps"
      >
        {content}
      </a>
    );
  }

  return (
    <div className={baseClassName} aria-label="Powered by Telegram">
      {content}
    </div>
  );
}

