import styles from './SkeletonLoader.module.css';

interface SkeletonLoaderProps {
  width?: string;
  height?: string;
  variant?: 'text' | 'circular' | 'rectangular' | 'rounded';
  className?: string;
  lines?: number;
  animate?: boolean;
}

export function SkeletonLoader({
  width,
  height,
  variant = 'rectangular',
  className = '',
  lines = 1,
  animate = true,
}: SkeletonLoaderProps) {
  if (variant === 'text' && lines > 1) {
    return (
      <div className={className} role="status" aria-label="Loading content">
        {Array.from({ length: lines }).map((_, index) => (
          <div
            key={index}
            className={`${styles.skeleton} ${styles.text} ${animate ? styles.animate : ''}`}
            style={{
              width: index === lines - 1 ? '70%' : index % 2 === 0 ? '100%' : '90%',
              height: height || '1em',
              marginBottom: index < lines - 1 ? '0.5em' : '0',
              animationDelay: `${index * 0.05}s`,
            }}
          />
        ))}
        <span className={styles.srOnly}>Loading...</span>
      </div>
    );
  }

  const computedHeight = height || (variant === 'circular' ? (width || '40px') : '20px');

  return (
    <div
      className={`${styles.skeleton} ${styles[variant]} ${animate ? styles.animate : ''} ${className}`}
      style={{
        width: width || '100%',
        height: computedHeight,
      }}
      aria-label="Loading..."
      role="status"
    >
      <span className={styles.srOnly}>Loading...</span>
    </div>
  );
}

// Preset skeleton components for common use cases
export function SkeletonCard() {
  return (
    <div className={styles.skeletonCard}>
      <SkeletonLoader variant="rectangular" height="120px" />
      <div className={styles.skeletonContent}>
        <SkeletonLoader variant="text" width="60%" height="1.2em" />
        <SkeletonLoader variant="text" width="40%" height="1em" />
        <SkeletonLoader variant="text" width="80%" height="1em" />
      </div>
    </div>
  );
}

export function SkeletonWalletSummary() {
  return (
    <div className={styles.walletSkeleton}>
      <div className={styles.walletHeader}>
        <SkeletonLoader variant="rounded" width="80px" height="24px" />
        <SkeletonLoader variant="text" width="120px" height="32px" />
      </div>
      <div className={styles.walletBalances}>
        <div className={styles.balanceItem}>
          <SkeletonLoader variant="circular" width="40px" height="40px" />
          <div className={styles.balanceText}>
            <SkeletonLoader variant="text" width="60px" height="12px" />
            <SkeletonLoader variant="text" width="90px" height="20px" />
          </div>
        </div>
        <div className={styles.balanceItem}>
          <SkeletonLoader variant="circular" width="40px" height="40px" />
          <div className={styles.balanceText}>
            <SkeletonLoader variant="text" width="60px" height="12px" />
            <SkeletonLoader variant="text" width="90px" height="20px" />
          </div>
        </div>
      </div>
      <div className={styles.walletActions}>
        <SkeletonLoader variant="rounded" width="48%" height="44px" />
        <SkeletonLoader variant="rounded" width="48%" height="44px" />
      </div>
    </div>
  );
}

export function SkeletonFeatureCard() {
  return (
    <div className={styles.featureCardSkeleton}>
      <div className={styles.featureCardIcon}>
        <SkeletonLoader variant="circular" width="48px" height="48px" />
      </div>
      <div className={styles.featureCardContent}>
        <SkeletonLoader variant="text" width="100px" height="18px" />
        <SkeletonLoader variant="text" width="140px" height="14px" />
      </div>
      <SkeletonLoader variant="circular" width="24px" height="24px" />
    </div>
  );
}

export function SkeletonStatRow() {
  return (
    <div className={styles.statRowSkeleton}>
      <div className={styles.statItem}>
        <SkeletonLoader variant="text" width="40px" height="12px" />
        <SkeletonLoader variant="text" width="60px" height="20px" />
      </div>
      <div className={styles.statDivider} />
      <div className={styles.statItem}>
        <SkeletonLoader variant="text" width="40px" height="12px" />
        <SkeletonLoader variant="text" width="60px" height="20px" />
      </div>
      <div className={styles.statDivider} />
      <div className={styles.statItem}>
        <SkeletonLoader variant="text" width="40px" height="12px" />
        <SkeletonLoader variant="text" width="60px" height="20px" />
      </div>
    </div>
  );
}

export function SkeletonListItem() {
  return (
    <div className={styles.listItemSkeleton}>
      <SkeletonLoader variant="circular" width="44px" height="44px" />
      <div className={styles.listItemContent}>
        <SkeletonLoader variant="text" width="120px" height="16px" />
        <SkeletonLoader variant="text" width="80px" height="13px" />
      </div>
      <SkeletonLoader variant="text" width="60px" height="16px" />
    </div>
  );
}

export function SkeletonTransactionList({ count = 5 }: { count?: number }) {
  return (
    <div className={styles.transactionListSkeleton}>
      {Array.from({ length: count }).map((_, index) => (
        <SkeletonListItem key={index} />
      ))}
    </div>
  );
}

export function SkeletonAirdropScreen() {
  return (
    <div className={styles.airdropSkeleton}>
      <div className={styles.airdropHeader}>
        <SkeletonLoader variant="text" width="120px" height="24px" />
        <SkeletonLoader variant="text" width="80px" height="14px" />
      </div>
      <div className={styles.airdropCoin}>
        <SkeletonLoader variant="circular" width="180px" height="180px" />
      </div>
      <div className={styles.airdropStats}>
        <SkeletonLoader variant="rounded" width="100%" height="80px" />
      </div>
      <SkeletonLoader variant="rounded" width="100%" height="56px" />
    </div>
  );
}

export function SkeletonLotteryScreen() {
  return (
    <div className={styles.lotterySkeleton}>
      <div className={styles.lotteryHeader}>
        <SkeletonLoader variant="rounded" width="100%" height="120px" />
      </div>
      <SkeletonStatRow />
      <div className={styles.lotteryTickets}>
        <SkeletonLoader variant="text" width="100px" height="18px" />
        <div className={styles.ticketGrid}>
          {Array.from({ length: 6 }).map((_, i) => (
            <SkeletonLoader key={i} variant="rounded" width="100%" height="60px" />
          ))}
        </div>
      </div>
    </div>
  );
}

export function SkeletonHomeScreen() {
  return (
    <div className={styles.homeSkeleton}>
      {/* Header */}
      <div className={styles.homeHeader}>
        <SkeletonLoader variant="text" width="150px" height="28px" />
        <SkeletonLoader variant="text" width="100px" height="14px" />
      </div>
      
      {/* Wallet Summary */}
      <SkeletonWalletSummary />
      
      {/* Feature Cards */}
      <div className={styles.homeFeatures}>
        <SkeletonFeatureCard />
        <SkeletonFeatureCard />
        <SkeletonFeatureCard />
        <SkeletonFeatureCard />
      </div>
    </div>
  );
}
