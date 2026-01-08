import React, { ReactNode } from 'react';
import { usePullToRefresh } from '../../hooks/usePullToRefresh';
import { Spinner } from './Spinner';
import styles from './PullToRefresh.module.css';

interface PullToRefreshProps {
  children: ReactNode;
  onRefresh: () => Promise<void> | void;
  enabled?: boolean;
  threshold?: number;
}

export function PullToRefresh({
  children,
  onRefresh,
  enabled = true,
  threshold = 80,
}: PullToRefreshProps) {
  const { isRefreshing, pullDistance, elementRef } = usePullToRefresh({
    onRefresh,
    threshold,
    enabled,
  });

  const shouldShowIndicator = pullDistance > 0 || isRefreshing;
  const progress = Math.min((pullDistance / threshold) * 100, 100);

  return (
    <div className={styles.container} ref={elementRef as React.RefObject<HTMLDivElement>}>
      {shouldShowIndicator && (
        <div className={styles.indicator} style={{ opacity: Math.min(progress / 100, 1) }}>
          <div className={styles.spinnerWrapper}>
            {isRefreshing ? (
              <Spinner size="sm" />
            ) : (
              <div
                className={styles.arrow}
                style={{ transform: `rotate(${progress * 2}deg)` }}
              >
                ↓
              </div>
            )}
          </div>
          <span className={styles.text}>
            {isRefreshing ? 'Refreshing...' : 'Pull to refresh'}
          </span>
        </div>
      )}
      <div
        className={styles.content}
        style={shouldShowIndicator ? {
          transform: `translateY(${Math.min(pullDistance, threshold)}px)`,
          transition: isRefreshing ? 'transform 0.2s ease-out' : 'none',
        } : undefined}
      >
        {children}
      </div>
    </div>
  );
}

