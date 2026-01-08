import { useState, useEffect } from 'react';
import { useNetworkStatus } from '../../hooks/useNetworkStatus';
import { WarningIcon } from '../Icons';
import styles from './OfflineBanner.module.css';

export function OfflineBanner() {
  const isOnline = useNetworkStatus();
  const [wasOffline, setWasOffline] = useState(false);
  const [showReconnected, setShowReconnected] = useState(false);

  useEffect(() => {
    if (!isOnline) {
      setWasOffline(true);
    } else if (wasOffline) {
      // Show "reconnected" message briefly
      setShowReconnected(true);
      const timer = setTimeout(() => {
        setShowReconnected(false);
        setWasOffline(false);
      }, 3000);
      return () => clearTimeout(timer);
    }
  }, [isOnline, wasOffline]);

  if (!isOnline) {
    return (
      <div 
        className={styles.banner} 
        role="alert" 
        aria-live="assertive"
      >
        <WarningIcon size={18} color="var(--warning)" />
        <span className={styles.message}>
          You're offline. Actions will be synced when you reconnect.
        </span>
      </div>
    );
  }

  if (showReconnected) {
    return (
      <div 
        className={`${styles.banner} ${styles.reconnected}`}
        role="status"
        aria-live="polite"
      >
        <span className={styles.message}>
          ✓ Back online! Syncing your data...
        </span>
      </div>
    );
  }

  return null;
}

