<?php

/**
 * Cron job for processing admin payments
 * Processes scheduled payments from admin_payment_queue table
 * Should be run every 5 minutes: * /5 * * * * php /path/to/process_admin_payments.php (without space)
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Payments\AdminWalletService;
use Ghidar\Logging\Logger;

try {
    Logger::info('Starting admin payment processing cron job');

    // Process scheduled payments
    $results = AdminWalletService::processScheduledPayments();

    $successCount = count(array_filter($results, fn($r) => $r['success'] ?? false));
    $failCount = count($results) - $successCount;

    Logger::info('Admin payment processing completed', [
        'total_processed' => count($results),
        'successful' => $successCount,
        'failed' => $failCount,
        'results' => $results
    ]);

    echo json_encode([
        'status' => 'success',
        'processed' => count($results),
        'successful' => $successCount,
        'failed' => $failCount,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT) . "\n";

} catch (\Exception $e) {
    Logger::error('Admin payment cron job failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

