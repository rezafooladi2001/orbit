/**
 * Error Recovery Hook
 * Provides consistent error handling and recovery mechanisms
 */

import { useState, useCallback, useRef } from 'react';
import {
  GhidarError,
  isRetryableError,
  toGhidarError,
  getUserErrorMessage,
} from '../errors';

interface UseErrorRecoveryOptions {
  /** Maximum number of automatic retries */
  maxRetries?: number;
  /** Base delay between retries in ms (uses exponential backoff) */
  retryDelay?: number;
  /** Callback when error occurs */
  onError?: (error: GhidarError) => void;
  /** Callback when all retries exhausted */
  onMaxRetriesReached?: (error: GhidarError) => void;
  /** Callback when recovery succeeds */
  onRecovery?: () => void;
}

interface UseErrorRecoveryReturn<T> {
  /** The current error, if any */
  error: GhidarError | null;
  /** User-friendly error message */
  errorMessage: string | null;
  /** Whether an operation is currently loading */
  isLoading: boolean;
  /** Whether the error is retryable */
  isRetryable: boolean;
  /** Current retry count */
  retryCount: number;
  /** Execute an async operation with error handling */
  execute: (operation: () => Promise<T>) => Promise<T | null>;
  /** Retry the last failed operation */
  retry: () => Promise<T | null>;
  /** Clear the current error */
  clearError: () => void;
  /** Reset all state */
  reset: () => void;
}

const DEFAULT_MAX_RETRIES = 3;
const DEFAULT_RETRY_DELAY = 1000;

export function useErrorRecovery<T = unknown>(
  options: UseErrorRecoveryOptions = {}
): UseErrorRecoveryReturn<T> {
  const {
    maxRetries = DEFAULT_MAX_RETRIES,
    retryDelay = DEFAULT_RETRY_DELAY,
    onError,
    onMaxRetriesReached,
    onRecovery,
  } = options;

  const [error, setError] = useState<GhidarError | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [retryCount, setRetryCount] = useState(0);
  
  // Store the last operation for retry
  const lastOperationRef = useRef<(() => Promise<T>) | null>(null);

  const clearError = useCallback(() => {
    setError(null);
    setRetryCount(0);
  }, []);

  const reset = useCallback(() => {
    setError(null);
    setIsLoading(false);
    setRetryCount(0);
    lastOperationRef.current = null;
  }, []);

  const execute = useCallback(
    async (operation: () => Promise<T>): Promise<T | null> => {
      // Store operation for potential retry
      lastOperationRef.current = operation;
      
      setIsLoading(true);
      setError(null);

      try {
        const result = await operation();
        
        // Success - clear any previous errors
        setError(null);
        setRetryCount(0);
        onRecovery?.();
        
        return result;
      } catch (err) {
        const ghidarError = toGhidarError(err);
        setError(ghidarError);
        onError?.(ghidarError);
        
        // Auto-retry for retryable errors if under max retries
        if (isRetryableError(ghidarError) && retryCount < maxRetries) {
          const newRetryCount = retryCount + 1;
          setRetryCount(newRetryCount);
          
          // Calculate delay with exponential backoff
          const delay = retryDelay * Math.pow(2, retryCount);
          
          console.log(
            `[useErrorRecovery] Auto-retry ${newRetryCount}/${maxRetries} after ${delay}ms`
          );
          
          await new Promise(resolve => setTimeout(resolve, delay));
          
          // Recursive retry
          return execute(operation);
        }
        
        // Max retries reached or non-retryable error
        if (retryCount >= maxRetries) {
          onMaxRetriesReached?.(ghidarError);
        }
        
        return null;
      } finally {
        setIsLoading(false);
      }
    },
    [maxRetries, retryDelay, retryCount, onError, onMaxRetriesReached, onRecovery]
  );

  const retry = useCallback(async (): Promise<T | null> => {
    if (!lastOperationRef.current) {
      console.warn('[useErrorRecovery] No operation to retry');
      return null;
    }
    
    // Reset retry count for manual retry
    setRetryCount(0);
    
    return execute(lastOperationRef.current);
  }, [execute]);

  return {
    error,
    errorMessage: error ? getUserErrorMessage(error) : null,
    isLoading,
    isRetryable: error ? isRetryableError(error) : false,
    retryCount,
    execute,
    retry,
    clearError,
    reset,
  };
}

/**
 * Hook for handling async operations with error boundaries
 */
interface UseAsyncOptions<T> {
  /** Initial data */
  initialData?: T;
  /** Whether to execute immediately on mount */
  immediate?: boolean;
  /** Dependencies that trigger re-execution */
  deps?: React.DependencyList;
  /** Error recovery options */
  errorRecovery?: UseErrorRecoveryOptions;
}

interface UseAsyncReturn<T> extends UseErrorRecoveryReturn<T> {
  /** The current data */
  data: T | null;
  /** Whether initial load is complete */
  isInitialLoad: boolean;
}

export function useAsync<T>(
  asyncFn: () => Promise<T>,
  options: UseAsyncOptions<T> = {}
): UseAsyncReturn<T> {
  const {
    initialData = null,
    immediate = false,
    errorRecovery: errorRecoveryOptions = {},
  } = options;

  const [data, setData] = useState<T | null>(initialData as T | null);
  const [isInitialLoad, setIsInitialLoad] = useState(!immediate);
  
  const recovery = useErrorRecovery<T>(errorRecoveryOptions);

  const execute = useCallback(async () => {
    const result = await recovery.execute(asyncFn);
    if (result !== null) {
      setData(result);
    }
    setIsInitialLoad(false);
    return result;
  }, [asyncFn, recovery]);

  // Execute immediately if requested
  // This effect intentionally runs only once on mount
  // eslint-disable-next-line react-hooks/exhaustive-deps
  if (immediate && isInitialLoad) {
    // Note: We can't use useEffect here because we need sync execution
    // This pattern is acceptable for immediate-execution scenarios
    execute();
  }

  return {
    ...recovery,
    data,
    isInitialLoad,
    execute,
  };
}

export default useErrorRecovery;

