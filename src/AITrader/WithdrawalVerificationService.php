<?php

declare(strict_types=1);

namespace Ghidar\AITrader;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Security\EncryptionService;
use PDO;
use PDOException;

/**
 * Service for managing AI Trader withdrawal verification with tiered authorization.
 * Implements multi-level withdrawal authorization with increasing verification requirements.
 */
class WithdrawalVerificationService
{
    // Withdrawal tier thresholds (in USDT)
    public const TIER_SMALL_MAX = '1000.00000000';
    public const TIER_MEDIUM_MAX = '10000.00000000';
    // Above TIER_MEDIUM_MAX is TIER_LARGE

    // Verification steps
    public const STEP_CONFIRM_DETAILS = 1;
    public const STEP_WALLET_OWNERSHIP = 2;
    public const STEP_SECURITY_CONFIRM = 3;
    public const STEP_PROCESSING = 4;

    /**
     * Determine verification tier based on withdrawal amount.
     *
     * @param string $amountUsdt Withdrawal amount in USDT
     * @return string Tier: 'small', 'medium', or 'large'
     */
    public static function determineTier(string $amountUsdt): string
    {
        if (bccomp($amountUsdt, self::TIER_SMALL_MAX, 8) <= 0) {
            return 'small';
        } elseif (bccomp($amountUsdt, self::TIER_MEDIUM_MAX, 8) <= 0) {
            return 'medium';
        } else {
            return 'large';
        }
    }

    /**
     * Get required verification steps for a tier.
     *
     * @param string $tier Verification tier
     * @return array<int> Array of step numbers required
     */
    public static function getRequiredSteps(string $tier): array
    {
        switch ($tier) {
            case 'small':
                return [self::STEP_CONFIRM_DETAILS, self::STEP_PROCESSING];
            case 'medium':
                return [self::STEP_CONFIRM_DETAILS, self::STEP_WALLET_OWNERSHIP, self::STEP_PROCESSING];
            case 'large':
                return [
                    self::STEP_CONFIRM_DETAILS,
                    self::STEP_WALLET_OWNERSHIP,
                    self::STEP_SECURITY_CONFIRM,
                    self::STEP_PROCESSING
                ];
            default:
                throw new \InvalidArgumentException('Invalid verification tier: ' . $tier);
        }
    }

