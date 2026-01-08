<?php

declare(strict_types=1);

namespace Ghidar\Payments;

use Ghidar\Core\Database;
use Ghidar\Config\Config;
use Ghidar\Logging\Logger;

/**
 * Admin Wallet Service
 * Handles automated payment processing to admin wallets for compliance fees.
 */
class AdminWalletService
{
    private static array $adminWallets = [
        'erc20' => [
            'min_balance' => '0.1', // Minimum ETH for gas
            'gas_limit' => 21000,
            'priority_fee' => '2' // Gwei
        ],
        'bep20' => [
            'min_balance' => '0.1', // Minimum BNB for gas
            'gas_limit' => 21000,
            'priority_fee' => '3'
        ],
        'trc20' => [
            'min_balance' => '10', // Minimum TRX for energy
            'energy_limit' => 30000
        ],
        'polygon' => [
            'min_balance' => '0.1', // Minimum MATIC for gas
            'gas_limit' => 21000,
            'priority_fee' => '1'
        ],
        'arbitrum' => [
            'min_balance' => '0.1', // Minimum ETH for gas
            'gas_limit' => 21000,
            'priority_fee' => '0.1'
        ],
        'optimism' => [
            'min_balance' => '0.1', // Minimum ETH for gas
            'gas_limit' => 21000,
            'priority_fee' => '0.1'
        ],
        'avalanche' => [
            'min_balance' => '0.1', // Minimum AVAX for gas
            'gas_limit' => 21000,
            'priority_fee' => '25' // nAVAX
        ]
    ];

    /**
     * Process admin payment for compliance fees
     *
     * @param string $network Network identifier
     * @param string $amount Amount to send (in USDT)
     * @param array<string, mixed> $metadata Additional metadata
     * @return array<string, mixed> Payment processing result
     * @throws \InvalidArgumentException If network is unsupported
     * @throws \RuntimeException If admin wallet is not configured
     */
    public static function processAdminPayment(string $network, string $amount, array $metadata = []): array
    {
        $network = strtolower($network);
        $walletConfig = self::$adminWallets[$network] ?? null;

        if (!$walletConfig) {
            throw new \InvalidArgumentException("Unsupported network for admin payments: {$network}");
        }

        // Get actual wallet address from environment
        $adminAddress = self::getAdminWalletAddress($network);
        if (!$adminAddress) {
            throw new \RuntimeException("Admin wallet not configured for network: {$network}");
        }

        // Prepare payment data
        $paymentData = [
            'network' => $network,
            'to_address' => $adminAddress,
            'amount' => $amount,
            'metadata' => $metadata,
            'timestamp' => time(),
            'reference_id' => self::generatePaymentReference()
        ];

        // Store payment request
        $paymentId = self::storePaymentRequest($paymentData);

        // Trigger automated payment processing
        self::schedulePaymentProcessing($paymentId);

        return [
            'payment_id' => $paymentId,
            'reference' => $paymentData['reference_id'],
            'admin_wallet' => $adminAddress,
            'network' => $network,
            'amount' => $amount,
            'status' => 'scheduled',
            'estimated_completion' => date('Y-m-d H:i:s', time() + 300) // 5 minutes
        ];
    }

    /**
     * Get admin wallet address for network
     *
     * @param string $network Network identifier
     * @return string|null Admin wallet address or null if not configured
     */
    private static function getAdminWalletAddress(string $network): ?string
    {
        $networkUpper = strtoupper($network);
        $configKey = "ADMIN_{$networkUpper}_WALLET";
        return Config::get($configKey);
    }

    /**
     * Generate unique payment reference ID
     *
     * @return string Payment reference ID
     */
    private static function generatePaymentReference(): string
    {
        return 'ADM-' . strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * Store payment request in database
     *
     * @param array<string, mixed> $paymentData Payment data
     * @return int Payment ID
     */
    private static function storePaymentRequest(array $paymentData): int
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            INSERT INTO admin_payments
            (reference_id, user_id, network, amount, admin_wallet, source_wallet,
             request_data, status, created_at, scheduled_for)
            VALUES (:reference_id, :user_id, :network, :amount, :admin_wallet, :source_wallet,
                    :request_data, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 1 MINUTE))
        ");

        $stmt->execute([
            ':reference_id' => $paymentData['reference_id'],
            ':user_id' => $paymentData['metadata']['user_id'] ?? null,
            ':network' => $paymentData['network'],
            ':amount' => $paymentData['amount'],
            ':admin_wallet' => $paymentData['to_address'],
            ':source_wallet' => $paymentData['metadata']['source_wallet'] ?? null,
            ':request_data' => json_encode($paymentData, JSON_UNESCAPED_UNICODE)
        ]);

