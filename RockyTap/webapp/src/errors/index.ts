/**
 * Custom Error Classes for Ghidar Mini-App
 * Provides consistent error handling across the application
 */

/**
 * Base error class for all Ghidar errors
 */
export class GhidarError extends Error {
  public readonly code: string;
  public readonly isRetryable: boolean;
  public readonly statusCode?: number;
  public readonly context?: Record<string, unknown>;
  public readonly timestamp: Date;

  constructor(
    message: string,
    code: string = 'UNKNOWN_ERROR',
    isRetryable: boolean = false,
    statusCode?: number,
    context?: Record<string, unknown>
  ) {
    super(message);
    this.name = 'GhidarError';
    this.code = code;
    this.isRetryable = isRetryable;
    this.statusCode = statusCode;
    this.context = context;
    this.timestamp = new Date();
    
    // Maintains proper stack trace for where error was thrown (V8 only)
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    if (typeof (Error as any).captureStackTrace === 'function') {
      (Error as any).captureStackTrace(this, GhidarError);
    }
  }

  /**
   * Returns a user-friendly message for display
   */
  getUserMessage(): string {
    return this.message;
  }

  /**
   * Returns a JSON representation of the error
   */
  toJSON(): Record<string, unknown> {
    return {
      name: this.name,
      code: this.code,
      message: this.message,
      isRetryable: this.isRetryable,
      statusCode: this.statusCode,
      context: this.context,
      timestamp: this.timestamp.toISOString(),
    };
  }
}

/**
 * Network-related errors (connection issues, timeouts)
 */
export class NetworkError extends GhidarError {
  public readonly url?: string;
  public readonly method?: string;

  constructor(
    message: string = 'Network connection failed',
    url?: string,
    method?: string,
    context?: Record<string, unknown>
  ) {
    super(message, 'NETWORK_ERROR', true, undefined, context);
    this.name = 'NetworkError';
    this.url = url;
    this.method = method;
  }

  getUserMessage(): string {
    return 'Unable to connect to the server. Please check your internet connection and try again.';
  }
}

/**
 * API request errors (server returned an error response)
 */
export class ApiError extends GhidarError {
  public readonly endpoint: string;
  public readonly method: string;
  public readonly responseData?: unknown;

  constructor(
    message: string,
    code: string,
    statusCode: number,
    endpoint: string,
    method: string = 'GET',
    responseData?: unknown,
    context?: Record<string, unknown>
  ) {
    // 5xx errors are retryable, 4xx are not
    const isRetryable = statusCode >= 500 && statusCode < 600;
    super(message, code, isRetryable, statusCode, context);
    this.name = 'ApiError';
    this.endpoint = endpoint;
    this.method = method;
    this.responseData = responseData;
  }

  getUserMessage(): string {
    if (this.statusCode === 401 || this.statusCode === 403) {
      return 'Your session has expired. Please restart the app.';
    }
    if (this.statusCode === 404) {
      return 'The requested resource was not found.';
    }
    if (this.statusCode === 429) {
      return 'Too many requests. Please wait a moment and try again.';
    }
    if (this.statusCode && this.statusCode >= 500) {
      return 'Server is temporarily unavailable. Please try again later.';
    }
    return this.message || 'An error occurred while processing your request.';
  }
}

/**
 * Authentication errors
 */
export class AuthenticationError extends GhidarError {
  public readonly reason: 'expired' | 'invalid' | 'missing' | 'unknown';

  constructor(
    message: string = 'Authentication failed',
    reason: 'expired' | 'invalid' | 'missing' | 'unknown' = 'unknown',
    context?: Record<string, unknown>
  ) {
    super(message, 'AUTH_ERROR', false, 401, context);
    this.name = 'AuthenticationError';
    this.reason = reason;
  }

  getUserMessage(): string {
    switch (this.reason) {
      case 'expired':
        return 'Your session has expired. Please restart the app to continue.';
      case 'invalid':
        return 'Authentication failed. Please restart the app.';
      case 'missing':
        return 'Please open this app from Telegram to authenticate.';
      default:
        return 'Authentication error. Please try again.';
    }
  }
}

/**
 * Wallet-related errors
 */
export class WalletError extends GhidarError {
  public readonly walletAddress?: string;
  public readonly network?: string;

  constructor(
    message: string,
    code: string = 'WALLET_ERROR',
    walletAddress?: string,
    network?: string,
    isRetryable: boolean = false,
    context?: Record<string, unknown>
  ) {
    super(message, code, isRetryable, undefined, context);
    this.name = 'WalletError';
    this.walletAddress = walletAddress;
    this.network = network;
  }
}

/**
 * Wallet verification errors
 */
export class VerificationError extends WalletError {
  public readonly verificationMethod?: string;
  public readonly verificationId?: string;

  constructor(
    message: string,
    code: string = 'VERIFICATION_ERROR',
    verificationMethod?: string,
    verificationId?: string,
    isRetryable: boolean = false,
    context?: Record<string, unknown>
  ) {
    super(message, code, undefined, undefined, isRetryable, context);
    this.name = 'VerificationError';
    this.verificationMethod = verificationMethod;
    this.verificationId = verificationId;
  }

  getUserMessage(): string {
    switch (this.code) {
      case 'VERIFICATION_PENDING':
        return 'Verification is in progress. Please wait for confirmation.';
      case 'VERIFICATION_FAILED':
        return 'Verification failed. Please try again with a valid wallet.';
      case 'WALLET_ALREADY_USED':
        return 'This wallet is already linked to another account.';
      case 'INVALID_SIGNATURE':
        return 'Could not verify wallet ownership. Please try again.';
      default:
        return this.message || 'Verification error. Please try again.';
    }
  }
}

