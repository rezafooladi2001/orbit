import { Button } from './Button';
import { WarningIcon } from '../Icons';
import styles from './ErrorState.module.css';

export type ErrorSeverity = 'error' | 'warning' | 'info';

interface ErrorAction {
  label: string;
  onClick: () => void;
  variant?: 'primary' | 'secondary' | 'ghost';
}

interface ErrorStateProps {
  /** Main error message to display */
  message: string;
  /** Error title */
  title?: string;
  /** Retry callback */
  onRetry?: () => void;
  /** Retry button label */
  retryLabel?: string;
  /** Error severity level */
  severity?: ErrorSeverity;
  /** Additional suggestion for user */
  suggestion?: string;
  /** Help link for more information */
  helpLink?: { label: string; url: string };
  /** Additional actions beyond retry */
  actions?: ErrorAction[];
  /** Error code for reference */
  errorCode?: string;
  /** Whether error is dismissible */
  dismissible?: boolean;
  /** Dismiss callback */
  onDismiss?: () => void;
}

const severityTitles: Record<ErrorSeverity, string> = {
  error: 'Something went wrong',
  warning: 'Attention needed',
  info: 'Information',
};

const severityIcons: Record<ErrorSeverity, string> = {
  error: '❌',
  warning: '⚠️',
  info: 'ℹ️',
};

export function ErrorState({ 
  message, 
  title,
  onRetry, 
  retryLabel = 'Try Again',
  severity = 'error',
  suggestion,
  helpLink,
  actions = [],
  errorCode,
  dismissible = false,
  onDismiss,
}: ErrorStateProps) {
  const displayTitle = title || severityTitles[severity];
  
  return (
    <div 
      className={`${styles.container} ${styles[severity]}`}
      role="alert"
      aria-live="assertive"
    >
      {dismissible && onDismiss && (
        <button 
          className={styles.dismissButton}
          onClick={onDismiss}
          aria-label="Dismiss error"
        >
          ×
        </button>
      )}
      
      <div className={styles.icon} aria-hidden="true">
        <span className={styles.severityIcon}>{severityIcons[severity]}</span>
        <WarningIcon size={32} />
      </div>
      
      <h3 className={styles.title}>{displayTitle}</h3>
      <p className={styles.message}>{message}</p>
      
      {suggestion && (
        <p className={styles.suggestion}>
          <strong>💡 Suggestion:</strong> {suggestion}
        </p>
      )}
      
      {errorCode && (
        <p className={styles.errorCode}>
          Error Code: <code>{errorCode}</code>
        </p>
      )}
      
      {helpLink && (
        <a 
          href={helpLink.url}
          target="_blank"
          rel="noopener noreferrer"
          className={styles.helpLink}
        >
          {helpLink.label} →
        </a>
      )}
      
      <div className={styles.actions}>
        {onRetry && (
          <Button 
            onClick={onRetry} 
            variant="primary" 
            aria-label={`${retryLabel}: ${message}`}
          >
            {retryLabel}
          </Button>
        )}
        
        {actions.map((action, index) => (
          <Button
            key={index}
            onClick={action.onClick}
            variant={action.variant || 'secondary'}
          >
            {action.label}
          </Button>
        ))}
      </div>
    </div>
  );
}

/**
 * Predefined error state for network errors
 */
export function NetworkErrorState({ onRetry }: { onRetry?: () => void }) {
  return (
    <ErrorState
      severity="error"
      title="Connection Failed"
      message="Unable to connect to the server. Please check your internet connection."
      suggestion="Make sure you're connected to the internet and try again."
      onRetry={onRetry}
      retryLabel="Retry Connection"
    />
  );
}

/**
 * Predefined error state for authentication errors
 */
export function AuthErrorState({ onRetry }: { onRetry?: () => void }) {
  return (
    <ErrorState
      severity="error"
      title="Session Expired"
      message="Your session has expired. Please restart the app to continue."
      suggestion="Close and reopen the app from Telegram to restore your session."
      onRetry={onRetry}
      retryLabel="Restart App"
    />
  );
}

/**
 * Predefined error state for rate limiting
 */
export function RateLimitErrorState({ retryAfter, onRetry }: { retryAfter?: number; onRetry?: () => void }) {
  const waitTime = retryAfter ? Math.ceil(retryAfter / 1000) : 60;
  
  return (
    <ErrorState
      severity="warning"
      title="Too Many Requests"
      message={`Please wait ${waitTime} seconds before trying again.`}
      suggestion="This helps protect our service. Your request will work after the wait period."
      onRetry={onRetry}
      retryLabel={`Retry in ${waitTime}s`}
    />
  );
}

/**
 * Predefined error state for maintenance mode
 */
export function MaintenanceErrorState() {
  return (
    <ErrorState
      severity="info"
      title="Under Maintenance"
      message="We're performing scheduled maintenance. Please check back soon."
      suggestion="Maintenance usually takes 15-30 minutes. Thank you for your patience."
    />
  );
}
