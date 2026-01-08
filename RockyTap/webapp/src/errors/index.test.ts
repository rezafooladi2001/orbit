/**
 * Tests for Custom Error Classes
 */

import { describe, it, expect } from 'vitest';
import {
  GhidarError,
  NetworkError,
  ApiError,
  AuthenticationError,
  WalletError,
  VerificationError,
  TransactionError,
  ValidationError,
  TimeoutError,
  RateLimitError,
  isGhidarError,
  isNetworkError,
  isApiError,
  isRetryableError,
  toGhidarError,
  getUserErrorMessage,
} from './index';

describe('GhidarError', () => {
  it('should create a basic error with default values', () => {
    const error = new GhidarError('Test error');
    
    expect(error.message).toBe('Test error');
    expect(error.code).toBe('UNKNOWN_ERROR');
    expect(error.isRetryable).toBe(false);
    expect(error.name).toBe('GhidarError');
    expect(error.timestamp).toBeInstanceOf(Date);
  });

  it('should create an error with custom values', () => {
    const error = new GhidarError('Custom error', 'CUSTOM_CODE', true, 500, { key: 'value' });
    
    expect(error.message).toBe('Custom error');
    expect(error.code).toBe('CUSTOM_CODE');
    expect(error.isRetryable).toBe(true);
    expect(error.statusCode).toBe(500);
    expect(error.context).toEqual({ key: 'value' });
  });

  it('should serialize to JSON correctly', () => {
    const error = new GhidarError('Test', 'CODE', true, 400);
    const json = error.toJSON();
    
    expect(json.name).toBe('GhidarError');
    expect(json.code).toBe('CODE');
    expect(json.message).toBe('Test');
    expect(json.isRetryable).toBe(true);
    expect(json.statusCode).toBe(400);
  });
});

describe('NetworkError', () => {
  it('should be retryable by default', () => {
    const error = new NetworkError();
    
    expect(error.isRetryable).toBe(true);
    expect(error.code).toBe('NETWORK_ERROR');
    expect(error.name).toBe('NetworkError');
  });

  it('should return user-friendly message', () => {
    const error = new NetworkError();
    
    expect(error.getUserMessage()).toContain('internet connection');
  });
});

describe('ApiError', () => {
  it('should be retryable for 5xx errors', () => {
    const error = new ApiError('Server error', 'SERVER_ERROR', 500, '/api/test');
    
    expect(error.isRetryable).toBe(true);
  });

  it('should not be retryable for 4xx errors', () => {
    const error = new ApiError('Bad request', 'BAD_REQUEST', 400, '/api/test');
    
    expect(error.isRetryable).toBe(false);
  });

  it('should return appropriate message for 401', () => {
    const error = new ApiError('Unauthorized', 'UNAUTHORIZED', 401, '/api/test');
    
    expect(error.getUserMessage()).toContain('session');
  });

  it('should return appropriate message for 429', () => {
    const error = new ApiError('Rate limited', 'RATE_LIMITED', 429, '/api/test');
    
    expect(error.getUserMessage()).toContain('Too many requests');
  });
});

describe('AuthenticationError', () => {
  it('should include reason in message', () => {
    const expired = new AuthenticationError('Session expired', 'expired');
    expect(expired.getUserMessage()).toContain('expired');

    const missing = new AuthenticationError('Auth missing', 'missing');
    expect(missing.getUserMessage()).toContain('Telegram');
  });
});

describe('TransactionError', () => {
  it('should handle insufficient balance', () => {
    const error = new TransactionError('Not enough funds', 'INSUFFICIENT_BALANCE');
    
    expect(error.getUserMessage()).toContain('Insufficient balance');
  });

  it('should handle minimum not met', () => {
    const error = new TransactionError('Below minimum', 'MINIMUM_NOT_MET');
    
    expect(error.getUserMessage()).toContain('minimum');
  });
});

describe('RateLimitError', () => {
  it('should include wait time in message', () => {
    const error = new RateLimitError('Too many requests', 30000);
    
    expect(error.getUserMessage()).toContain('30');
  });
});

describe('Type Guards', () => {
  it('isGhidarError should identify GhidarError instances', () => {
    const ghidarError = new GhidarError('Test');
    const networkError = new NetworkError();
    const regularError = new Error('Test');
    
    expect(isGhidarError(ghidarError)).toBe(true);
    expect(isGhidarError(networkError)).toBe(true);
    expect(isGhidarError(regularError)).toBe(false);
    expect(isGhidarError('string')).toBe(false);
  });

  it('isNetworkError should identify NetworkError instances', () => {
    const networkError = new NetworkError();
    const apiError = new ApiError('Test', 'CODE', 500, '/api');
    
    expect(isNetworkError(networkError)).toBe(true);
    expect(isNetworkError(apiError)).toBe(false);
  });

  it('isRetryableError should check retryable flag', () => {
    const retryable = new NetworkError();
    const notRetryable = new AuthenticationError();
    
    expect(isRetryableError(retryable)).toBe(true);
    expect(isRetryableError(notRetryable)).toBe(false);
  });
});

describe('toGhidarError', () => {
  it('should return same error if already GhidarError', () => {
    const original = new NetworkError('Test');
    const converted = toGhidarError(original);
    
    expect(converted).toBe(original);
  });

  it('should convert TypeError with fetch to NetworkError', () => {
    const fetchError = new TypeError('Failed to fetch');
    const converted = toGhidarError(fetchError);
    
    expect(converted).toBeInstanceOf(NetworkError);
  });

  it('should convert AbortError to TimeoutError', () => {
    const abortError = new DOMException('Aborted', 'AbortError');
    const converted = toGhidarError(abortError);
    
    expect(converted).toBeInstanceOf(TimeoutError);
  });

  it('should convert unknown types to GhidarError', () => {
    const converted = toGhidarError('string error');
    
    expect(converted).toBeInstanceOf(GhidarError);
    expect(converted.message).toContain('string error');
  });
});

describe('getUserErrorMessage', () => {
  it('should return user message for GhidarError', () => {
    const error = new NetworkError();
    
    expect(getUserErrorMessage(error)).toContain('internet connection');
  });

  it('should return generic message for unknown errors', () => {
    const error = new Error('Internal error');
    
    expect(getUserErrorMessage(error)).toContain('unexpected');
  });

  it('should handle network-related error messages', () => {
    const error = new Error('network error');
    
    expect(getUserErrorMessage(error)).toContain('Network');
  });
});

