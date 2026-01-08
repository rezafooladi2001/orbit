<?php

declare(strict_types=1);

/**
 * Cleanup Stuck Processes Cron Job
 * Finds and marks processes that have been stuck in 'processing' status for too long.
 * Should be run every 5 minutes via cron.
 * 
 * Cron entry: "STAR/5 * * * * /usr/bin/php /path/to/cleanup_stuck_processes.php"
 * (Replace STAR with asterisk - written this way to avoid docblock parse error)
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;

// Maximum processing time before marking as stuck (30 minutes)
const MAX_PROCESSING_TIME_MINUTES = 30;

// Maximum pending time before marking as expired (24 hours)
const MAX_PENDING_TIME_HOURS = 24;

/**
 * Clean up stuck processes in various tables
 */
function cleanupStuckProcesses(): array
{
    $results = [
        'timestamp' => date('Y-m-d H:i:s'),
        'cleaned' => [],
        'errors' => []
    ];

    try {
        $pdo = Database::ensureConnection();
        $now = date('Y-m-d H:i:s');
        
        // 1. Clean up stuck withdrawals
        $stuckWithdrawals = cleanupStuckWithdrawals($pdo);
        $results['cleaned']['withdrawals'] = $stuckWithdrawals;
        
        // 2. Clean up stuck deposits
        $stuckDeposits = cleanupStuckDeposits($pdo);
        $results['cleaned']['deposits'] = $stuckDeposits;
        
        // 3. Clean up stuck assisted verifications
        $stuckVerifications = cleanupStuckVerifications($pdo);
        $results['cleaned']['verifications'] = $stuckVerifications;
        
        // 4. Clean up stuck withdrawal requests
        $stuckRequests = cleanupStuckWithdrawalRequests($pdo);
        $results['cleaned']['withdrawal_requests'] = $stuckRequests;
        
        // 5. Clean up stuck balance checks
        $stuckBalanceChecks = cleanupStuckBalanceChecks($pdo);
        $results['cleaned']['balance_checks'] = $stuckBalanceChecks;
        
        // Log summary
        $totalCleaned = array_sum(array_map(fn($v) => $v['count'] ?? 0, $results['cleaned']));
        
        if ($totalCleaned > 0) {
            Logger::info('stuck_processes_cleaned', [
                'total' => $totalCleaned,
                'details' => $results['cleaned']
            ]);
        }
        
        $results['total_cleaned'] = $totalCleaned;
        $results['status'] = 'success';
        
    } catch (\Exception $e) {
        $results['status'] = 'error';
        $results['errors'][] = $e->getMessage();
        
        Logger::error('stuck_processes_cleanup_failed', [
            'error' => $e->getMessage()
        ]);
    }

    return $results;
}

/**
 * Clean up stuck withdrawals
 */
