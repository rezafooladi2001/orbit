import React from 'react';
import { ComplianceBadge, SecurityEducationProps } from './types';
import styles from './ComplianceBadges.module.css';

const defaultBadges: ComplianceBadge[] = [
  {
    id: 'aml',
    name: 'AML Compliant',
    description: 'Complies with Anti-Money Laundering regulations',
    icon: '🛡️',
    verified: true,
  },
  {
    id: 'kyc',
    name: 'KYC Ready',
    description: 'Know Your Customer verification available',
    icon: '✅',
    verified: true,
  },
  {
    id: 'encryption',
    name: 'End-to-End Encryption',
    description: 'All data is encrypted in transit and at rest',
    icon: '🔐',
    verified: true,
  },
  {
    id: 'audit',
    name: 'Regular Audits',
    description: 'Security audits conducted regularly',
    icon: '📋',
    verified: true,
  },
  {
    id: 'gdpr',
    name: 'GDPR Compliant',
    description: 'General Data Protection Regulation compliant',
    icon: '🇪🇺',
    verified: true,
  },
];

export function ComplianceBadges({ type = 'badges', compact = false }: SecurityEducationProps) {
  if (compact) {
    return (
      <div className={styles.compactContainer}>
        <div className={styles.compactBadges}>
          {defaultBadges.slice(0, 3).map((badge) => (
            <div key={badge.id} className={styles.compactBadge}>
              <span className={styles.compactIcon}>{badge.icon}</span>
              <span className={styles.compactName}>{badge.name}</span>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className={styles.container}>
      <div className={styles.header}>
        <h3 className={styles.title}>Compliance & Security</h3>
        <p className={styles.description}>
          Ghidar is committed to maintaining the highest standards of security and compliance
        </p>
      </div>

      <div className={styles.badgesGrid}>
        {defaultBadges.map((badge) => (
          <div
            key={badge.id}
            className={`${styles.badgeCard} ${
              badge.verified ? styles.verified : ''
            }`}
          >
            <div className={styles.badgeIcon}>{badge.icon}</div>
            <div className={styles.badgeContent}>
              <h4 className={styles.badgeName}>
                {badge.name}
                {badge.verified && (
                  <span className={styles.verifiedCheck} aria-label="Verified">✓</span>
                )}
              </h4>
              <p className={styles.badgeDescription}>{badge.description}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

