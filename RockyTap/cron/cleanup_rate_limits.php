<?php

declare(strict_types=1);

/**
 * Cron job: Cleanup old rate limit records.
 * Run this periodically (e.g., every hour) via cron:
 * 0 * * * * php /path/to/RockyTap/cron/cleanup_rate_limits.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Security\RateLimiter;
use Ghidar\Logging\Logger;

try {
    // Cleanup records older than 24 hours
    $deletedCount = RateLimiter::cleanup(24);

    Logger::info('rate_limit_cleanup', [
        'deleted_count' => $deletedCount
    ]);

    echo "Cleaned up {$deletedCount} old rate limit records.\n";

} catch (\Throwable $e) {
    Logger::error('rate_limit_cleanup_failed', [
        'error' => $e->getMessage()
    ]);

    echo "Cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}

