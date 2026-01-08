import { Suspense, ReactNode } from 'react';
import { SkeletonCard } from './SkeletonLoader';
import styles from './LazyScreen.module.css';

interface LazyScreenProps {
  children: ReactNode;
  fallback?: ReactNode;
}

export function LazyScreen({ children, fallback }: LazyScreenProps) {
  return (
    <Suspense fallback={fallback || <DefaultFallback />}>
      {children}
    </Suspense>
  );
}

function DefaultFallback() {
  return (
    <div className={styles.fallbackContainer}>
      <SkeletonCard />
      <SkeletonCard />
      <SkeletonCard />
    </div>
  );
}

