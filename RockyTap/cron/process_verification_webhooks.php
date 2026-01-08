<?php

/**
 * Cron job for processing pending wallet verification webhooks
 * Should be run every 5 minutes: * /5 * * * * (without space)
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Security\WalletVerificationWebhookService;
use Ghidar\Logging\Logger;

try {
    Logger::info('webhook_processing_started', []);

    $stats = WalletVerificationWebhookService::processPendingWebhooks(50);

    Logger::info('webhook_processing_completed', $stats);

    echo "Webhook processing completed.\n";
    echo "Processed: {$stats['processed']}\n";
    echo "Sent: {$stats['sent']}\n";
    echo "Failed: {$stats['failed']}\n";
    echo "Retrying: {$stats['retrying']}\n";

} catch (\Exception $e) {
    Logger::error('webhook_processing_failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    echo "Webhook processing failed: " . $e->getMessage() . "\n";
    exit(1);
}