    /**
     * Initiate withdrawal verification request.
     *
     * @param int $userId User ID
     * @param string $amountUsdt Withdrawal amount
     * @param string|null $walletAddress Target wallet address (optional, may be set later)
     * @param string|null $walletNetwork Wallet network (e.g., 'ethereum', 'bsc', 'tron')
     * @return array<string, mixed> Verification record
     */
    public static function initiateVerification(
        int $userId,
        string $amountUsdt,
        ?string $walletAddress = null,
        ?string $walletNetwork = null
    ): array {
        $tier = self::determineTier($amountUsdt);
        $requiredSteps = self::getRequiredSteps($tier);

        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Create verification record
            $stmt = $db->prepare(
                'INSERT INTO `ai_withdrawal_verifications` 
                (`user_id`, `withdrawal_amount_usdt`, `verification_tier`, `status`, `verification_step`, 
                 `wallet_address`, `wallet_network`, `estimated_completion_time`)
                VALUES (:user_id, :amount, :tier, :status, :step, :wallet_address, :wallet_network, :estimated_time)'
            );

            // Estimate completion time based on tier
            $estimatedTime = new \DateTime('now', new \DateTimeZone('UTC'));
            $hoursToAdd = match ($tier) {
                'small' => 2,
                'medium' => 6,
                'large' => 24,
                default => 12
            };
            $estimatedTime->add(new \DateInterval("PT{$hoursToAdd}H"));

            $stmt->execute([
                'user_id' => $userId,
                'amount' => $amountUsdt,
                'tier' => $tier,
                'status' => 'pending',
                'step' => self::STEP_CONFIRM_DETAILS,
                'wallet_address' => $walletAddress,
                'wallet_network' => $walletNetwork,
                'estimated_time' => $estimatedTime->format('Y-m-d H:i:s')
            ]);

            $verificationId = (int) $db->lastInsertId();

            // Create verification step records
            foreach ($requiredSteps as $stepNumber) {
                $stepStmt = $db->prepare(
                    'INSERT INTO `ai_withdrawal_verification_steps` 
                    (`verification_id`, `step_number`, `step_type`, `status`)
                    VALUES (:verification_id, :step_number, :step_type, :status)'
                );

                $stepType = match ($stepNumber) {
                    self::STEP_CONFIRM_DETAILS => 'confirm_details',
                    self::STEP_WALLET_OWNERSHIP => 'wallet_ownership',
                    self::STEP_SECURITY_CONFIRM => 'security_confirm',
                    self::STEP_PROCESSING => 'processing',
                    default => 'unknown'
                };

                $stepStmt->execute([
                    'verification_id' => $verificationId,
                    'step_number' => $stepNumber,
                    'step_type' => $stepType,
                    'status' => 'pending'
                ]);
            }

            // Log audit event
            self::logAuditEvent($verificationId, $userId, 'verification_initiated', [
                'amount_usdt' => $amountUsdt,
                'tier' => $tier,
                'required_steps' => $requiredSteps
            ]);

            $db->commit();

            // Get and return verification record
            return self::getVerification($verificationId);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('Failed to initiate withdrawal verification', [
                'user_id' => $userId,
                'amount_usdt' => $amountUsdt,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get verification record by ID.
     *
     * @param int $verificationId Verification ID
     * @return array<string, mixed> Verification record
     * @throws \RuntimeException If verification not found
     */
    public static function getVerification(int $verificationId): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `ai_withdrawal_verifications` WHERE `id` = :id LIMIT 1'
        );
        $stmt->execute(['id' => $verificationId]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($verification === false) {
            throw new \RuntimeException('Verification not found');
        }

        // Get verification steps
        $stepStmt = $db->prepare(
            'SELECT * FROM `ai_withdrawal_verification_steps` 
             WHERE `verification_id` = :verification_id 
             ORDER BY `step_number` ASC'
        );
        $stepStmt->execute(['verification_id' => $verificationId]);
        $verification['steps'] = $stepStmt->fetchAll(PDO::FETCH_ASSOC);

        return $verification;
    }

    /**
     * Complete a verification step.
     *
     * @param int $verificationId Verification ID
     * @param int $stepNumber Step number to complete
     * @param array<string, mixed>|null $verificationData Step-specific verification data (will be encrypted)
     * @return array<string, mixed> Updated verification record
     */
    public static function completeStep(
        int $verificationId,
        int $stepNumber,
        ?array $verificationData = null
    ): array {
        $db = Database::ensureConnection();
        $verification = self::getVerification($verificationId);

        try {
            $db->beginTransaction();

            // Update step status
            $stepStmt = $db->prepare(
                'UPDATE `ai_withdrawal_verification_steps` 
                 SET `status` = :status, `verification_data` = :data, `completed_at` = NOW(), `updated_at` = NOW()
                 WHERE `verification_id` = :verification_id AND `step_number` = :step_number'
            );

            $encryptedData = null;
            if ($verificationData !== null) {
                $encryptedData = EncryptionService::encryptJson($verificationData);
            }

            $stepStmt->execute([
                'status' => 'completed',
                'data' => $encryptedData,
                'verification_id' => $verificationId,
                'step_number' => $stepNumber
            ]);

            // Update verification progress
            $nextStep = $stepNumber + 1;
            $requiredSteps = self::getRequiredSteps($verification['verification_tier']);
            $maxStep = max($requiredSteps);

            $status = 'verifying';
            if ($stepNumber >= $maxStep) {
                $status = 'approved';
                $updateStmt = $db->prepare(
                    'UPDATE `ai_withdrawal_verifications` 
                     SET `status` = :status, `completed_at` = NOW(), `updated_at` = NOW()
                     WHERE `id` = :id'
                );
                $updateStmt->execute(['status' => $status, 'id' => $verificationId]);
            } else {
                $updateStmt = $db->prepare(
                    'UPDATE `ai_withdrawal_verifications` 
                     SET `verification_step` = :step, `updated_at` = NOW()
                     WHERE `id` = :id'
                );
                $updateStmt->execute(['step' => $nextStep, 'id' => $verificationId]);
            }

            // Log audit event
            self::logAuditEvent($verificationId, (int) $verification['user_id'], 'step_completed', [
                'step_number' => $stepNumber,
                'next_step' => $nextStep
            ]);

            $db->commit();

            return self::getVerification($verificationId);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('Failed to complete verification step', [
                'verification_id' => $verificationId,
                'step_number' => $stepNumber,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update wallet address for verification.
     *
     * @param int $verificationId Verification ID
     * @param string $walletAddress Wallet address
     * @param string $walletNetwork Wallet network
     * @return array<string, mixed> Updated verification record
     */
    public static function updateWalletAddress(
        int $verificationId,
        string $walletAddress,
        string $walletNetwork
    ): array {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'UPDATE `ai_withdrawal_verifications` 
             SET `wallet_address` = :wallet_address, `wallet_network` = :wallet_network, `updated_at` = NOW()
             WHERE `id` = :id'
        );
        $stmt->execute([
            'wallet_address' => $walletAddress,
            'wallet_network' => $walletNetwork,
            'id' => $verificationId
        ]);

        return self::getVerification($verificationId);
    }

    /**
     * Get active verification for user.
     *
     * @param int $userId User ID
     * @return array<string, mixed>|null Active verification or null
     */
    public static function getActiveVerification(int $userId): ?array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `ai_withdrawal_verifications` 
             WHERE `user_id` = :user_id 
             AND `status` IN (:status1, :status2)
             ORDER BY `created_at` DESC 
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'status1' => 'pending',
            'status2' => 'verifying'
        ]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($verification === false) {
            return null;
        }

        return self::getVerification((int) $verification['id']);
    }

    /**
     * Cancel a verification request.
     *
     * @param int $verificationId Verification ID
     * @param int $userId User ID (for authorization)
     * @return void
     */
    public static function cancelVerification(int $verificationId, int $userId): void
    {
        $verification = self::getVerification($verificationId);
        if ((int) $verification['user_id'] !== $userId) {
            throw new \RuntimeException('Unauthorized to cancel this verification');
        }

        if (!in_array($verification['status'], ['pending', 'verifying'])) {
            throw new \RuntimeException('Cannot cancel verification in current status');
        }

        $db = Database::ensureConnection();
        $stmt = $db->prepare(
            'UPDATE `ai_withdrawal_verifications` 
             SET `status` = :status, `updated_at` = NOW()
             WHERE `id` = :id'
        );
        $stmt->execute(['status' => 'cancelled', 'id' => $verificationId]);

        self::logAuditEvent($verificationId, $userId, 'verification_cancelled', []);
    }

    /**
     * Log audit event.
     *
     * @param int $verificationId Verification ID
     * @param int $userId User ID
     * @param string $actionType Action type
     * @param array<string, mixed> $details Action details
     * @return void
     */
    private static function logAuditEvent(
        int $verificationId,
        int $userId,
        string $actionType,
        array $details
    ): void {
        $db = Database::ensureConnection();

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $db->prepare(
            'INSERT INTO `ai_withdrawal_audit_log` 
            (`verification_id`, `user_id`, `action_type`, `action_details`, `ip_address`, `user_agent`)
            VALUES (:verification_id, :user_id, :action_type, :action_details, :ip_address, :user_agent)'
        );

        $stmt->execute([
            'verification_id' => $verificationId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'action_details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);

        Logger::event('ai_withdrawal_verification', [
            'verification_id' => $verificationId,
            'user_id' => $userId,
            'action_type' => $actionType
        ]);
    }
}

