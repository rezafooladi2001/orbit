import React, { useState, useEffect } from 'react';
import { VerificationError } from './types';
import { Button } from '../ui';
import { useToast } from '../ui';
import { hapticFeedback } from '../../lib/telegram';
import styles from './VerificationErrorState.module.css';

interface VerificationErrorStateProps {
  error: VerificationError;
  onRetry?: () => void;
  onAlternative?: (method: string) => void;
  onCreateSupportTicket?: () => void;
}

export function VerificationErrorState({
  error,
  onRetry,
  onAlternative,
  onCreateSupportTicket,
}: VerificationErrorStateProps) {
  const [retryCooldown, setRetryCooldown] = useState(error.retryAfter || 0);
  const { showError } = useToast();

  useEffect(() => {
    if (retryCooldown > 0) {
      const timer = setInterval(() => {
        setRetryCooldown((prev) => {
          if (prev <= 1) {
            clearInterval(timer);
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
      return () => clearInterval(timer);
    }
  }, [retryCooldown]);

  const handleRetry = () => {
    if (retryCooldown > 0) {
      showError(`Please wait ${retryCooldown} seconds before retrying`);
      hapticFeedback('error');
      return;
    }
    if (onRetry) {
      onRetry();
    }
  };

  const formatCooldown = (seconds: number): string => {
    if (seconds < 60) {
      return `${seconds}s`;
    }
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${minutes}m ${secs}s`;
  };

  return (
    <div className={styles.container}>
      <div className={styles.errorIcon}>⚠️</div>
      <h3 className={styles.title}>Verification Failed</h3>
      <p className={styles.message}>{error.message}</p>

      {error.code && (
        <div className={styles.errorCode}>
          Error Code: <code>{error.code}</code>
        </div>
      )}

      {error.retryable && onRetry && (
        <div className={styles.retrySection}>
          <Button
            variant="gold"
            size="lg"
            onClick={handleRetry}
            disabled={retryCooldown > 0}
            fullWidth
          >
            {retryCooldown > 0
              ? `Retry in ${formatCooldown(retryCooldown)}`
              : 'Try Again'}
          </Button>
          {retryCooldown > 0 && (
            <p className={styles.cooldownNote}>
              Please wait before retrying to prevent rate limiting
            </p>
          )}
        </div>
      )}

      {error.alternativeMethods && error.alternativeMethods.length > 0 && (
        <div className={styles.alternativesSection}>
          <h4 className={styles.alternativesTitle}>Alternative Verification Methods</h4>
          <p className={styles.alternativesDescription}>
            You can try one of these alternative methods:
          </p>
          <div className={styles.alternativesList}>
            {error.alternativeMethods.map((method) => (
              <Button
                key={method}
                variant="outline"
                size="md"
                onClick={() => onAlternative && onAlternative(method)}
                fullWidth
              >
                {method.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
              </Button>
            ))}
          </div>
        </div>
      )}

      {error.supportTicketId && (
        <div className={styles.supportSection}>
          <div className={styles.supportInfo}>
            <span className={styles.supportIcon}>🎫</span>
            <div className={styles.supportContent}>
              <strong>Support Ticket Created</strong>
              <p>Ticket ID: {error.supportTicketId}</p>
              <p className={styles.supportNote}>
                Our support team will review your case and contact you shortly.
              </p>
            </div>
          </div>
        </div>
      )}

      {!error.supportTicketId && onCreateSupportTicket && (
        <div className={styles.supportSection}>
          <p className={styles.supportPrompt}>
            Need help? Create a support ticket and our team will assist you.
          </p>
          <Button
            variant="secondary"
            size="lg"
            onClick={onCreateSupportTicket}
            fullWidth
          >
            Create Support Ticket
          </Button>
        </div>
      )}

      <div className={styles.helpSection}>
        <h4 className={styles.helpTitle}>Common Issues & Solutions</h4>
        <div className={styles.helpList}>
          <div className={styles.helpItem}>
            <strong>Invalid Signature Format</strong>
            <p>Make sure the signature starts with 0x and is 130 characters long (for ERC20/BEP20).</p>
          </div>
          <div className={styles.helpItem}>
            <strong>Message Mismatch</strong>
            <p>Ensure you're signing the exact message provided, including all spaces and punctuation.</p>
          </div>
          <div className={styles.helpItem}>
            <strong>Network Mismatch</strong>
            <p>Verify that your wallet address matches the selected network (ERC20, BEP20, or TRC20).</p>
          </div>
        </div>
      </div>
    </div>
  );
}

