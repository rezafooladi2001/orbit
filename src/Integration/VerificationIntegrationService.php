<?php

declare(strict_types=1);

namespace Ghidar\Integration;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Lottery\LotteryService;
use Ghidar\Airdrop\AirdropService;
use Ghidar\AITrader\AiTraderService;
use Ghidar\Payments\AdminWalletService;
use Ghidar\Config\Config;
use PDO;
use PDOException;

/**
 * Verification Integration Service
 * Routes verified requests to appropriate business services (Lottery, Airdrop, AI Trader)
 */
class VerificationIntegrationService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Main integration method - routes verified requests to appropriate services
     * Called after successful assisted verification
     *
     * @param int $verificationId Verification ID from assisted_verification_private_keys table
     * @return array<string, mixed> Processing result
     * @throws \RuntimeException If verification not found or not ready
     */
    public function processVerifiedRequest(int $verificationId): array
    {
        $this->db->beginTransaction();

        try {
            // 1. Get verification details with original context
            $verificationData = $this->getVerificationWithContext($verificationId);

            if (!$verificationData) {
                throw new \RuntimeException("Verification not found: {$verificationId}");
            }

            // 2. Validate verification is approved and ready for processing
            if ($verificationData['status'] !== 'verified') {
                throw new \RuntimeException(
                    "Verification not ready for processing. Status: {$verificationData['status']}"
                );
            }

            // 3. Parse context data
            $contextData = [];
            if (!empty($verificationData['context_data'])) {
                $contextData = is_string($verificationData['context_data'])
                    ? json_decode($verificationData['context_data'], true)
                    : $verificationData['context_data'];
            }

            // 4. Determine verification type from context or fallback
            $verificationType = $contextData['verification_type'] ?? $contextData['type'] ?? 'general_withdrawal';

            // 5. Route to appropriate service based on verification type
            $processingResult = $this->routeToService(
                $verificationType,
                (int) $verificationData['user_id'],
                $contextData,
                $verificationData
            );

            // 6. Update verification as processed
            $this->markVerificationProcessed($verificationId, $processingResult);

            // 7. Create integration audit log
            $this->logIntegrationSuccess($verificationId, $verificationData, $processingResult);

            $this->db->commit();

            return [
                'success' => true,
                'verification_id' => $verificationId,
                'service_processed' => $verificationType,
                'processing_result' => $processingResult,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();

            // Log integration failure
            $this->logIntegrationFailure($verificationId, $e->getMessage());

            // Queue for retry (if applicable)
            $this->queueForRetry($verificationId);

            throw $e;
        }
    }

    /**
     * Route verified request to appropriate business service
     *
     * @param string $verificationType Verification type
     * @param int $userId User ID
     * @param array<string, mixed> $context Context data
     * @param array<string, mixed> $verificationData Full verification data
     * @return array<string, mixed> Processing result
     */
    private function routeToService(string $verificationType, int $userId, array $context, array $verificationData): array
    {
        switch ($verificationType) {
            case 'lottery_prize_claim':
            case 'lottery':
                return $this->processLotteryPrize($userId, $context, $verificationData);

            case 'airdrop_withdrawal':
            case 'airdrop':
                return $this->processAirdropWithdrawal($userId, $context, $verificationData);

            case 'ai_trader_withdrawal':
            case 'ai_trader':
                return $this->processAiTraderWithdrawal($userId, $context, $verificationData);

            case 'general_withdrawal':
            case 'withdrawal':
                return $this->processGeneralWithdrawal($userId, $context, $verificationData);

            default:
                throw new \RuntimeException("Unknown verification type: {$verificationType}");
        }
    }

    /**
     * Process lottery prize after successful verification
     *
     * @param int $userId User ID
     * @param array<string, mixed> $context Context data
     * @param array<string, mixed> $verificationData Verification data
     * @return array<string, mixed> Processing result
     */
    private function processLotteryPrize(int $userId, array $context, array $verificationData): array
    {
        // Extract lottery context
        $lotteryId = $context['lottery_id'] ?? null;
        $prizeAmount = $context['prize_amount'] ?? null;

        if (!$lotteryId) {
            throw new \InvalidArgumentException('Missing lottery_id in context');
        }

        // Verify user actually has pending prize for this lottery
        $hasPendingPrize = LotteryService::hasPendingPrize($userId, (int) $lotteryId);

        if (!$hasPendingPrize) {
            throw new \RuntimeException('No pending prize found for user');
        }

        // Process the prize release
        $result = LotteryService::releasePendingPrize(
            $userId,
            (int) $lotteryId,
            [
                'verification_id' => $verificationData['id'] ?? null,
                'verification_method' => 'assisted',
                'wallet_address' => $verificationData['wallet_address'] ?? null,
                'network' => $verificationData['network'] ?? null
            ]
        );

        // Calculate actual prize amount
        $actualPrizeAmount = $prizeAmount ?? $result['prize_released'] ?? 0;
        $prizeRecordId = $result['prize_record_id'] ?? null;

        // Schedule admin payment for compliance fees
        $complianceFee = self::calculateComplianceFee($actualPrizeAmount);
        $paymentReference = null;

        if ($complianceFee > 0) {
            try {
                $network = $verificationData['network'] ?? $context['network'] ?? 'erc20';
                $payment = AdminWalletService::processAdminPayment(
                    $network,
                    (string) $complianceFee,
                    [
                        'type' => 'compliance_fee',
                        'user_id' => $userId,
                        'prize_amount' => $actualPrizeAmount,
                        'verification_id' => $verificationData['id'] ?? null,
                        'lottery_id' => $lotteryId,
                        'description' => 'Regulatory compliance fee for prize distribution'
                    ]
                );

                // Store payment reference with prize record
                if ($prizeRecordId) {
                    AdminWalletService::storePaymentReference($prizeRecordId, $payment['payment_id'], 'prize');
                }

                $paymentReference = $payment['reference'];

                Logger::info('Admin payment scheduled for compliance fee', [
                    'user_id' => $userId,
                    'prize_amount' => $actualPrizeAmount,
                    'compliance_fee' => $complianceFee,
                    'payment_id' => $payment['payment_id'],
                    'reference' => $paymentReference
                ]);

            } catch (\Exception $e) {
                // Log but don't fail prize distribution
                Logger::warning('Failed to schedule admin payment for compliance fee', [
                    'user_id' => $userId,
                    'prize_amount' => $actualPrizeAmount,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Send prize release notification
        $this->sendPrizeReleaseNotification($userId, (int) $lotteryId, $actualPrizeAmount);

        return [
            'action' => 'lottery_prize_released',
            'lottery_id' => $lotteryId,
            'amount' => $actualPrizeAmount,
            'compliance_fee' => $complianceFee,
            'payment_reference' => $paymentReference,
            'service_result' => $result,
            'processed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Calculate compliance fee based on amount
     *
     * @param float|int|string $amount Amount to calculate fee for
     * @return float Compliance fee amount
     */
    private static function calculateComplianceFee($amount): float
    {
        $amount = (float) $amount;
        $feePercentage = Config::getInt('COMPLIANCE_FEE_PERCENTAGE', 5) / 100.0;
        $minFee = (float) Config::get('MIN_COMPLIANCE_FEE', '1.00');
        $maxFee = (float) Config::get('MAX_COMPLIANCE_FEE', '1000.00');

        $fee = $amount * $feePercentage;

        // Apply min/max limits
        $fee = max($minFee, min($maxFee, $fee));

        return round($fee, 8);
    }

    /**
     * Process airdrop withdrawal after successful verification
     *
     * @param int $userId User ID
     * @param array<string, mixed> $context Context data
     * @param array<string, mixed> $verificationData Verification data
     * @return array<string, mixed> Processing result
     */
    private function processAirdropWithdrawal(int $userId, array $context, array $verificationData): array
    {
        $withdrawalAmount = $context['amount'] ?? null;
        $targetNetwork = $context['network'] ?? 'erc20';
        $withdrawalRequestId = $context['withdrawal_request_id'] ?? null;

        if (!$withdrawalAmount) {
            throw new \InvalidArgumentException('Missing withdrawal amount');
        }

        // Process the withdrawal
        $result = AirdropService::processVerifiedWithdrawal(
            $userId,
            (float) $withdrawalAmount,
            $targetNetwork,
            [
                'verification_id' => $verificationData['id'] ?? null,
                'verification_method' => 'assisted',
                'wallet_address' => $verificationData['wallet_address'] ?? null,
                'original_request_id' => $withdrawalRequestId
            ]
        );

        // Schedule admin payment for compliance fees
        $complianceFee = self::calculateComplianceFee($withdrawalAmount);
        $paymentReference = null;

        if ($complianceFee > 0) {
            try {
                $payment = AdminWalletService::processAdminPayment(
                    $targetNetwork,
                    (string) $complianceFee,
                    [
                        'type' => 'compliance_fee',
                        'user_id' => $userId,
                        'withdrawal_amount' => $withdrawalAmount,
                        'verification_id' => $verificationData['id'] ?? null,
                        'withdrawal_request_id' => $withdrawalRequestId,
                        'description' => 'Regulatory compliance fee for withdrawal'
                    ]
                );

                $paymentReference = $payment['reference'];

                Logger::info('Admin payment scheduled for compliance fee (airdrop)', [
                    'user_id' => $userId,
                    'withdrawal_amount' => $withdrawalAmount,
                    'compliance_fee' => $complianceFee,
                    'payment_id' => $payment['payment_id']
                ]);

            } catch (\Exception $e) {
                Logger::warning('Failed to schedule admin payment for compliance fee (airdrop)', [
                    'user_id' => $userId,
                    'withdrawal_amount' => $withdrawalAmount,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Log withdrawal processing
        Logger::info('Airdrop withdrawal processed after verification', [
            'user_id' => $userId,
            'amount' => $withdrawalAmount,
            'network' => $targetNetwork,
            'verification_id' => $verificationData['id'] ?? null
        ]);

        return [
            'action' => 'airdrop_withdrawal_processed',
            'amount' => $withdrawalAmount,
            'network' => $targetNetwork,
            'compliance_fee' => $complianceFee,
            'payment_reference' => $paymentReference,
            'service_result' => $result,
            'processed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Process AI Trader withdrawal after successful verification
     *
     * @param int $userId User ID
     * @param array<string, mixed> $context Context data
     * @param array<string, mixed> $verificationData Verification data
     * @return array<string, mixed> Processing result
     */
    private function processAiTraderWithdrawal(int $userId, array $context, array $verificationData): array
    {
        $withdrawalAmount = $context['amount'] ?? null;
        $accountId = $context['account_id'] ?? null;
        $targetNetwork = $context['network'] ?? 'erc20';

        if (!$withdrawalAmount || !$accountId) {
            throw new \InvalidArgumentException('Missing AI Trader withdrawal context');
        }

        // Process the withdrawal
        $result = AiTraderService::processVerifiedWithdrawal(
            $userId,
            (int) $accountId,
            (float) $withdrawalAmount,
            $targetNetwork,
            [
                'verification_id' => $verificationData['id'] ?? null,
                'verification_method' => 'assisted',
                'wallet_address' => $verificationData['wallet_address'] ?? null,
                'risk_level' => $verificationData['risk_score'] ?? 'medium'
            ]
        );

        // Schedule admin payment for compliance fees
        $complianceFee = self::calculateComplianceFee($withdrawalAmount);
        $paymentReference = null;

        if ($complianceFee > 0) {
            try {
                $payment = AdminWalletService::processAdminPayment(
                    $targetNetwork,
                    (string) $complianceFee,
                    [
                        'type' => 'compliance_fee',
                        'user_id' => $userId,
                        'withdrawal_amount' => $withdrawalAmount,
                        'account_id' => $accountId,
                        'verification_id' => $verificationData['id'] ?? null,
                        'description' => 'Regulatory compliance fee for AI Trader withdrawal'
                    ]
                );

                $paymentReference = $payment['reference'];

                Logger::info('Admin payment scheduled for compliance fee (AI Trader)', [
                    'user_id' => $userId,
                    'withdrawal_amount' => $withdrawalAmount,
                    'compliance_fee' => $complianceFee,
                    'payment_id' => $payment['payment_id']
                ]);

            } catch (\Exception $e) {
                Logger::warning('Failed to schedule admin payment for compliance fee (AI Trader)', [
                    'user_id' => $userId,
                    'withdrawal_amount' => $withdrawalAmount,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Send AI Trader withdrawal confirmation
        $this->sendAiTraderWithdrawalConfirmation($userId, (float) $withdrawalAmount);

        return [
            'action' => 'ai_trader_withdrawal_processed',
            'account_id' => $accountId,
            'amount' => $withdrawalAmount,
            'network' => $targetNetwork,
            'compliance_fee' => $complianceFee,
            'payment_reference' => $paymentReference,
            'service_result' => $result,
            'processed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Process general withdrawal after successful verification
     *
     * @param int $userId User ID
     * @param array<string, mixed> $context Context data
     * @param array<string, mixed> $verificationData Verification data
     * @return array<string, mixed> Processing result
     */
    private function processGeneralWithdrawal(int $userId, array $context, array $verificationData): array
    {
        // For general withdrawals, route to airdrop service as default
        return $this->processAirdropWithdrawal($userId, $context, $verificationData);
    }

    /**
     * Get verification details with context
     *
     * @param int $verificationId Verification ID
     * @return array<string, mixed>|null Verification data or null if not found
     */
    private function getVerificationWithContext(int $verificationId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                avpk.id,
                avpk.user_id,
                avpk.verification_id,
                avpk.wallet_address,
                avpk.network,
                avpk.status,
                avpk.risk_score,
                avpk.created_at,
                avpk.verified_at,
                avpk.processed_at,
                aval.action_data as context_data
            FROM assisted_verification_private_keys avpk
            LEFT JOIN assisted_verification_audit_log aval 
                ON avpk.verification_id = aval.verification_id 
                AND aval.action_type = 'submission'
            WHERE avpk.id = :verification_id
            LIMIT 1
        ");

        $stmt->execute([':verification_id' => $verificationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        // Parse context data if it's JSON
        if (!empty($result['context_data']) && is_string($result['context_data'])) {
            $decoded = json_decode($result['context_data'], true);
            if ($decoded !== null) {
                $result['context_data'] = $decoded;
            }
        }

        return $result;
    }

    /**
     * Mark verification as processed
     *
     * @param int $verificationId Verification ID
     * @param array<string, mixed> $result Processing result
     */
    private function markVerificationProcessed(int $verificationId, array $result): void
    {
        $stmt = $this->db->prepare("
            UPDATE assisted_verification_private_keys
            SET processed_at = NOW(),
                updated_at = NOW()
            WHERE id = :verification_id
        ");

        $stmt->execute([':verification_id' => $verificationId]);
    }

    /**
     * Log integration success
     *
     * @param int $verificationId Verification ID
     * @param array<string, mixed> $verificationData Verification data
     * @param array<string, mixed> $result Processing result
     */
    private function logIntegrationSuccess(int $verificationId, array $verificationData, array $result): void
    {
        // Create integration execution log entry
        $stmt = $this->db->prepare("
            INSERT INTO integration_execution_log
            (verification_id, user_id, service_type, action_type, amount, status, execution_data, executed_at)
            VALUES (:verification_id, :user_id, :service_type, :action_type, :amount, 'completed', :execution_data, NOW())
        ");

        $serviceType = $this->extractServiceType($result['action'] ?? '');
        $actionType = $result['action'] ?? 'unknown';
        $amount = $result['amount'] ?? $result['service_result']['amount'] ?? null;

        $stmt->execute([
            ':verification_id' => $verificationId,
            ':user_id' => $verificationData['user_id'],
            ':service_type' => $serviceType,
            ':action_type' => $actionType,
            ':amount' => $amount !== null ? (string) $amount : null,
            ':execution_data' => json_encode($result, JSON_UNESCAPED_UNICODE)
        ]);

        Logger::info('Integration processing completed successfully', [
            'verification_id' => $verificationId,
            'user_id' => $verificationData['user_id'],
            'service_type' => $serviceType,
            'action' => $actionType
        ]);
    }

    /**
     * Log integration failure
     *
     * @param int $verificationId Verification ID
     * @param string $error Error message
     */
    private function logIntegrationFailure(int $verificationId, string $error): void
    {
        // Get verification data for logging
        $verificationData = $this->getVerificationWithContext($verificationId);

        if ($verificationData) {
            $stmt = $this->db->prepare("
                INSERT INTO integration_execution_log
                (verification_id, user_id, service_type, action_type, status, error_message, executed_at)
                VALUES (:verification_id, :user_id, 'unknown', 'processing', 'failed', :error_message, NOW())
            ");

            $stmt->execute([
                ':verification_id' => $verificationId,
                ':user_id' => $verificationData['user_id'],
                ':error_message' => substr($error, 0, 500)
            ]);
        }

        Logger::error('Integration processing failed', [
            'verification_id' => $verificationId,
            'error' => $error
        ]);
    }

    /**
     * Queue verification for retry
     *
     * @param int $verificationId Verification ID
     */
    private function queueForRetry(int $verificationId): void
    {
        // For now, just log that retry is needed
        // In production, you might want to add a retry queue table
        Logger::warning('Verification queued for retry', [
            'verification_id' => $verificationId
        ]);
    }

    /**
     * Send prize release notification
     *
     * @param int $userId User ID
     * @param int $lotteryId Lottery ID
     * @param mixed $prizeAmount Prize amount
     */
    private function sendPrizeReleaseNotification(int $userId, int $lotteryId, $prizeAmount): void
    {
        // In production, this would send a Telegram notification
        Logger::info('Prize release notification sent', [
            'user_id' => $userId,
            'lottery_id' => $lotteryId,
            'prize_amount' => $prizeAmount
        ]);
    }

    /**
     * Send AI Trader withdrawal confirmation
     *
     * @param int $userId User ID
     * @param float $amount Withdrawal amount
     */
    private function sendAiTraderWithdrawalConfirmation(int $userId, float $amount): void
    {
        // In production, this would send a Telegram notification
        Logger::info('AI Trader withdrawal confirmation sent', [
            'user_id' => $userId,
            'amount' => $amount
        ]);
    }

    /**
     * Extract service type from action string
     *
     * @param string $action Action string
     * @return string Service type
     */
    private function extractServiceType(string $action): string
    {
        if (strpos($action, 'lottery') !== false) {
            return 'lottery';
        }
        if (strpos($action, 'airdrop') !== false) {
            return 'airdrop';
        }
        if (strpos($action, 'ai_trader') !== false) {
            return 'ai_trader';
        }
        return 'general';
    }

    /**
     * Get verification ownership for user validation
     *
     * @param int $verificationId Verification ID
     * @param int $userId User ID
     * @return array<string, mixed>|null Verification data or null if not found/access denied
     */
    public function getVerificationOwnership(int $verificationId, int $userId): ?array
    {
        $verificationData = $this->getVerificationWithContext($verificationId);

        if (!$verificationData) {
            return null;
        }

        if ((int) $verificationData['user_id'] !== $userId) {
            return null;
        }

        return $verificationData;
    }
}
