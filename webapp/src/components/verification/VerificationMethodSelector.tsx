import React from 'react';
import { VerificationMethod, VerificationMethodOption } from './types';
import { Button } from '../ui';
import styles from './VerificationMethodSelector.module.css';

interface VerificationMethodSelectorProps {
  methods: VerificationMethodOption[];
  onSelect: (method: VerificationMethod) => void;
  loading?: boolean;
  disabled?: boolean;
}

export function VerificationMethodSelector({
  methods,
  onSelect,
  loading = false,
  disabled = false,
}: VerificationMethodSelectorProps) {
  const recommendedMethod = methods.find(m => m.recommended);

  return (
    <div className={styles.container}>
      <div className={styles.header}>
        <h3 className={styles.title}>Choose Verification Method</h3>
        <p className={styles.description}>
          Select how you'd like to verify wallet ownership. We recommend message signing for the fastest verification.
        </p>
      </div>

      <div className={styles.methodsList}>
        {methods.map((method) => (
          <button
            key={method.id}
            className={`${styles.methodCard} ${
              method.recommended ? styles.recommended : ''
            } ${!method.available ? styles.unavailable : ''}`}
            onClick={() => method.available && !disabled && onSelect(method.id)}
            disabled={!method.available || disabled || loading}
            aria-label={`Select ${method.name} verification method`}
          >
            <div className={styles.methodIcon}>{method.icon}</div>
            <div className={styles.methodContent}>
              <div className={styles.methodHeader}>
                <h4 className={styles.methodName}>{method.name}</h4>
                {method.recommended && (
                  <span className={styles.recommendedBadge} aria-label="Recommended method">
                    Recommended
                  </span>
                )}
                {!method.available && (
                  <span className={styles.unavailableBadge}>Unavailable</span>
                )}
              </div>
              <p className={styles.methodDescription}>{method.description}</p>
              <div className={styles.methodMeta}>
                <span className={styles.estimatedTime}>
                  ⏱️ Estimated time: {method.estimatedTime}
                </span>
                {method.requirements && method.requirements.length > 0 && (
                  <div className={styles.requirements}>
                    <strong>Requirements:</strong>
                    <ul>
                      {method.requirements.map((req, idx) => (
                        <li key={idx}>{req}</li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            </div>
            <div className={styles.methodArrow} aria-hidden="true">
              →
            </div>
          </button>
        ))}
      </div>

      {recommendedMethod && (
        <div className={styles.recommendedNote}>
          <span className={styles.noteIcon}>💡</span>
          <span>
            <strong>{recommendedMethod.name}</strong> is recommended for most users as it's the fastest and most secure method.
          </span>
        </div>
      )}
    </div>
  );
}

