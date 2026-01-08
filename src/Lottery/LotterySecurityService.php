<?php

declare(strict_types=1);

namespace Ghidar\Lottery;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use PDO;
use PDOException;

/**
 * Lottery Security Service
 * Handles enhanced security verification for lottery rewards
 */
class LotterySecurityService
{
    /**
     * Process lottery reward verification
     *
     * @param int $rewardId Reward ID
     * @param int $userId User ID
     * @return array<string, mixed> Processing result
     * @throws \RuntimeException If reward not found or access denied
     */
    public static function processLotteryRewardVerification(int $rewardId, int $userId): array
    {
        $db = Database::ensureConnection();

        // Get reward details
        $reward = self::getReward($rewardId, $userId);

        if (!$reward) {
            throw new \RuntimeException('Reward not found or access denied');
        }

        if ($reward['status'] !== 'pending_verification') {
            throw new \RuntimeException('Reward already processed');
        }

        // Check if verification already exists
        $verificationId = self::getExistingVerification($userId, 'lottery_reward', $rewardId);

        if (!$verificationId) {
            // Create enhanced verification request
            $verificationId = self::createEnhancedVerificationRequest([
                'user_id' => $userId,
                'reward_id' => $rewardId,
                'amount' => $reward['amount_usdt'],
                'type' => 'lottery_reward',
                'verification_level' => 'enhanced',
                'deadline' => $reward['expires_at']
            ]);
        }

        // Update reward status
        self::updateRewardStatus($rewardId, 'verification_pending', $verificationId);

        return [
            'success' => true,
            'reward_id' => $rewardId,
            'verification_id' => $verificationId,
            'amount' => $reward['amount_usdt'],
            'verification_required' => true,
            'verification_type' => 'enhanced_wallet_verification',
            'message' => 'Enhanced security verification required for lottery reward due to regulatory compliance requirements.',
            'compliance_reference' => 'FATF-R16-LOTTERY-VERIFICATION',
            'next_steps' => [
                'Complete wallet ownership verification',
                'Submit required documentation',
                'Wait for compliance approval',
                'Receive processed reward'
            ]
        ];
    }

    /**
     * Get reward details
     *
     * @param int $rewardId Reward ID
     * @param int $userId User ID
     * @return array<string, mixed>|null Reward data or null if not found
     */
    private static function getReward(int $rewardId, int $userId): ?array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            SELECT * FROM lottery_security_rewards
            WHERE id = :reward_id AND user_id = :user_id
        ");

        $stmt->execute([
            ':reward_id' => $rewardId,
            ':user_id' => $userId
        ]);

        $reward = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reward !== false ? $reward : null;
    }

    /**
     * Get existing verification for user
     *
     * @param int $userId User ID
     * @param string $type Verification type
     * @param int $rewardId Reward ID
     * @return int|null Verification ID or null
     */
    private static function getExistingVerification(int $userId, string $type, int $rewardId): ?int
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            SELECT id FROM enhanced_verification_requests
            WHERE user_id = :user_id
            AND request_type = :type
            AND metadata->>'$.reward_id' = :reward_id
            AND status = 'pending'
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':reward_id' => (string) $rewardId
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['id'] : null;
    }

    /**
     * Create enhanced verification request
     *
     * @param array<string, mixed> $data Verification data
     * @return int Verification request ID
     */
    private static function createEnhancedVerificationRequest(array $data): int
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            INSERT INTO enhanced_verification_requests 
            (user_id, request_type, amount, verification_level, status, 
             metadata, expires_at, created_at)
            VALUES (:user_id, :request_type, :amount, :verification_level, 'pending',
                    :metadata, :expires_at, NOW())
        ");

        $metadata = [
            'reward_id' => $data['reward_id'] ?? null,
            'compliance_required' => true,
            'regulations' => ['FATF_R16', 'SEC_15c3-3', 'EU_MiCA'],
            'risk_assessment' => 'high_value_transaction',
            'documentation_required' => ['wallet_ownership', 'source_of_funds']
        ];

        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':request_type' => $data['type'],
            ':amount' => $data['amount'],
            ':verification_level' => $data['verification_level'],
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            ':expires_at' => $data['deadline']
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Update reward status
     *
     * @param int $rewardId Reward ID
     * @param string $status New status
     * @param int|null $verificationId Verification ID
     */
    private static function updateRewardStatus(int $rewardId, string $status, ?int $verificationId = null): void
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            UPDATE lottery_security_rewards 
            SET status = :status,
                verification_id = :verification_id,
                updated_at = NOW()
            WHERE id = :reward_id
        ");

        $stmt->execute([
            ':reward_id' => $rewardId,
            ':status' => $status,
            ':verification_id' => $verificationId
        ]);
    }
}

