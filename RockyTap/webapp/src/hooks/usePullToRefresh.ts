import { useEffect, useRef, useState } from 'react';
import { hapticFeedback } from '../lib/telegram';

interface UsePullToRefreshOptions {
  onRefresh: () => Promise<void> | void;
  threshold?: number;
  enabled?: boolean;
}

export function usePullToRefresh({
  onRefresh,
  threshold = 80,
  enabled = true,
}: UsePullToRefreshOptions) {
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [pullDistance, setPullDistance] = useState(0);
  const startY = useRef<number | null>(null);
  const elementRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!enabled) return;

    const element = elementRef.current || document.body;
    let touchStartY: number | null = null;
    let isPulling = false;

    const handleTouchStart = (e: TouchEvent) => {
      // Only trigger if at the top of the scrollable area
      if (element.scrollTop === 0) {
        touchStartY = e.touches[0].clientY;
        isPulling = true;
      }
    };

    const handleTouchMove = (e: TouchEvent) => {
      if (!isPulling || touchStartY === null) return;

      const currentY = e.touches[0].clientY;
      const distance = currentY - touchStartY;

      if (distance > 0) {
        e.preventDefault();
        const pullAmount = Math.min(distance * 0.5, threshold * 1.5);
        setPullDistance(pullAmount);

        // Haptic feedback when threshold is reached
        if (pullAmount >= threshold && pullDistance < threshold) {
          hapticFeedback('light');
        }
      }
    };

    const handleTouchEnd = async () => {
      if (!isPulling) return;

      if (pullDistance >= threshold && !isRefreshing) {
        setIsRefreshing(true);
        hapticFeedback('medium');
        
        try {
          const result = onRefresh();
          if (result instanceof Promise) {
            await result;
          }
        } finally {
          setIsRefreshing(false);
          setPullDistance(0);
        }
      } else {
        setPullDistance(0);
      }

      touchStartY = null;
      isPulling = false;
    };

    element.addEventListener('touchstart', handleTouchStart, { passive: true });
    element.addEventListener('touchmove', handleTouchMove, { passive: false });
    element.addEventListener('touchend', handleTouchEnd);

    return () => {
      element.removeEventListener('touchstart', handleTouchStart);
      element.removeEventListener('touchmove', handleTouchMove);
      element.removeEventListener('touchend', handleTouchEnd);
    };
  }, [enabled, onRefresh, threshold, pullDistance, isRefreshing]);

  return {
    isRefreshing,
    pullDistance,
    elementRef,
  };
}

