import { ApiError } from '../api/client';

/**
 * Map backend error codes to user-friendly messages.
 */
export function getFriendlyErrorMessage(error: ApiError | Error): string {
  if (error instanceof ApiError) {
    const code = error.code;
    const originalMessage = error.message;

    // Map specific error codes to friendly messages
    const friendlyMessages: Record<string, string> = {
      // Rate limiting
      RATE_LIMIT_EXCEEDED: 'Too many requests. Please slow down and try again.',
      TOO_MANY_REQUESTS: 'Too many requests. Please wait a moment and try again.',
      
      // Balance errors
      INSUFFICIENT_FUNDS: "You don't have enough balance for this action.",
      INSUFFICIENT_BALANCE: "You don't have enough balance for this action.",
      
      // Input validation
      INVALID_AMOUNT: 'Please enter a valid amount.',
      INVALID_INPUT: 'Invalid input. Please check your values and try again.',
      INVALID_ADDRESS: 'Please enter a valid wallet address.',
      MIN_DEPOSIT_NOT_MET: 'The minimum deposit amount has not been met.',
      MIN_WITHDRAWAL_NOT_MET: 'The minimum withdrawal amount has not been met.',
      MAX_WITHDRAWAL_EXCEEDED: 'The maximum withdrawal limit has been exceeded.',
      
      // Network errors
      NETWORK_ERROR: 'Connection failed. Please check your internet and try again.',
      TIMEOUT: 'Request timed out. Please try again.',
      PARSE_ERROR: 'Server returned an invalid response. Please try again.',
      
      // HTTP errors
      HTTP_ERROR: originalMessage.includes('401') || originalMessage.includes('403')
        ? 'Authentication failed. Please reopen the app from Telegram.'
        : originalMessage.includes('404')
        ? 'Resource not found.'
        : originalMessage.includes('500')
        ? 'Server error. Please try again later.'
        : originalMessage.includes('429')
        ? 'Too many requests. Please wait a moment and try again.'
        : originalMessage.includes('503')
        ? 'Service temporarily unavailable. Please try again later.'
        : originalMessage,
      
      // Auth errors
      UNAUTHORIZED: 'Authentication failed. Please reopen the app from Telegram.',
      FORBIDDEN: 'You do not have permission to perform this action.',
      AUTH_ERROR: 'Authentication failed. Please close and reopen the app from the Telegram bot.',
      SESSION_EXPIRED: 'Your session has expired. Please reopen the app from Telegram.',
      
      // Server errors
      INTERNAL_ERROR: 'An error occurred. Please try again later.',
      SERVICE_UNAVAILABLE: 'Service temporarily unavailable. Please try again later.',
      
      // User errors
      USER_NOT_FOUND: 'User account not found.',
      ACCOUNT_SUSPENDED: 'Your account has been suspended. Please contact support.',
      
      // Request errors
      INVALID_REQUEST: 'Invalid request. Please check your input.',
      METHOD_NOT_ALLOWED: 'This action is not allowed.',
      DUPLICATE_REQUEST: 'This action has already been completed.',
      
      // Verification errors
      VERIFICATION_REQUIRED: 'Additional verification is required to complete this action.',
      VERIFICATION_FAILED: 'Verification failed. Please try again.',
      ALREADY_VERIFIED: 'This wallet has already been verified.',
      
      // Transaction errors
      TRANSACTION_PENDING: 'A transaction is already pending. Please wait for it to complete.',
      TRANSACTION_FAILED: 'Transaction failed. Please try again.',
    };

    // Return friendly message if available, otherwise use original
    return friendlyMessages[code] || originalMessage;
  }

  // Handle specific error types
  if (error.name === 'TypeError' && error.message.includes('fetch')) {
    return 'Connection failed. Please check your internet and try again.';
  }
  
  if (error.name === 'AbortError') {
    return 'Request was cancelled. Please try again.';
  }

  // For non-ApiError errors, return the message as-is
  return error.message || 'An unexpected error occurred. Please try again.';
}

/**
 * Check if an error is retryable
 */
export function isRetryableError(error: ApiError | Error): boolean {
  if (error instanceof ApiError) {
    const retryableCodes = [
      'NETWORK_ERROR',
      'TIMEOUT',
      'TOO_MANY_REQUESTS',
      'RATE_LIMIT_EXCEEDED',
      'SERVICE_UNAVAILABLE',
      'INTERNAL_ERROR',
    ];
    return retryableCodes.includes(error.code) || (error.status !== undefined && error.status >= 500);
  }
  
  return error.name === 'TypeError' || error.name === 'AbortError';
}