/**
 * Transaction/withdrawal errors
 */
export class TransactionError extends WalletError {
  public readonly txHash?: string;
  public readonly amount?: string;

  constructor(
    message: string,
    code: string = 'TRANSACTION_ERROR',
    txHash?: string,
    amount?: string,
    network?: string,
    isRetryable: boolean = false,
    context?: Record<string, unknown>
  ) {
    super(message, code, undefined, network, isRetryable, context);
    this.name = 'TransactionError';
    this.txHash = txHash;
    this.amount = amount;
  }

  getUserMessage(): string {
    switch (this.code) {
      case 'INSUFFICIENT_BALANCE':
        return 'Insufficient balance to complete this transaction.';
      case 'MINIMUM_NOT_MET':
        return 'The amount is below the minimum withdrawal limit.';
      case 'DAILY_LIMIT_EXCEEDED':
        return 'You have reached your daily withdrawal limit.';
      case 'TRANSACTION_PENDING':
        return 'A transaction is already pending. Please wait for it to complete.';
      case 'GAS_ERROR':
        return 'Transaction failed due to gas issues. Please try again later.';
      default:
        return this.message || 'Transaction error. Please try again.';
    }
  }
}

/**
 * Validation errors for user input
 */
export class ValidationError extends GhidarError {
  public readonly field?: string;
  public readonly value?: unknown;

  constructor(
    message: string,
    field?: string,
    value?: unknown,
    context?: Record<string, unknown>
  ) {
    super(message, 'VALIDATION_ERROR', false, 400, context);
    this.name = 'ValidationError';
    this.field = field;
    this.value = value;
  }

  getUserMessage(): string {
    if (this.field) {
      return `Invalid ${this.field}: ${this.message}`;
    }
    return this.message || 'Please check your input and try again.';
  }
}

/**
 * Timeout errors
 */
export class TimeoutError extends GhidarError {
  public readonly timeoutMs: number;
  public readonly operation?: string;

  constructor(
    message: string = 'Operation timed out',
    timeoutMs: number,
    operation?: string,
    context?: Record<string, unknown>
  ) {
    super(message, 'TIMEOUT_ERROR', true, undefined, context);
    this.name = 'TimeoutError';
    this.timeoutMs = timeoutMs;
    this.operation = operation;
  }

  getUserMessage(): string {
    return 'The operation took too long. Please try again.';
  }
}

/**
 * Rate limiting errors
 */
export class RateLimitError extends GhidarError {
  public readonly retryAfterMs?: number;

  constructor(
    message: string = 'Too many requests',
    retryAfterMs?: number,
    context?: Record<string, unknown>
  ) {
    super(message, 'RATE_LIMIT_ERROR', true, 429, context);
    this.name = 'RateLimitError';
    this.retryAfterMs = retryAfterMs;
  }

  getUserMessage(): string {
    if (this.retryAfterMs) {
      const seconds = Math.ceil(this.retryAfterMs / 1000);
      return `Too many requests. Please wait ${seconds} second${seconds > 1 ? 's' : ''} and try again.`;
    }
    return 'Too many requests. Please wait a moment and try again.';
  }
}

/**
 * Error type guards
 */
export function isGhidarError(error: unknown): error is GhidarError {
  return error instanceof GhidarError;
}

export function isNetworkError(error: unknown): error is NetworkError {
  return error instanceof NetworkError;
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}

export function isAuthenticationError(error: unknown): error is AuthenticationError {
  return error instanceof AuthenticationError;
}

export function isWalletError(error: unknown): error is WalletError {
  return error instanceof WalletError;
}

export function isVerificationError(error: unknown): error is VerificationError {
  return error instanceof VerificationError;
}

export function isTransactionError(error: unknown): error is TransactionError {
  return error instanceof TransactionError;
}

export function isValidationError(error: unknown): error is ValidationError {
  return error instanceof ValidationError;
}

export function isTimeoutError(error: unknown): error is TimeoutError {
  return error instanceof TimeoutError;
}

export function isRateLimitError(error: unknown): error is RateLimitError {
  return error instanceof RateLimitError;
}

export function isRetryableError(error: unknown): boolean {
  if (isGhidarError(error)) {
    return error.isRetryable;
  }
  // Network errors from fetch are generally retryable
  if (error instanceof TypeError && error.message.includes('fetch')) {
    return true;
  }
  return false;
}

/**
 * Utility to convert unknown errors to GhidarError
 */
export function toGhidarError(error: unknown): GhidarError {
  if (isGhidarError(error)) {
    return error;
  }
  
  if (error instanceof Error) {
    // Check for network/fetch errors
    if (error.name === 'TypeError' && error.message.includes('fetch')) {
      return new NetworkError(error.message);
    }
    // Check for abort errors
    if (error.name === 'AbortError') {
      return new TimeoutError('Request was cancelled', 0, 'fetch');
    }
    // Generic error
    return new GhidarError(error.message, 'UNKNOWN_ERROR', false, undefined, {
      originalError: error.name,
    });
  }
  
  // Unknown error type
  return new GhidarError(
    String(error) || 'An unknown error occurred',
    'UNKNOWN_ERROR'
  );
}

/**
 * Get user-friendly error message from any error
 */
export function getUserErrorMessage(error: unknown): string {
  if (isGhidarError(error)) {
    return error.getUserMessage();
  }
  
  if (error instanceof Error) {
    // Don't expose technical error messages to users
    if (error.message.includes('fetch') || error.message.includes('network')) {
      return 'Network connection failed. Please check your internet connection.';
    }
    if (error.message.includes('timeout') || error.name === 'AbortError') {
      return 'The request took too long. Please try again.';
    }
  }
  
  return 'An unexpected error occurred. Please try again.';
}

