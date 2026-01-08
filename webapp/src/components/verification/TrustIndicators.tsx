import React from 'react';
import { TrustIndicator, SecurityEducationProps } from './types';
import styles from './TrustIndicators.module.css';

const defaultIndicators: TrustIndicator[] = [
  {
    id: 'telegram',
    label: 'Powered by Telegram',
    icon: '⚡',
    description: 'Built on Telegram\'s secure platform with built-in authentication',
  },
  {
    id: 'telegram-verified',
    label: 'Telegram Verified',
    icon: '✅',
    description: 'Verified Telegram Mini App with Telegram\'s security standards',
  },
  {
    id: 'ssl',
    label: 'SSL Encrypted',
    icon: '🔒',
    description: 'All connections are secured with SSL encryption',
  },
  {
    id: 'aml',
    label: 'AML Compliant',
    icon: '🛡️',
    description: 'Anti-Money Laundering compliant',
  },
  {
    id: 'fraud',
    label: 'Fraud Protected',
    icon: '🛡️',
    description: 'Advanced fraud detection and prevention',
  },
  {
    id: 'audit',
    label: 'Regular Audits',
    icon: '📋',
    description: 'Regular security audits and compliance checks',
  },
];

export function TrustIndicators({ type = 'indicators', compact = false }: SecurityEducationProps) {
  if (compact) {
    return (
      <div className={styles.compactContainer}>
        <div className={styles.compactIndicators}>
          {defaultIndicators.map((indicator) => (
            <div key={indicator.id} className={styles.compactIndicator}>
              <span className={styles.compactIcon}>{indicator.icon}</span>
              <span className={styles.compactLabel}>{indicator.label}</span>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className={styles.container}>
      <div className={styles.indicatorsList}>
        {defaultIndicators.map((indicator) => (
          <div key={indicator.id} className={styles.indicatorItem}>
            <div className={styles.indicatorIcon}>{indicator.icon}</div>
            <div className={styles.indicatorContent}>
              <h4 className={styles.indicatorLabel}>{indicator.label}</h4>
              {indicator.description && (
                <p className={styles.indicatorDescription}>{indicator.description}</p>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

