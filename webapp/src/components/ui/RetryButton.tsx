import { Button } from './Button';
import styles from './RetryButton.module.css';

interface RetryButtonProps {
  onRetry: () => void;
  loading?: boolean;
  className?: string;
}

export function RetryButton({ onRetry, loading = false, className = '' }: RetryButtonProps) {
  return (
    <Button
      variant="outline"
      onClick={onRetry}
      loading={loading}
      className={`${styles.retryButton} ${className}`}
    >
      Retry
    </Button>
  );
}

