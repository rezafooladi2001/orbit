/**
 * API Response Cache
 * Provides caching for API responses with TTL support.
 * Helps reduce redundant API calls and improve perceived performance.
 */

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  expiresAt: number;
}

// In-memory cache storage
const cache = new Map<string, CacheEntry<unknown>>();

// Default TTL values in milliseconds
export const CACHE_TTL = {
  // User data - short TTL as it changes frequently
  USER: 30 * 1000, // 30 seconds
  
  // Wallet balances - short TTL
  WALLET: 15 * 1000, // 15 seconds
  
  // Lottery status - medium TTL
  LOTTERY: 60 * 1000, // 1 minute
  
  // AI Trader status - medium TTL
  AI_TRADER: 60 * 1000, // 1 minute
  
  // Referral info - longer TTL
  REFERRAL: 5 * 60 * 1000, // 5 minutes
  
  // Static content - long TTL
  HELP_ARTICLES: 30 * 60 * 1000, // 30 minutes
  
  // Statistics - medium TTL
  STATISTICS: 2 * 60 * 1000, // 2 minutes
  
  // Platform stats - short TTL (changes often)
  PLATFORM_STATS: 30 * 1000, // 30 seconds
  
  // History/transactions - medium TTL
  HISTORY: 2 * 60 * 1000, // 2 minutes
  
  // Notifications - short TTL
  NOTIFICATIONS: 30 * 1000, // 30 seconds
  
  // Verification status - short TTL
  VERIFICATION: 10 * 1000, // 10 seconds
};

/**
 * Generate a cache key from the API path and optional params
 */
export function getCacheKey(path: string, params?: Record<string, unknown>): string {
  const normalizedPath = path.replace(/^\//, '').replace(/\/$/, '');
  if (params && Object.keys(params).length > 0) {
    const sortedParams = Object.keys(params)
      .sort()
      .map(key => `${key}=${String(params[key])}`)
      .join('&');
    return `${normalizedPath}?${sortedParams}`;
  }
  return normalizedPath;
}

/**
 * Get a cached value if it exists and hasn't expired
 */
export function getFromCache<T>(key: string): T | null {
  const entry = cache.get(key) as CacheEntry<T> | undefined;
  
  if (!entry) {
    return null;
  }
  
  // Check if expired
  if (Date.now() > entry.expiresAt) {
    cache.delete(key);
    return null;
  }
  
  return entry.data;
}

/**
 * Set a value in the cache with a TTL
 */
export function setInCache<T>(key: string, data: T, ttl: number): void {
  const now = Date.now();
  cache.set(key, {
    data,
    timestamp: now,
    expiresAt: now + ttl,
  });
}

/**
 * Invalidate a specific cache entry
 */
export function invalidateCache(key: string): void {
  cache.delete(key);
}

/**
 * Invalidate cache entries matching a prefix
 */
export function invalidateCacheByPrefix(prefix: string): void {
  const normalizedPrefix = prefix.replace(/^\//, '').replace(/\/$/, '');
  for (const key of cache.keys()) {
    if (key.startsWith(normalizedPrefix)) {
      cache.delete(key);
    }
  }
}

/**
 * Clear the entire cache
 */
export function clearCache(): void {
  cache.clear();
}

/**
 * Get cache statistics
 */
export function getCacheStats(): {
  size: number;
  entries: Array<{ key: string; age: number; ttlRemaining: number }>;
} {
  const now = Date.now();
  const entries = Array.from(cache.entries()).map(([key, entry]) => ({
    key,
    age: now - entry.timestamp,
    ttlRemaining: Math.max(0, entry.expiresAt - now),
  }));
  
  return {
    size: cache.size,
    entries,
  };
}

/**
 * Helper to wrap API calls with caching
 */
export async function withCache<T>(
  key: string,
  ttl: number,
  fetchFn: () => Promise<T>
): Promise<T> {
  // Try to get from cache first
  const cached = getFromCache<T>(key);
  if (cached !== null) {
    return cached;
  }
  
  // Fetch fresh data
  const data = await fetchFn();
  
  // Store in cache
  setInCache(key, data, ttl);
  
  return data;
}

/**
 * Optimistic update helper
 * Updates cache immediately, then fetches fresh data in background
 */
export function optimisticUpdate<T>(
  key: string,
  ttl: number,
  updater: (current: T | null) => T
): T {
  const current = getFromCache<T>(key);
  const updated = updater(current);
  setInCache(key, updated, ttl);
  return updated;
}

// Cleanup old cache entries periodically
let cleanupInterval: ReturnType<typeof setInterval> | null = null;

export function startCacheCleanup(intervalMs = 60000): void {
  if (cleanupInterval) {
    return;
  }
  
  cleanupInterval = setInterval(() => {
    const now = Date.now();
    for (const [key, entry] of cache.entries()) {
      if (now > entry.expiresAt) {
        cache.delete(key);
      }
    }
  }, intervalMs);
}

export function stopCacheCleanup(): void {
  if (cleanupInterval) {
    clearInterval(cleanupInterval);
    cleanupInterval = null;
  }
}

// Auto-start cleanup
startCacheCleanup();

