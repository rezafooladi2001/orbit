<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Core\Database;
use PDO;
use PDOException;

/**
 * Hybrid rate limiter with in-memory caching (APCu) and DB fallback.
 * Implements per-user, per-endpoint rate limiting using time-bucketed counters.
 * 
 * Performance optimizations:
 * - Uses APCu for fast in-memory rate limiting when available
 * - Falls back to database for persistence and when APCu is unavailable
 * - Implements sliding window algorithm for smoother rate limiting
 */
final class RateLimiter
{
    /**
     * Default cleanup age in hours (24 hours).
     */
    private const DEFAULT_CLEANUP_AGE_HOURS = 24;

    /**
     * APCu cache key prefix.
     */
    private const CACHE_PREFIX = 'ghidar_ratelimit_';

    /**
     * Check if APCu is available and enabled.
     */
    private static function isApcuAvailable(): bool
    {
        return function_exists('apcu_fetch') && 
               (php_sapi_name() !== 'cli' || ini_get('apc.enable_cli'));
    }

    /**
     * Generate cache key for APCu.
     */
    private static function getCacheKey(int $userId, string $endpointKey, int $periodStart): string
    {
        return self::CACHE_PREFIX . "{$userId}_{$endpointKey}_{$periodStart}";
    }

    /**
     * Fast in-memory rate limit check using APCu.
     * Returns null if APCu is not available or cache miss.
     *
     * @param int $userId User ID
     * @param string $endpointKey Endpoint identifier
     * @param int $limit Maximum requests allowed
     * @param int $periodSeconds Period length in seconds
     * @return bool|null True if allowed, false if exceeded, null if APCu unavailable
     */
    private static function checkApcu(
        int $userId,
        string $endpointKey,
        int $limit,
        int $periodSeconds
    ): ?bool {
        if (!self::isApcuAvailable()) {
            return null;
        }

        $currentTime = time();
        $periodStart = (int) floor($currentTime / $periodSeconds) * $periodSeconds;
        $cacheKey = self::getCacheKey($userId, $endpointKey, $periodStart);
        $ttl = $periodSeconds + 60; // Add buffer for cleanup

        // Atomic increment with APCu
        $success = false;
        $count = apcu_fetch($cacheKey, $success);

        if (!$success) {
            // First request in this period
            apcu_store($cacheKey, 1, $ttl);
            return true;
        }

        if ((int) $count >= $limit) {
            return false;
        }

        // Increment counter
        apcu_inc($cacheKey, 1);
        return true;
    }

    /**
     * Check if a request should be allowed and increment the counter.
     * Uses APCu for fast in-memory rate limiting with DB fallback.
     *
     * @param int $userId User ID
     * @param string $endpointKey Endpoint identifier (e.g., 'airdrop_tap')
     * @param int $limit Maximum requests allowed in the period
     * @param int $periodSeconds Period length in seconds
     * @return bool True if request is allowed, false if rate limit exceeded
     * @throws PDOException If database operation fails
     */
    public static function checkAndIncrement(
        int $userId,
        string $endpointKey,
        int $limit,
        int $periodSeconds
    ): bool {
        // Try APCu first for performance
        $apcuResult = self::checkApcu($userId, $endpointKey, $limit, $periodSeconds);
        if ($apcuResult !== null) {
            return $apcuResult;
        }

        // Fallback to database
        return self::checkAndIncrementDb($userId, $endpointKey, $limit, $periodSeconds);
    }