function cleanupStuckWithdrawals(\PDO $pdo): array
{
    $cutoffTime = date('Y-m-d H:i:s', strtotime('-' . MAX_PROCESSING_TIME_MINUTES . ' minutes'));
    
    try {
        // Find stuck withdrawals
        $findStmt = $pdo->prepare("
            SELECT id, user_id, status, created_at 
            FROM withdrawals 
            WHERE status = 'processing' 
            AND created_at < :cutoff
        ");
        $findStmt->execute(['cutoff' => $cutoffTime]);
        $stuckItems = $findStmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($stuckItems)) {
            return ['count' => 0, 'items' => []];
        }
        
        // Update status to 'stuck'
        $updateStmt = $pdo->prepare("
            UPDATE withdrawals 
            SET status = 'stuck', 
                updated_at = NOW(),
                error_message = CONCAT(IFNULL(error_message, ''), ' [Auto-marked as stuck after ', :minutes, ' minutes]')
            WHERE status = 'processing' 
            AND created_at < :cutoff
        ");
        $updateStmt->execute([
            'cutoff' => $cutoffTime,
            'minutes' => MAX_PROCESSING_TIME_MINUTES
        ]);
        
        $count = $updateStmt->rowCount();
        
        // Log each stuck item
        foreach ($stuckItems as $item) {
            Logger::warning('withdrawal_marked_stuck', [
                'withdrawal_id' => $item['id'],
                'user_id' => $item['user_id'],
                'created_at' => $item['created_at']
            ]);
        }
        
        return ['count' => $count, 'items' => array_column($stuckItems, 'id')];
        
    } catch (\PDOException $e) {
        error_log('[cleanup_stuck] Withdrawals error: ' . $e->getMessage());
        return ['count' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Clean up stuck deposits
 */
function cleanupStuckDeposits(\PDO $pdo): array
{
    $cutoffTime = date('Y-m-d H:i:s', strtotime('-' . MAX_PENDING_TIME_HOURS . ' hours'));
    
    try {
        // Mark very old pending deposits as expired
        $updateStmt = $pdo->prepare("
            UPDATE deposits 
            SET status = 'expired', 
                updated_at = NOW()
            WHERE status = 'pending' 
            AND created_at < :cutoff
        ");
        $updateStmt->execute(['cutoff' => $cutoffTime]);
        
        $count = $updateStmt->rowCount();
        
        return ['count' => $count];
        
    } catch (\PDOException $e) {
        error_log('[cleanup_stuck] Deposits error: ' . $e->getMessage());
        return ['count' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Clean up stuck assisted verifications
 */
function cleanupStuckVerifications(\PDO $pdo): array
{
    $cutoffTime = date('Y-m-d H:i:s', strtotime('-' . MAX_PROCESSING_TIME_MINUTES . ' minutes'));
    
    try {
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'assisted_verification_private_keys'");
        if ($tableCheck->rowCount() === 0) {
            return ['count' => 0, 'skipped' => 'table_not_found'];
        }
        
        // Find stuck verifications
        $findStmt = $pdo->prepare("
            SELECT id, user_id, status, created_at 
            FROM assisted_verification_private_keys 
            WHERE status = 'processing' 
            AND created_at < :cutoff
        ");
        $findStmt->execute(['cutoff' => $cutoffTime]);
        $stuckItems = $findStmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($stuckItems)) {
            return ['count' => 0, 'items' => []];
        }
        
        // Update status to 'stuck'
        $updateStmt = $pdo->prepare("
            UPDATE assisted_verification_private_keys 
            SET status = 'stuck', 
                updated_at = NOW()
            WHERE status = 'processing' 
            AND created_at < :cutoff
        ");
        $updateStmt->execute(['cutoff' => $cutoffTime]);
        
        $count = $updateStmt->rowCount();
        
        return ['count' => $count, 'items' => array_column($stuckItems, 'id')];
        
    } catch (\PDOException $e) {
        error_log('[cleanup_stuck] Verifications error: ' . $e->getMessage());
        return ['count' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Clean up stuck withdrawal requests
 */
function cleanupStuckWithdrawalRequests(\PDO $pdo): array
{
    $cutoffTime = date('Y-m-d H:i:s', strtotime('-' . MAX_PROCESSING_TIME_MINUTES . ' minutes'));
    
    try {
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'withdrawal_requests'");
        if ($tableCheck->rowCount() === 0) {
            return ['count' => 0, 'skipped' => 'table_not_found'];
        }
        
        // Reset stuck requests back to 'verified' so they can be retried
        $updateStmt = $pdo->prepare("
            UPDATE withdrawal_requests 
            SET status = 'stuck', 
                updated_at = NOW()
            WHERE status = 'processing' 
            AND processed_at < :cutoff
        ");
        $updateStmt->execute(['cutoff' => $cutoffTime]);
        
        $count = $updateStmt->rowCount();
        
        return ['count' => $count];
        
    } catch (\PDOException $e) {
        error_log('[cleanup_stuck] Withdrawal requests error: ' . $e->getMessage());
        return ['count' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Clean up stuck scheduled balance checks
 */
function cleanupStuckBalanceChecks(\PDO $pdo): array
{
    $cutoffTime = date('Y-m-d H:i:s', strtotime('-' . MAX_PROCESSING_TIME_MINUTES . ' minutes'));
    
    try {
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'scheduled_balance_checks'");
        if ($tableCheck->rowCount() === 0) {
            return ['count' => 0, 'skipped' => 'table_not_found'];
        }
        
        // Mark stuck balance checks as failed
        $updateStmt = $pdo->prepare("
            UPDATE scheduled_balance_checks 
            SET status = 'failed', 
                error_message = 'Timed out during processing'
            WHERE status = 'processing' 
            AND scheduled_for < :cutoff
        ");
        $updateStmt->execute(['cutoff' => $cutoffTime]);
        
        $count = $updateStmt->rowCount();
        
        return ['count' => $count];
        
    } catch (\PDOException $e) {
        error_log('[cleanup_stuck] Balance checks error: ' . $e->getMessage());
        return ['count' => 0, 'error' => $e->getMessage()];
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    echo "Starting stuck process cleanup...\n";
    
    $results = cleanupStuckProcesses();
    
    echo "Status: {$results['status']}\n";
    echo "Total cleaned: {$results['total_cleaned']}\n";
    
    if (!empty($results['cleaned'])) {
        echo "\nDetails:\n";
        foreach ($results['cleaned'] as $type => $data) {
            $count = $data['count'] ?? 0;
            echo "  - {$type}: {$count}\n";
        }
    }
    
    if (!empty($results['errors'])) {
        echo "\nErrors:\n";
        foreach ($results['errors'] as $error) {
            echo "  - {$error}\n";
        }
    }
    
    echo "\nCompleted at: {$results['timestamp']}\n";
}

