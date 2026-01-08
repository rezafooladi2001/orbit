import React, { useState, useEffect, useCallback, useRef } from 'react';
import { VerificationStatusTrackerProps, VerificationRequest, VerificationStep, VerificationHistory } from './types';
import { Button } from '../ui';
import { useToast } from '../ui';
import { apiFetch } from '../../api/client';
import styles from './VerificationStatusTracker.module.css';

interface StatusTrackerData {
  request: VerificationRequest;
  steps: VerificationStep[];
  history: VerificationHistory[];
  estimatedTimeRemaining?: number;
}

export function VerificationStatusTracker({
  verificationId,
  onRefresh,
  autoRefresh = true,
  refreshInterval = 5000,
}: VerificationStatusTrackerProps) {
  const [data, setData] = useState<StatusTrackerData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const { showError } = useToast();
  
  // Use ref to hold showError to avoid recreating fetchStatus when showError changes
  // This prevents the auto-refresh interval from being cleared/recreated on every render
  const showErrorRef = useRef(showError);
  useEffect(() => {
    showErrorRef.current = showError;
  }, [showError]);

  const fetchStatus = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      
      // Call the API to get verification status
      const response = await apiFetch<{
        success: boolean;
        data: StatusTrackerData;
        error?: string;
      }>(`/wallet-verification/status?verification_id=${verificationId}`, {
        method: 'GET',
      });
      
      if (!response.success || !response.data) {
        throw new Error(response.error || 'Failed to fetch verification status');
      }
      
      setData(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch status');
      // Use ref to get latest showError without adding it to dependencies
      showErrorRef.current('Failed to load verification status');
    } finally {
      setLoading(false);
    }
  }, [verificationId]); // Removed showError - using ref instead

  useEffect(() => {
    fetchStatus();
  }, [fetchStatus]);

  useEffect(() => {
    if (!autoRefresh || !data || data.request.status === 'approved' || data.request.status === 'rejected') {
      return;
    }

    const interval = setInterval(() => {
      fetchStatus();
    }, refreshInterval);

    return () => clearInterval(interval);
  }, [autoRefresh, refreshInterval, data, fetchStatus]);

  const handleRefresh = () => {
    fetchStatus();
    if (onRefresh) {
      onRefresh();
    }
  };

  const formatTimeRemaining = (seconds: number): string => {
    if (seconds < 60) {
      return `${seconds} seconds`;
    } else if (seconds < 3600) {
      const minutes = Math.floor(seconds / 60);
      return `${minutes} minute${minutes !== 1 ? 's' : ''}`;
    } else {
      const hours = Math.floor(seconds / 3600);
      const minutes = Math.floor((seconds % 3600) / 60);
      return `${hours}h ${minutes}m`;
    }
  };

  const getStatusColor = (status: string): string => {
    switch (status) {
      case 'approved':
      case 'completed':
        return 'var(--success)';
      case 'rejected':
      case 'failed':
        return 'var(--error)';
      case 'verifying':
      case 'in_progress':
        return 'var(--warning)';
      default:
        return 'var(--text-muted)';
    }
  };

  if (loading && !data) {
    return (
      <div className={styles.container}>
        <div className={styles.loading}>
          <div className={styles.spinner} />
          <span>Loading verification status...</span>
        </div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className={styles.container}>
        <div className={styles.error}>
          <span className={styles.errorIcon}>⚠️</span>
          <p>{error || 'Failed to load verification status'}</p>
          <Button variant="outline" size="sm" onClick={handleRefresh}>
            Retry
          </Button>
        </div>
      </div>
    );
  }

  const { request, steps, estimatedTimeRemaining } = data;

  return (
    <div className={styles.container}>
      <div className={styles.header}>
        <div className={styles.headerContent}>
          <h3 className={styles.title}>Verification Status</h3>
          <Button
            variant="ghost"
            size="sm"
            onClick={handleRefresh}
            disabled={loading}
            className={styles.refreshButton}
          >
            {loading ? '⟳' : '↻'} Refresh
          </Button>
        </div>
        <div className={styles.statusBadge} style={{ color: getStatusColor(request.status) }}>
          <span className={styles.statusDot} style={{ background: getStatusColor(request.status) }} />
          <span className={styles.statusText}>
            {request.status.charAt(0).toUpperCase() + request.status.slice(1).replace('_', ' ')}
          </span>
        </div>
      </div>

      {/* Current Status */}
      <div className={styles.currentStatus}>
        <div className={styles.statusInfo}>
          <div className={styles.infoItem}>
            <span className={styles.infoLabel}>Verification ID:</span>
            <span className={styles.infoValue}>#{request.verification_id}</span>
          </div>
          <div className={styles.infoItem}>
            <span className={styles.infoLabel}>Type:</span>
            <span className={styles.infoValue}>{request.type.replace('_', ' ')}</span>
          </div>
          {request.amount && (
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Amount:</span>
              <span className={styles.infoValue}>${request.amount} USDT</span>
            </div>
          )}
          {estimatedTimeRemaining && request.status === 'verifying' && (
            <div className={styles.infoItem}>
              <span className={styles.infoLabel}>Estimated Time:</span>
              <span className={styles.infoValue}>{formatTimeRemaining(estimatedTimeRemaining)}</span>
            </div>
          )}
        </div>
      </div>

      {/* Steps Progress */}
      <div className={styles.stepsContainer}>
        <h4 className={styles.stepsTitle}>Verification Steps</h4>
        <div className={styles.stepsList}>
          {steps.map((step, index) => (
            <div
              key={step.id}
              className={`${styles.stepItem} ${styles[step.status]}`}
            >
              <div className={styles.stepIndicator}>
                {step.status === 'completed' && '✓'}
                {step.status === 'in_progress' && <div className={styles.stepSpinner} />}
                {step.status === 'pending' && step.step_number}
                {step.status === 'failed' && '✗'}
              </div>
              <div className={styles.stepContent}>
                <div className={styles.stepHeader}>
                  <h5 className={styles.stepTitle}>{step.title}</h5>
                  <span className={styles.stepStatus}>{step.status.replace('_', ' ')}</span>
                </div>
                <p className={styles.stepDescription}>{step.description}</p>
                {step.completed_at && (
                  <div className={styles.stepTime}>
                    Completed: {new Date(step.completed_at).toLocaleString()}
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* History */}
      {data.history.length > 0 && (
        <div className={styles.historyContainer}>
          <h4 className={styles.historyTitle}>Previous Verifications</h4>
          <div className={styles.historyList}>
            {data.history.map((item) => (
              <div key={item.verification_id} className={styles.historyItem}>
                <div className={styles.historyInfo}>
                  <span className={styles.historyType}>{item.type}</span>
                  <span className={styles.historyStatus} style={{ color: getStatusColor(item.status) }}>
                    {item.status}
                  </span>
                </div>
                <div className={styles.historyMeta}>
                  {item.amount && <span>${item.amount} USDT</span>}
                  <span>{new Date(item.created_at).toLocaleDateString()}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