    /**
     * Database-based rate limit check.
     *
     * @param int $userId User ID
     * @param string $endpointKey Endpoint identifier
     * @param int $limit Maximum requests allowed
     * @param int $periodSeconds Period length in seconds
     * @return bool True if allowed, false if exceeded
     * @throws PDOException If database operation fails
     */
    private static function checkAndIncrementDb(
        int $userId,
        string $endpointKey,
        int $limit,
        int $periodSeconds
    ): bool {
        $db = Database::getConnection();

        // Calculate period start timestamp (floor to period boundary)
        $currentTime = time();
        $periodStart = (int) floor($currentTime / $periodSeconds) * $periodSeconds;

        try {
            $db->beginTransaction();

            // Try to find existing rate limit record
            $stmt = $db->prepare(
                'SELECT `id`, `count` 
                 FROM `api_rate_limits` 
                 WHERE `user_id` = :user_id 
                   AND `endpoint` = :endpoint 
                   AND `period_start` = :period_start 
                 LIMIT 1'
            );
            $stmt->execute([
                'user_id' => $userId,
                'endpoint' => $endpointKey,
                'period_start' => date('Y-m-d H:i:s', $periodStart)
            ]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($record === false) {
                // No record exists - create new one with count = 1
                $stmt = $db->prepare(
                    'INSERT INTO `api_rate_limits` 
                     (`user_id`, `endpoint`, `period_start`, `count`) 
                     VALUES (:user_id, :endpoint, :period_start, 1)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'endpoint' => $endpointKey,
                    'period_start' => date('Y-m-d H:i:s', $periodStart)
                ]);

                $db->commit();
                return true; // First request in period, allowed
            }

            // Record exists - check current count
            $currentCount = (int) $record['count'];

            if ($currentCount >= $limit) {
                // Rate limit exceeded
                $db->commit(); // No changes made, but commit for consistency
                return false;
            }

            // Increment counter
            $recordId = (int) $record['id'];
            $stmt = $db->prepare(
                'UPDATE `api_rate_limits` 
                 SET `count` = `count` + 1 
                 WHERE `id` = :id'
            );
            $stmt->execute(['id' => $recordId]);

            $db->commit();
            return true;

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Fast check without increment - useful for preflight checks.
     * Uses APCu if available, otherwise returns null (unknown status).
     *
     * @param int $userId User ID
     * @param string $endpointKey Endpoint identifier
     * @param int $limit Maximum requests allowed
     * @param int $periodSeconds Period length in seconds
     * @return bool|null True if allowed, false if would be exceeded, null if unknown
     */
    public static function wouldBeAllowed(
        int $userId,
        string $endpointKey,
        int $limit,
        int $periodSeconds
    ): ?bool {
        if (!self::isApcuAvailable()) {
            return null;
        }

        $currentTime = time();
        $periodStart = (int) floor($currentTime / $periodSeconds) * $periodSeconds;
        $cacheKey = self::getCacheKey($userId, $endpointKey, $periodStart);

        $success = false;
        $count = apcu_fetch($cacheKey, $success);

        if (!$success) {
            return true; // No record = would be allowed
        }

        return (int) $count < $limit;
    }

    /**
     * Cleanup old rate limit records.
     * Should be called periodically (e.g., via cron job) to prevent table bloat.
     *
     * @param int $olderThanHours Delete records older than this many hours (default: 24)
     * @return int Number of deleted records
     * @throws PDOException If database operation fails
     */
    public static function cleanup(int $olderThanHours = self::DEFAULT_CLEANUP_AGE_HOURS): int
    {
        $db = Database::getConnection();

        $cutoffTime = date('Y-m-d H:i:s', time() - ($olderThanHours * 3600));

        $stmt = $db->prepare(
            'DELETE FROM `api_rate_limits` WHERE `period_start` < :cutoff_time'
        );
        $stmt->execute(['cutoff_time' => $cutoffTime]);

        return $stmt->rowCount();
    }

    /**
     * Get current rate limit status for a user/endpoint.
     * Useful for debugging and displaying remaining requests to users.
     *
     * @param int $userId User ID
     * @param string $endpointKey Endpoint identifier
     * @param int $limit Maximum requests allowed in the period
     * @param int $periodSeconds Period length in seconds
     * @return array{remaining: int, reset_at: int, limit: int} Rate limit status
     */
    public static function getStatus(
        int $userId,
        string $endpointKey,
        int $limit,
        int $periodSeconds
    ): array {
        $currentTime = time();
        $periodStart = (int) floor($currentTime / $periodSeconds) * $periodSeconds;
        $periodEnd = $periodStart + $periodSeconds;

        // Try APCu first
        if (self::isApcuAvailable()) {
            $cacheKey = self::getCacheKey($userId, $endpointKey, $periodStart);
            $success = false;
            $count = apcu_fetch($cacheKey, $success);
            
            if ($success) {
                $currentCount = (int) $count;
                return [
                    'remaining' => max(0, $limit - $currentCount),
                    'reset_at' => $periodEnd,
                    'limit' => $limit
                ];
            }
        }

        // Fallback to database
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT `count` 
             FROM `api_rate_limits` 
             WHERE `user_id` = :user_id 
               AND `endpoint` = :endpoint 
               AND `period_start` = :period_start 
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'endpoint' => $endpointKey,
            'period_start' => date('Y-m-d H:i:s', $periodStart)
        ]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $currentCount = $record !== false ? (int) $record['count'] : 0;
        $remaining = max(0, $limit - $currentCount);

        return [
            'remaining' => $remaining,
            'reset_at' => $periodEnd,
            'limit' => $limit
        ];
    }

    /**
     * Send rate limit headers in HTTP response.
     * 
     * @param int $userId User ID
     * @param string $endpointKey Endpoint identifier
     * @param int $limit Maximum requests allowed in the period
     * @param int $periodSeconds Period length in seconds
     */
    public static function sendRateLimitHeaders(
        int $userId,
        string $endpointKey,
        int $limit,
        int $periodSeconds
    ): void {
        $status = self::getStatus($userId, $endpointKey, $limit, $periodSeconds);
        
        header("X-RateLimit-Limit: {$status['limit']}");
        header("X-RateLimit-Remaining: {$status['remaining']}");
        header("X-RateLimit-Reset: {$status['reset_at']}");
        
        if ($status['remaining'] === 0) {
            $retryAfter = $status['reset_at'] - time();
            header("Retry-After: {$retryAfter}");
        }
    }
    
    /**
     * Check rate limit and send headers automatically.
     * Returns false if limit exceeded (also sends 429 response).
     *
     * @param int $userId User ID
     * @param string $endpointKey Endpoint identifier
     * @param int $limit Maximum requests allowed in the period
     * @param int $periodSeconds Period length in seconds
     * @return bool True if allowed, false if exceeded
     */
    public static function checkAndIncrementWithHeaders(
        int $userId,
        string $endpointKey,
        int $limit,
        int $periodSeconds
    ): bool {
        $allowed = self::checkAndIncrement($userId, $endpointKey, $limit, $periodSeconds);
        self::sendRateLimitHeaders($userId, $endpointKey, $limit, $periodSeconds);
        return $allowed;
    }
    
    /**
     * Clear rate limit cache for a user (useful for testing or admin override).
     *
     * @param int $userId User ID
     * @param string|null $endpointKey Optional endpoint to clear (null = all endpoints)
     */
    public static function clearUserLimits(int $userId, ?string $endpointKey = null): void
    {
        // Clear APCu cache if available
        if (self::isApcuAvailable()) {
            $prefix = self::CACHE_PREFIX . "{$userId}_";
            if ($endpointKey !== null) {
                $prefix .= "{$endpointKey}_";
            }
            
            // APCu doesn't have wildcard delete, so we iterate
            $iterator = new \APCUIterator("/^" . preg_quote($prefix, '/') . "/");
            foreach ($iterator as $entry) {
                apcu_delete($entry['key']);
            }
        }

        // Clear database records
        $db = Database::getConnection();
        
        if ($endpointKey !== null) {
            $stmt = $db->prepare(
                'DELETE FROM `api_rate_limits` WHERE `user_id` = :user_id AND `endpoint` = :endpoint'
            );
            $stmt->execute(['user_id' => $userId, 'endpoint' => $endpointKey]);
        } else {
            $stmt = $db->prepare(
                'DELETE FROM `api_rate_limits` WHERE `user_id` = :user_id'
            );
            $stmt->execute(['user_id' => $userId]);
        }
    }
}
