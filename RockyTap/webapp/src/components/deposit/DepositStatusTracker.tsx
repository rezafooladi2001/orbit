import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button } from '../ui';
import { CheckIcon, ClockIcon, XIcon } from '../Icons';
import { getDepositStatus, DepositStatus } from '../../api/client';
import styles from './DepositStatusTracker.module.css';

interface DepositStatusTrackerProps {
  depositId: number;
  onComplete?: () => void;
  onError?: () => void;
}

export function DepositStatusTracker({ depositId, onComplete, onError }: DepositStatusTrackerProps) {
  const [status, setStatus] = useState<DepositStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let interval: ReturnType<typeof setInterval> | null = null;

    const checkStatus = async () => {
      try {
        const depositStatus = await getDepositStatus(depositId);
        setStatus(depositStatus);
        setLoading(false);

        if (depositStatus.status === 'confirmed') {
          if (interval) clearInterval(interval);
          if (onComplete) onComplete();
        } else if (depositStatus.status === 'failed') {
          if (interval) clearInterval(interval);
          if (onError) onError();
        }
      } catch (err) {
        setError((err as Error).message);
        setLoading(false);
        if (interval) clearInterval(interval);
      }
    };

    // Check immediately
    checkStatus();

    // Then check every 10 seconds
    interval = setInterval(checkStatus, 10000);

    return () => {
      if (interval) clearInterval(interval);
    };
  }, [depositId, onComplete, onError]);

  if (loading && !status) {
    return (
      <Card>
        <CardContent>
          <div className={styles.loadingState}>
            <div className={styles.spinner} />
            <p>Checking deposit status...</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card>
        <CardContent>
          <div className={styles.errorState}>
            <XIcon size={24} color="var(--error)" />
            <p>{error}</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!status) {
    return null;
  }

  const getStatusIcon = () => {
    switch (status.status) {
      case 'confirmed':
        return <CheckIcon size={24} color="var(--success)" />;
      case 'pending':
        return <ClockIcon size={24} color="var(--warning)" />;
      case 'failed':
        return <XIcon size={24} color="var(--error)" />;
      default:
        return <ClockIcon size={24} color="var(--text-muted)" />;
    }
  };

  const getStatusText = () => {
    switch (status.status) {
      case 'confirmed':
        return 'Deposit Confirmed';
      case 'pending':
        return 'Waiting for Confirmation';
      case 'failed':
        return 'Deposit Failed';
      default:
        return 'Processing';
    }
  };

  return (
    <Card variant="elevated">
      <CardHeader>
        <CardTitle>Deposit Status</CardTitle>
      </CardHeader>
      <CardContent>
        <div className={styles.statusContainer}>
          <div className={styles.statusHeader}>
            {getStatusIcon()}
            <h3 className={styles.statusTitle}>{getStatusText()}</h3>
          </div>

          {status.tx_hash && (
            <div className={styles.statusDetail}>
              <span className={styles.detailLabel}>Transaction Hash:</span>
              <code className={styles.txHash}>{status.tx_hash}</code>
            </div>
          )}

          {status.confirmations !== undefined && (
            <div className={styles.statusDetail}>
              <span className={styles.detailLabel}>Confirmations:</span>
              <span className={styles.detailValue}>{status.confirmations}</span>
            </div>
          )}

          {status.actual_amount_usdt && (
            <div className={styles.statusDetail}>
              <span className={styles.detailLabel}>Amount Received:</span>
              <span className={styles.detailValue}>{status.actual_amount_usdt} USDT</span>
            </div>
          )}

          {status.status === 'pending' && (
            <div className={styles.pendingNote}>
              <p>Your deposit is being processed. This may take a few minutes.</p>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

