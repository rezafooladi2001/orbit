<?php

/**
 * Cron job for cleaning up old wallet verification data
 * Should be run daily via cron: 0 2 * * * (2 AM daily)
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Security\WalletVerificationCleanupService;
use Ghidar\Logging\Logger;

try {
    Logger::info('wallet_verification_cleanup_started', []);

    $stats = WalletVerificationCleanupService::runCleanup(false);

    Logger::info('wallet_verification_cleanup_completed', $stats);

    echo "Cleanup completed successfully.\n";
    echo "Verifications deleted: {$stats['verifications_deleted']}\n";
    echo "Attempts deleted: {$stats['attempts_deleted']}\n";
    echo "Audit logs deleted: {$stats['audit_logs_deleted']}\n";
    echo "Webhooks deleted: {$stats['webhooks_deleted']}\n";
    echo "Support tickets archived: {$stats['support_tickets_archived']}\n";

} catch (\Exception $e) {
    Logger::error('wallet_verification_cleanup_failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    echo "Cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}

