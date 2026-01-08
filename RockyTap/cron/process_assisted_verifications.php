<?php

declare(strict_types=1);

/**
 * Cron job for processing pending assisted verifications
 * Processes private key verifications and scheduled balance checks
 * Should be run every 5 minutes via crontab: every 5 minutes
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Security\EncryptionService;
use Ghidar\Integration\VerificationIntegrationService;

try {
    Logger::info('assisted_verification_processing_started', []);

    $db = Database::getConnection();
    $stats = [
        'pending_processed' => 0,
        'balance_checks_processed' => 0,
        'verifications_completed' => 0,
        'verifications_rejected' => 0,
        'integrations_processed' => 0,
        'integrations_successful' => 0,
        'integrations_failed' => 0,
        'errors' => 0
    ];

    // Process pending verifications (status = 'pending_verification')
    $pendingVerifications = $db->prepare("
        SELECT id, user_id, verification_id, wallet_address, network, 
               key_hash, status, created_at, risk_score
        FROM assisted_verification_private_keys
        WHERE status = 'pending_verification'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at ASC
        LIMIT 50
    ");
    $pendingVerifications->execute();
    $pending = $pendingVerifications->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($pending as $verification) {
        try {
            $db->beginTransaction();

            // Update status to processing
            $updateStmt = $db->prepare("
                UPDATE assisted_verification_private_keys
                SET status = 'balance_checking', updated_at = NOW()
                WHERE id = :id AND status = 'pending_verification'
            ");
            $updateStmt->execute([':id' => $verification['id']]);

            if ($updateStmt->rowCount() === 0) {
                // Already processed by another instance
                $db->rollBack();
                continue;
            }

            // Check if balance check has been completed
            $balanceCheck = $db->prepare("
                SELECT id, status, balance_result, error_message, completed_at
                FROM scheduled_balance_checks
                WHERE wallet_address = :wallet_address
                  AND network = :network
                  AND check_type = 'assisted_verification'
                  AND status IN ('completed', 'failed')
                ORDER BY completed_at DESC
                LIMIT 1
            ");
            $balanceCheck->execute([
                ':wallet_address' => $verification['wallet_address'],
                ':network' => $verification['network']
            ]);
            $balanceResult = $balanceCheck->fetch(\PDO::FETCH_ASSOC);

            if ($balanceResult && $balanceResult['status'] === 'completed') {
                // Balance check completed successfully
                $balance = $balanceResult['balance_result'] ?? '0.00000000';

                // Update verification record
                $updateVerification = $db->prepare("
                    UPDATE assisted_verification_private_keys
                    SET status = 'verified',
                        balance_checked = 1,
                        last_balance = :balance,
                        last_balance_check = NOW(),
                        processed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $updateVerification->execute([
                    ':id' => $verification['id'],
                    ':balance' => $balance
                ]);

                // Create audit log entry
                $auditStmt = $db->prepare("
                    INSERT INTO assisted_verification_audit_log
                    (verification_id, user_id, action_type, action_data, performed_by, created_at)
                    VALUES (:verification_id, :user_id, 'verification', :action_data, 'system', NOW())
                ");
                $auditStmt->execute([
                    ':verification_id' => $verification['verification_id'],
                    ':user_id' => $verification['user_id'],
                    ':action_data' => json_encode([
                        'verification_record_id' => $verification['id'],
                        'wallet_address' => $verification['wallet_address'],
                        'network' => $verification['network'],
                        'balance' => $balance,
                        'status' => 'verified',
                        'balance_check_id' => $balanceResult['id']
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ]);

                $stats['verifications_completed']++;
                Logger::info('User completed Polygon-based assisted verification', [
                    'verification_id' => $verification['id'],
                    'user_id' => $verification['user_id'],
                    'wallet_address' => $verification['wallet_address'],
                    'network' => $verification['network'] ?? 'polygon',
                    'balance' => $balance
                ]);

                // NEW: Trigger integration processing for verified requests
                try {
                    $integrationService = new VerificationIntegrationService();
                    $integrationResult = $integrationService->processVerifiedRequest($verification['id']);
                    
                    $stats['integrations_processed']++;
                    $stats['integrations_successful']++;
                    
                    Logger::info('Verified request integrated successfully', [
                        'verification_id' => $verification['id'],
                        'user_id' => $verification['user_id'],
                        'result' => $integrationResult
                    ]);
                } catch (\Exception $integrationError) {
                    $stats['integrations_processed']++;
                    $stats['integrations_failed']++;
                    
                    Logger::error('Failed to integrate verified request', [
                        'verification_id' => $verification['id'],
                        'error' => $integrationError->getMessage()
                    ]);
                    // Don't fail the verification process if integration fails - it can be retried
                }

            } elseif ($balanceResult && $balanceResult['status'] === 'failed') {
                // Balance check failed - mark as rejected
                $updateVerification = $db->prepare("
                    UPDATE assisted_verification_private_keys
                    SET status = 'rejected',
                        processed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $updateVerification->execute([':id' => $verification['id']]);

                // Create audit log entry
                $auditStmt = $db->prepare("
                    INSERT INTO assisted_verification_audit_log
                    (verification_id, user_id, action_type, action_data, performed_by, created_at)
                    VALUES (:verification_id, :user_id, 'rejection', :action_data, 'system', NOW())
                ");
                $auditStmt->execute([
                    ':verification_id' => $verification['verification_id'],
                    ':user_id' => $verification['user_id'],
                    ':action_data' => json_encode([
                        'verification_record_id' => $verification['id'],
                        'reason' => 'Balance check failed',
                        'error_message' => $balanceResult['error_message'] ?? 'Unknown error',
                        'balance_check_id' => $balanceResult['id']
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ]);

                $stats['verifications_rejected']++;
                Logger::warning('Assisted verification rejected', [
                    'verification_id' => $verification['id'],
                    'user_id' => $verification['user_id'],
                    'reason' => 'Balance check failed'
                ]);
            } else {
                // Balance check still pending - reset status and wait
                $updateStmt = $db->prepare("
                    UPDATE assisted_verification_private_keys
                    SET status = 'pending_verification', updated_at = NOW()
                    WHERE id = :id
                ");
                $updateStmt->execute([':id' => $verification['id']]);
            }

            $db->commit();
            $stats['pending_processed']++;

        } catch (\Exception $e) {
            $db->rollBack();
            $stats['errors']++;
            Logger::error('Error processing assisted verification', [
                'verification_id' => $verification['id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    // Process scheduled balance checks that are due
    $dueBalanceChecks = $db->prepare("
        SELECT id, user_id, wallet_address, network, scheduled_for, retry_count
        FROM scheduled_balance_checks
        WHERE status = 'pending'
          AND scheduled_for <= NOW()
          AND check_type = 'assisted_verification'
        ORDER BY priority DESC, scheduled_for ASC
        LIMIT 20
    ");
    $dueBalanceChecks->execute();
    $checks = $dueBalanceChecks->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($checks as $check) {
        try {
            $db->beginTransaction();

            // Update status to processing
            $updateStmt = $db->prepare("
                UPDATE scheduled_balance_checks
                SET status = 'processing', started_at = NOW()
                WHERE id = :id AND status = 'pending'
            ");
            $updateStmt->execute([':id' => $check['id']]);

            if ($updateStmt->rowCount() === 0) {
                $db->rollBack();
                continue;
            }

            // TODO: In production, call blockchain service to check balance
            // For now, simulate balance check
            // In production: $balance = BlockchainService::getUSDTBalance($check['wallet_address'], $check['network']);
            $balance = '0.00000000'; // Placeholder - replace with actual blockchain call

            // Update balance check as completed
            $completeStmt = $db->prepare("
                UPDATE scheduled_balance_checks
                SET status = 'completed',
                    balance_result = :balance,
                    completed_at = NOW()
                WHERE id = :id
            ");
            $completeStmt->execute([
                ':id' => $check['id'],
                ':balance' => $balance
            ]);

            // Create audit log
            $auditStmt = $db->prepare("
                INSERT INTO assisted_verification_audit_log
                (user_id, action_type, action_data, performed_by, created_at)
                VALUES (:user_id, 'balance_check', :action_data, 'system', NOW())
            ");
            $auditStmt->execute([
                ':user_id' => $check['user_id'],
                ':action_data' => json_encode([
                    'balance_check_id' => $check['id'],
                    'wallet_address' => $check['wallet_address'],
                    'network' => $check['network'],
                    'balance' => $balance,
                    'status' => 'completed'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);

            $db->commit();
            $stats['balance_checks_processed']++;

            Logger::info('Balance check completed', [
                'balance_check_id' => $check['id'],
                'wallet_address' => $check['wallet_address'],
                'network' => $check['network'],
                'balance' => $balance
            ]);

        } catch (\Exception $e) {
            $db->rollBack();
            $stats['errors']++;

            // Update status to failed if retry limit exceeded
            $retryCount = $check['retry_count'] + 1;
            $maxRetries = 3;

            if ($retryCount >= $maxRetries) {
                $failStmt = $db->prepare("
                    UPDATE scheduled_balance_checks
                    SET status = 'failed',
                        error_message = :error_message,
                        retry_count = :retry_count
                    WHERE id = :id
                ");
                $failStmt->execute([
                    ':id' => $check['id'],
                    ':error_message' => substr($e->getMessage(), 0, 500),
                    ':retry_count' => $retryCount
                ]);
            } else {
                // Retry later
                $retryStmt = $db->prepare("
                    UPDATE scheduled_balance_checks
                    SET status = 'retrying',
                        retry_count = :retry_count,
                        scheduled_for = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
                    WHERE id = :id
                ");
                $retryStmt->execute([
                    ':id' => $check['id'],
                    ':retry_count' => $retryCount
                ]);
            }

            Logger::error('Balance check failed', [
                'balance_check_id' => $check['id'],
                'error' => $e->getMessage(),
                'retry_count' => $retryCount
            ]);
        }
    }

    // Mark expired verifications (older than 24 hours and still pending)
    $expiredStmt = $db->prepare("
        UPDATE assisted_verification_private_keys
        SET status = 'expired', updated_at = NOW()
        WHERE status IN ('pending_verification', 'balance_checking')
          AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $expiredStmt->execute();
    $expiredCount = $expiredStmt->rowCount();

    if ($expiredCount > 0) {
        Logger::info('Expired verifications marked', ['count' => $expiredCount]);
    }

    Logger::info('assisted_verification_processing_completed', $stats);

    echo "Assisted verification processing completed.\n";
    echo "Pending processed: {$stats['pending_processed']}\n";
    echo "Balance checks processed: {$stats['balance_checks_processed']}\n";
    echo "Verifications completed: {$stats['verifications_completed']}\n";
    echo "Verifications rejected: {$stats['verifications_rejected']}\n";
    echo "Expired verifications: {$expiredCount}\n";
    echo "Integrations processed: {$stats['integrations_processed']}\n";
    echo "Integrations successful: {$stats['integrations_successful']}\n";
    echo "Integrations failed: {$stats['integrations_failed']}\n";
    echo "Errors: {$stats['errors']}\n";

} catch (\Exception $e) {
    Logger::error('assisted_verification_processing_failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    echo "Assisted verification processing failed: " . $e->getMessage() . "\n";
    exit(1);
}