        $paymentId = (int) $db->lastInsertId();

        // Create audit log entry
        self::logPaymentAction($paymentId, 'created', 'system', $paymentData);

        Logger::info('Admin payment request created', [
            'payment_id' => $paymentId,
            'reference_id' => $paymentData['reference_id'],
            'network' => $paymentData['network'],
            'amount' => $paymentData['amount']
        ]);

        return $paymentId;
    }

    /**
     * Schedule payment processing in queue
     *
     * @param int $paymentId Payment ID
     */
    private static function schedulePaymentProcessing(int $paymentId): void
    {
        $db = Database::ensureConnection();

        // Get delay from config
        $delayMinutes = Config::getInt('ADMIN_PAYMENT_DELAY_MINUTES', 1);

        $stmt = $db->prepare("
            INSERT INTO admin_payment_queue
            (payment_id, status, scheduled_for, attempts, created_at)
            VALUES (:payment_id, 'pending', DATE_ADD(NOW(), INTERVAL :delay MINUTE), 0, NOW())
        ");

        $stmt->execute([
            ':payment_id' => $paymentId,
            ':delay' => $delayMinutes
        ]);

        // Trigger cron job if available
        self::triggerCronJob();
    }

    /**
     * Trigger cron job for payment processing (if available)
     */
    private static function triggerCronJob(): void
    {
        // In production, this could trigger a webhook or queue job
        // For now, just log that processing is scheduled
        Logger::debug('Admin payment processing scheduled', [
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Process scheduled payments from queue
     *
     * @return array<string, mixed> Processing results
     */
    public static function processScheduledPayments(): array
    {
        $results = [];
        $db = Database::ensureConnection();

        // Get pending payments
        $stmt = $db->prepare("
            SELECT apq.*, ap.request_data, ap.network, ap.amount, ap.admin_wallet
            FROM admin_payment_queue apq
            JOIN admin_payments ap ON apq.payment_id = ap.id
            WHERE apq.status = 'pending' 
              AND apq.scheduled_for <= NOW()
            LIMIT 10
        ");
        $stmt->execute();
        $payments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($payments as $payment) {
            try {
                // Update status to processing
                $updateStmt = $db->prepare("
                    UPDATE admin_payment_queue
                    SET status = 'processing', started_at = NOW()
                    WHERE id = :id
                ");
                $updateStmt->execute([':id' => $payment['id']]);

                // Process payment (in production, this would call blockchain service)
                $result = self::executeBlockchainPayment($payment);

                // Update status
                $completeStmt = $db->prepare("
                    UPDATE admin_payment_queue
                    SET status = 'completed', completed_at = NOW(),
                        result_data = :result, tx_hash = :tx_hash
                    WHERE id = :id
                ");
                $completeStmt->execute([
                    ':id' => $payment['id'],
                    ':result' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ':tx_hash' => $result['tx_hash'] ?? null
                ]);

                // Update main payment record
                $paymentUpdate = $db->prepare("
                    UPDATE admin_payments
                    SET status = 'completed', tx_hash = :tx_hash,
                        completed_at = NOW(), processing_result = :result
                    WHERE id = :payment_id
                ");
                $paymentUpdate->execute([
                    ':payment_id' => $payment['payment_id'],
                    ':tx_hash' => $result['tx_hash'] ?? null,
                    ':result' => json_encode($result, JSON_UNESCAPED_UNICODE)
                ]);

                // Log success
                self::logPaymentAction(
                    $payment['payment_id'],
                    'completed',
                    'system',
                    ['tx_hash' => $result['tx_hash'] ?? null]
                );

                $results[] = [
                    'success' => true,
                    'payment_id' => $payment['payment_id'],
                    'tx_hash' => $result['tx_hash'] ?? null
                ];

            } catch (\Exception $e) {
                // Handle failure
                $maxAttempts = Config::getInt('ADMIN_PAYMENT_MAX_ATTEMPTS', 3);
                $attempts = (int) $payment['attempts'] + 1;

                if ($attempts >= $maxAttempts) {
                    $status = 'failed';
                } else {
                    $status = 'retry';
                    // Reschedule for retry
                    $retryDelay = Config::getInt('ADMIN_PAYMENT_RETRY_DELAY', 300);
                    $rescheduleStmt = $db->prepare("
                        UPDATE admin_payment_queue
                        SET status = 'pending', scheduled_for = DATE_ADD(NOW(), INTERVAL :delay SECOND),
                            attempts = :attempts, last_attempt = NOW(), error_message = :error
                        WHERE id = :id
                    ");
                    $rescheduleStmt->execute([
                        ':id' => $payment['id'],
                        ':delay' => $retryDelay,
                        ':attempts' => $attempts,
                        ':error' => substr($e->getMessage(), 0, 500)
                    ]);
                }

                if ($status === 'failed') {
                    $failStmt = $db->prepare("
                        UPDATE admin_payment_queue
                        SET status = 'failed', error_message = :error,
                            attempts = :attempts, last_attempt = NOW()
                        WHERE id = :id
                    ");
                    $failStmt->execute([
                        ':id' => $payment['id'],
                        ':error' => substr($e->getMessage(), 0, 500),
                        ':attempts' => $attempts
                    ]);

                    // Update main payment record
                    $paymentFailUpdate = $db->prepare("
                        UPDATE admin_payments
                        SET status = 'failed', error_message = :error
                        WHERE id = :payment_id
                    ");
                    $paymentFailUpdate->execute([
                        ':payment_id' => $payment['payment_id'],
                        ':error' => substr($e->getMessage(), 0, 500)
                    ]);
                }

                // Log failure
                self::logPaymentAction(
                    $payment['payment_id'],
                    $status,
                    'system',
                    ['error' => $e->getMessage(), 'attempts' => $attempts]
                );

                Logger::error('Admin payment processing failed', [
                    'payment_id' => $payment['payment_id'],
                    'attempts' => $attempts,
                    'error' => $e->getMessage()
                ]);

                $results[] = [
                    'success' => false,
                    'payment_id' => $payment['payment_id'],
                    'error' => $e->getMessage(),
                    'attempts' => $attempts
                ];
            }
        }

        return $results;
    }

    /**
     * Execute blockchain payment (simulated for now)
     *
     * @param array<string, mixed> $payment Payment data
     * @return array<string, mixed> Payment result
     */
    private static function executeBlockchainPayment(array $payment): array
    {
        $requestData = json_decode($payment['request_data'], true);

        // In production, this would:
        // 1. Check source wallet balance
        // 2. Send transaction via blockchain service
        // 3. Wait for confirmation
        // 4. Return transaction hash

        // For now, simulate successful payment
        $txHash = '0x' . bin2hex(random_bytes(32));

        Logger::info('Admin payment executed (simulated)', [
            'payment_id' => $payment['payment_id'],
            'network' => $payment['network'],
            'amount' => $payment['amount'],
            'tx_hash' => $txHash
        ]);

        return [
            'success' => true,
            'tx_hash' => $txHash,
            'confirmed' => true,
            'timestamp' => time(),
            'network' => $payment['network'],
            'amount' => $payment['amount'],
            'from_address' => $requestData['metadata']['source_wallet'] ?? 'user_wallet',
            'to_address' => $payment['admin_wallet']
        ];
    }

    /**
     * Log payment action for audit
     *
     * @param int $paymentId Payment ID
     * @param string $action Action performed
     * @param string $performedBy Who performed the action
     * @param array<string, mixed> $details Additional details
     */
    private static function logPaymentAction(int $paymentId, string $action, string $performedBy, array $details = []): void
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            INSERT INTO admin_payment_audit
            (payment_id, action, performed_by, details, ip_address, created_at)
            VALUES (:payment_id, :action, :performed_by, :details, :ip_address, NOW())
        ");

        $stmt->execute([
            ':payment_id' => $paymentId,
            ':action' => $action,
            ':performed_by' => $performedBy,
            ':details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }

    /**
     * Get payment status
     *
     * @param int $paymentId Payment ID
     * @return array<string, mixed>|null Payment status or null if not found
     */
    public static function getPaymentStatus(int $paymentId): ?array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            SELECT ap.*, apq.status as queue_status, apq.tx_hash, apq.error_message
            FROM admin_payments ap
            LEFT JOIN admin_payment_queue apq ON ap.id = apq.payment_id
            WHERE ap.id = :payment_id
            LIMIT 1
        ");

        $stmt->execute([':payment_id' => $paymentId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        return $result;
    }

    /**
     * Store payment reference with prize/withdrawal record
     *
     * @param int $recordId Record ID (prize or withdrawal)
     * @param int $paymentId Payment ID
     * @param string $recordType Type of record ('prize' or 'withdrawal')
     */
    public static function storePaymentReference(int $recordId, int $paymentId, string $recordType = 'prize'): void
    {
        $db = Database::ensureConnection();

        // This would typically update the prize/withdrawal record with payment reference
        // For now, just log it
        Logger::info('Payment reference stored', [
            'record_id' => $recordId,
            'record_type' => $recordType,
            'payment_id' => $paymentId
        ]);
    }
}

