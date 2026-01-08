/**
 * Rate limiter for RPC calls.
 * Implements per-network rate limiting with exponential backoff.
 */

import { RateLimiter } from 'limiter';

// Rate limits per network (requests per second)
const RATE_LIMITS = {
  eth: 10,  // 10 requests/second for Ethereum
  bsc: 10,  // 10 requests/second for BSC
  tron: 5,  // 5 requests/second for Tron
};

// Create rate limiters for each network
const limiters: Record<string, RateLimiter> = {};

for (const [network, rps] of Object.entries(RATE_LIMITS)) {
  limiters[network] = new RateLimiter({
    tokensPerInterval: rps,
    interval: 'second',
  });
}

/**
 * Wait for rate limit token with exponential backoff.
 *
 * @param network Network identifier (eth, bsc, tron)
 * @param retries Number of retries attempted
 * @returns Promise that resolves when token is available
 */
export async function waitForRateLimit(
  network: string,
  retries: number = 0
): Promise<void> {
  const limiter = limiters[network];
  if (!limiter) {
    // Unknown network, use default delay
    await new Promise((resolve) => setTimeout(resolve, 100));
    return;
  }

  try {
    await limiter.removeTokens(1);
  } catch (error) {
    // Rate limit exceeded, wait with exponential backoff
    const backoffDelay = Math.min(1000 * Math.pow(2, retries), 10000); // Max 10 seconds
    console.warn(
      `Rate limit exceeded for ${network}, waiting ${backoffDelay}ms (retry ${retries})`
    );
    await new Promise((resolve) => setTimeout(resolve, backoffDelay));
    
    // Retry with incremented retry count
    if (retries < 5) {
      return waitForRateLimit(network, retries + 1);
    } else {
      throw new Error(`Rate limit exceeded for ${network} after ${retries} retries`);
    }
  }
}

/**
 * Check if rate limit is available (non-blocking).
 *
 * @param network Network identifier
 * @returns True if token is available immediately
 */
export function checkRateLimit(network: string): boolean {
  const limiter = limiters[network];
  if (!limiter) {
    return true;
  }

  return limiter.getTokensRemaining() > 0;
}

/**
 * Log rate limit event.
 *
 * @param network Network identifier
 * @param event Event type (exceeded, recovered, etc.)
 */
export function logRateLimitEvent(network: string, event: string): void {
  console.log(`[RateLimit] ${network}: ${event}`);
}

