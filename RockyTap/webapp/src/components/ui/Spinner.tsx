import styles from './Spinner.module.css';

interface SpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  className?: string;
  /** Accessible label for the spinner */
  label?: string;
}

export function Spinner({ size = 'md', className = '', label = 'Loading' }: SpinnerProps) {
  return (
    <div 
      className={`${styles.spinner} ${styles[size]} ${className}`}
      role="status"
      aria-label={label}
    >
      <span className="sr-only">{label}</span>
    </div>
  );
}

interface LoadingScreenProps {
  message?: string;
}

export function LoadingScreen({ message = 'Loading...' }: LoadingScreenProps) {
  return (
    <div 
      className={styles.loadingScreen}
      role="alert"
      aria-live="polite"
      aria-busy="true"
    >
      <Spinner size="lg" label={message} />
      <p className={styles.loadingMessage} aria-hidden="true">{message}</p>
      <div className={styles.loadingBars} aria-hidden="true">
        <div className={styles.loadingBar} />
        <div className={styles.loadingBar} />
        <div className={styles.loadingBar} />
      </div>
    </div>
  );
}
