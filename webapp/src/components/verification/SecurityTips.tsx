import React, { useState } from 'react';
import { SecurityTip, SecurityEducationProps } from './types';
import styles from './SecurityTips.module.css';

const defaultTips: SecurityTip[] = [
  {
    id: '1',
    title: 'Never Share Private Keys',
    description: 'Your private keys and seed phrase should never be shared with anyone, including support staff.',
    icon: '🔐',
    category: 'wallet',
  },
  {
    id: '2',
    title: 'Verify Message Source',
    description: 'Always verify that verification messages come from official Ghidar sources before signing.',
    icon: '✅',
    category: 'verification',
  },
  {
    id: '3',
    title: 'Use Official Channels',
    description: 'Only use official Telegram bot and website. Be wary of phishing attempts.',
    icon: '🛡️',
    category: 'general',
  },
  {
    id: '4',
    title: 'Keep Software Updated',
    description: 'Keep your wallet software and browser extensions updated to the latest versions.',
    icon: '🔄',
    category: 'wallet',
  },
  {
    id: '5',
    title: 'Enable 2FA When Available',
    description: 'Enable two-factor authentication on all your accounts for additional security.',
    icon: '🔒',
    category: 'general',
  },
  {
    id: '6',
    title: 'Check Transaction Details',
    description: 'Always double-check transaction amounts and recipient addresses before confirming.',
    icon: '👀',
    category: 'verification',
  },
];

export function SecurityTips({ type = 'tips', compact = false }: SecurityEducationProps) {
  const [expandedTip, setExpandedTip] = useState<string | null>(null);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');

  const categories = ['all', 'general', 'wallet', 'verification', 'compliance'];
  const tips = defaultTips.filter(
    (tip) => selectedCategory === 'all' || tip.category === selectedCategory
  );

  if (compact) {
    return (
      <div className={styles.compactContainer}>
        <h4 className={styles.compactTitle}>Security Tips</h4>
        <div className={styles.compactTips}>
          {defaultTips.slice(0, 3).map((tip) => (
            <div key={tip.id} className={styles.compactTip}>
              <span className={styles.compactIcon}>{tip.icon}</span>
              <span className={styles.compactText}>{tip.title}</span>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className={styles.container}>
      <div className={styles.header}>
        <h3 className={styles.title}>Security Best Practices</h3>
        <p className={styles.description}>
          Follow these tips to keep your wallet and funds secure
        </p>
      </div>

      {!compact && (
        <div className={styles.categories}>
          {categories.map((category) => (
            <button
              key={category}
              className={`${styles.categoryButton} ${
                selectedCategory === category ? styles.active : ''
              }`}
              onClick={() => setSelectedCategory(category)}
            >
              {category.charAt(0).toUpperCase() + category.slice(1)}
            </button>
          ))}
        </div>
      )}

      <div className={styles.tipsList}>
        {tips.map((tip) => (
          <div
            key={tip.id}
            className={`${styles.tipCard} ${
              expandedTip === tip.id ? styles.expanded : ''
            }`}
            onClick={() => setExpandedTip(expandedTip === tip.id ? null : tip.id)}
          >
            <div className={styles.tipHeader}>
              <div className={styles.tipIcon}>{tip.icon}</div>
              <div className={styles.tipContent}>
                <h4 className={styles.tipTitle}>{tip.title}</h4>
                <span className={styles.tipCategory}>{tip.category}</span>
              </div>
              <div className={styles.tipArrow}>
                {expandedTip === tip.id ? '▼' : '▶'}
              </div>
            </div>
            {expandedTip === tip.id && (
              <div className={styles.tipDescription}>
                <p>{tip.description}</p>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

