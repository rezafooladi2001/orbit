import React, { Component, ErrorInfo, ReactNode } from 'react';
import { GhidarLogo } from './GhidarLogo';

interface ErrorBoundaryProps {
  children: ReactNode;
  fallback?: ReactNode;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
}

interface ErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
}

/**
 * Error Boundary component that catches JavaScript errors anywhere in the child
 * component tree and displays a fallback UI instead of crashing the app.
 */
export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  constructor(props: ErrorBoundaryProps) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
    };
  }

  static getDerivedStateFromError(error: Error): Partial<ErrorBoundaryState> {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
    // Log error to console for debugging
    console.error('[ErrorBoundary] Caught error:', error);
    console.error('[ErrorBoundary] Error info:', errorInfo);

    // Store error info in state
    this.setState({ errorInfo });

    // Call optional error callback
    if (this.props.onError) {
      this.props.onError(error, errorInfo);
    }

    // Log to external service in production
    if (import.meta.env.PROD) {
      this.logErrorToService(error, errorInfo);
    }
  }

  private logErrorToService(error: Error, errorInfo: ErrorInfo): void {
    // In production, you would send this to an error tracking service
    // like Sentry, LogRocket, or your own backend
    try {
      const errorData = {
        message: error.message,
        stack: error.stack,
        componentStack: errorInfo.componentStack,
        timestamp: new Date().toISOString(),
        url: window.location.href,
        userAgent: navigator.userAgent,
      };
      
      // Log to console in production (could be replaced with API call)
      console.error('[ErrorBoundary] Production error logged:', errorData);
    } catch (e) {
      console.error('[ErrorBoundary] Failed to log error:', e);
    }
  }

  private handleReload = (): void => {
    window.location.reload();
  };

  private handleReset = (): void => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null,
    });
  };

  private handleGoHome = (): void => {
    // Clear error state and reload to home
    window.location.href = window.location.origin + window.location.pathname;
  };

  render(): ReactNode {
    if (this.state.hasError) {
      // Custom fallback provided
      if (this.props.fallback) {
        return this.props.fallback;
      }

      // Default error UI
      return (
        <div style={styles.container}>
          <div style={styles.content}>
            <div style={styles.logoWrapper}>
              <GhidarLogo size="xl" showText={false} animate />
            </div>
            
            <h1 style={styles.title}>Something went wrong</h1>
            
            <p style={styles.message}>
              We're sorry, but something unexpected happened. 
              Don't worry, your funds are safe.
            </p>
            
            <div style={styles.actions}>
              <button 
                style={styles.primaryButton}
                onClick={this.handleReload}
                aria-label="Reload the app"
              >
                Reload App
              </button>
              
              <button 
                style={styles.secondaryButton}
                onClick={this.handleReset}
                aria-label="Try again without reloading"
              >
                Try Again
              </button>
            </div>
            
            {/* Error details for development */}
            {import.meta.env.DEV && this.state.error && (
              <details style={styles.errorDetails}>
                <summary style={styles.errorSummary}>
                  Technical Details (Dev Only)
                </summary>
                <div style={styles.errorContent}>
                  <p style={styles.errorName}>
                    <strong>Error:</strong> {this.state.error.name}
                  </p>
                  <p style={styles.errorMessage}>
                    <strong>Message:</strong> {this.state.error.message}
                  </p>
                  {this.state.error.stack && (
                    <pre style={styles.errorStack}>
                      {this.state.error.stack}
                    </pre>
                  )}
                  {this.state.errorInfo?.componentStack && (
                    <>
                      <p style={styles.errorMessage}>
                        <strong>Component Stack:</strong>
                      </p>
                      <pre style={styles.errorStack}>
                        {this.state.errorInfo.componentStack}
                      </pre>
                    </>
                  )}
                </div>
              </details>
            )}
            
            <p style={styles.supportText}>
              If this problem persists, please contact our support team.
            </p>
          </div>
          
          {/* Background decoration */}
          <div style={styles.background} aria-hidden="true" />
        </div>
      );
    }

    return this.props.children;
  }
}

// Inline styles for the error boundary (to ensure they work even if CSS fails to load)
const styles: Record<string, React.CSSProperties> = {
  container: {
    position: 'fixed',
    inset: 0,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '20px',
    background: '#0a0c10',
    zIndex: 10000,
  },
  content: {
    position: 'relative',
    zIndex: 1,
    maxWidth: '400px',
    width: '100%',
    textAlign: 'center',
    animation: 'fadeIn 0.3s ease-out',
  },
  logoWrapper: {
    marginBottom: '24px',
  },
  title: {
    fontSize: '24px',
    fontWeight: 600,
    color: '#f8fafc',
    margin: '0 0 12px 0',
    fontFamily: "'Sora', -apple-system, BlinkMacSystemFont, sans-serif",
  },
  message: {
    fontSize: '15px',
    color: '#94a3b8',
    margin: '0 0 24px 0',
    lineHeight: 1.6,
    fontFamily: "'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif",
  },
  actions: {
    display: 'flex',
    flexDirection: 'column',
    gap: '12px',
    marginBottom: '24px',
  },
  primaryButton: {
    padding: '14px 24px',
    fontSize: '15px',
    fontWeight: 600,
    color: '#0a0c10',
    background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
    border: 'none',
    borderRadius: '12px',
    cursor: 'pointer',
    transition: 'transform 0.15s ease, box-shadow 0.15s ease',
    fontFamily: "'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif",
  },
  secondaryButton: {
    padding: '14px 24px',
    fontSize: '15px',
    fontWeight: 600,
    color: '#f8fafc',
    background: 'rgba(255, 255, 255, 0.08)',
    border: '1px solid rgba(255, 255, 255, 0.12)',
    borderRadius: '12px',
    cursor: 'pointer',
    transition: 'transform 0.15s ease, background 0.15s ease',
    fontFamily: "'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif",
  },
  errorDetails: {
    marginBottom: '24px',
    textAlign: 'left',
    background: 'rgba(239, 68, 68, 0.1)',
    borderRadius: '8px',
    padding: '12px',
    border: '1px solid rgba(239, 68, 68, 0.3)',
  },
  errorSummary: {
    cursor: 'pointer',
    color: '#ef4444',
    fontSize: '13px',
    fontWeight: 600,
  },
  errorContent: {
    marginTop: '12px',
    fontSize: '12px',
    color: '#94a3b8',
  },
  errorName: {
    margin: '0 0 8px 0',
    color: '#ef4444',
  },
  errorMessage: {
    margin: '0 0 8px 0',
    wordBreak: 'break-word',
  },
  errorStack: {
    margin: 0,
    padding: '8px',
    background: 'rgba(0, 0, 0, 0.3)',
    borderRadius: '4px',
    fontSize: '11px',
    overflow: 'auto',
    maxHeight: '150px',
    whiteSpace: 'pre-wrap',
    wordBreak: 'break-all',
  },
  supportText: {
    fontSize: '13px',
    color: '#64748b',
    margin: 0,
  },
  background: {
    position: 'absolute',
    inset: 0,
    background: 'radial-gradient(ellipse at 50% 0%, rgba(16, 185, 129, 0.15) 0%, transparent 60%)',
    pointerEvents: 'none',
  },
};

export default ErrorBoundary;

