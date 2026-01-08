<?php

declare(strict_types=1);

namespace Ghidar\Lottery;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use PDO;
use PDOException;

/**
 * Service for handling wallet ownership verification for lottery prize claims.
 * Implements two verification methods:
 * 1. Signature-based (preferred): User signs a message with their wallet
 * 2. Manual verification: For users experiencing signing issues
 */
class WalletVerificationService
{
    /**
     * Generate a unique nonce for wallet verification message signing.
     *
     * @param int $userId User ID
     * @param int|null $rewardId Optional reward ID if verifying specific reward
     * @return string Nonce string
     */
    public static function generateVerificationNonce(int $userId, ?int $rewardId = null): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(16));
        $data = "{$userId}:{$timestamp}:{$random}";
        if ($rewardId !== null) {
            $data .= ":{$rewardId}";
        }
        return hash('sha256', $data);
    }

    /**
     * Get or create a verification request for a user's pending rewards.
     *
     * @param int $userId User ID
     * @param string $verificationMethod Verification method ('signature' or 'manual')
     * @param int|null $rewardId Optional specific reward ID to verify, null for all pending rewards
     * @return array<string, mixed> Verification request data
     * @throws PDOException If database operation fails
     * @throws \RuntimeException If no pending rewards found
     */
    public static function createVerificationRequest(
        int $userId,
        string $verificationMethod = 'signature',
        ?int $rewardId = null
    ): array {
        if (!in_array($verificationMethod, ['signature', 'manual'], true)) {
            throw new \InvalidArgumentException('Invalid verification method. Must be "signature" or "manual"');
        }

        $db = Database::ensureConnection();

        // Check if user has pending rewards
        $checkStmt = $db->prepare(
            'SELECT SUM(`reward_amount_usdt`) as total_pending 
             FROM `lottery_participation_rewards` 
             WHERE `user_id` = :user_id AND `status` = :status'
        );
        $checkStmt->execute([
            'user_id' => $userId,
            'status' => 'pending_verification'
        ]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $totalPending = $result['total_pending'] ?? '0';

        if (bccomp($totalPending, '0', 8) <= 0) {
            throw new \RuntimeException('No pending rewards found for verification');
        }

        $messageToSign = null;
        $nonce = null;

        if ($verificationMethod === 'signature') {
            $nonce = self::generateVerificationNonce($userId, $rewardId);
            $messageToSign = self::buildVerificationMessage($userId, $nonce);
        }

        try {
            $db->beginTransaction();

            // Check for existing pending verification request
            $checkExistingStmt = $db->prepare(
                'SELECT * FROM `wallet_verification_requests` 
                 WHERE `user_id` = :user_id 
                   AND `verification_status` IN (:pending, :processing)
                   AND (`reward_id` = :reward_id OR (:reward_id IS NULL AND `reward_id` IS NULL))
                 ORDER BY `created_at` DESC 
                 LIMIT 1'
            );
            $checkExistingStmt->execute([
                'user_id' => $userId,
                'pending' => 'pending',
                'processing' => 'processing',
                'reward_id' => $rewardId
            ]);
            $existing = $checkExistingStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing request
                $updateStmt = $db->prepare(
                    'UPDATE `wallet_verification_requests` 
                     SET `verification_method` = :method,
                         `message_to_sign` = :message,
                         `message_nonce` = :nonce,
                         `expires_at` = DATE_ADD(NOW(), INTERVAL 24 HOUR),
                         `updated_at` = NOW()
                     WHERE `id` = :id'
                );
                $updateStmt->execute([
                    'method' => $verificationMethod,
                    'message' => $messageToSign,
                    'nonce' => $nonce,
                    'id' => $existing['id']
                ]);

                $requestId = (int) $existing['id'];
            } else {
                // Create new request
                $insertStmt = $db->prepare(
                    'INSERT INTO `wallet_verification_requests` 
                     (`user_id`, `reward_id`, `verification_method`, `verification_status`, 
                      `message_to_sign`, `message_nonce`, `expires_at`) 
                     VALUES (:user_id, :reward_id, :method, :status, :message, :nonce, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
                );
                $insertStmt->execute([
                    'user_id' => $userId,
                    'reward_id' => $rewardId,
                    'method' => $verificationMethod,
                    'status' => 'pending',
                    'message' => $messageToSign,
                    'nonce' => $nonce
                ]);
                $requestId = (int) $db->lastInsertId();
            }

            $db->commit();

            // Get the created/updated request
            $stmt = $db->prepare('SELECT * FROM `wallet_verification_requests` WHERE `id` = :id LIMIT 1');
            $stmt->execute(['id' => $requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            Logger::event('verification_request_created', [
                'user_id' => $userId,
                'request_id' => $requestId,
                'method' => $verificationMethod,
                'reward_id' => $rewardId
            ]);

            return $request !== false ? $request : [];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Build the message that user needs to sign with their wallet.
     *
     * @param int $userId User ID
     * @param string $nonce Unique nonce
     * @return string Message to sign
     */
    private static function buildVerificationMessage(int $userId, string $nonce): string
    {
        $domain = 'ghidar.lottery';
        $timestamp = time();
        return "Ghidar Lottery Prize Verification\n\nUser ID: {$userId}\nNonce: {$nonce}\nTimestamp: {$timestamp}\nDomain: {$domain}\n\nBy signing this message, you verify ownership of the withdrawal wallet.";
    }

    /**
     * Submit signature for verification.
     *
     * @param int $userId User ID
     * @param string $signature ECDSA signature (hex encoded)
     * @param string $walletAddress Wallet address used for signing
     * @param string $walletNetwork Network (ERC20, BEP20, TRC20)
     * @param int|null $requestId Optional specific verification request ID
     * @return array<string, mixed> Verification result
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If signature validation fails
     */
    public static function submitSignature(
        int $userId,
        string $signature,
        string $walletAddress,
        string $walletNetwork,
        ?int $requestId = null
    ): array {
        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Get verification request
            $query = 'SELECT * FROM `wallet_verification_requests` 
                      WHERE `user_id` = :user_id 
                        AND `verification_method` = :method
                        AND `verification_status` = :status';
            $params = [
                'user_id' => $userId,
                'method' => 'signature',
                'status' => 'pending'
            ];

            if ($requestId !== null) {
                $query .= ' AND `id` = :request_id';
                $params['request_id'] = $requestId;
            }

            $query .= ' ORDER BY `created_at` DESC LIMIT 1';

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($request === false) {
                throw new \RuntimeException('No pending verification request found');
            }

            // Update request with signature
            $updateStmt = $db->prepare(
                'UPDATE `wallet_verification_requests` 
                 SET `signature` = :signature,
                     `wallet_address` = :address,
                     `wallet_network` = :network,
                     `verification_status` = :status,
                     `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $updateStmt->execute([
                'signature' => $signature,
                'address' => $walletAddress,
                'network' => $walletNetwork,
                'status' => 'processing',
                'id' => $request['id']
            ]);

            // TODO: In production, verify the signature on-chain or using a cryptographic library
            // For now, we'll mark it as approved after basic validation
            // In real implementation, you would:
            // 1. Recover the address from the signature
            // 2. Verify it matches the provided wallet address
            // 3. Verify the message contains the expected nonce

            // Simulate verification (replace with actual signature verification)
            $isValid = self::validateSignature($request['message_to_sign'], $signature, $walletAddress, $walletNetwork);

            if ($isValid) {
                // Approve and process rewards
                self::processVerifiedRewards($userId, (int) $request['id']);
                
                $status = 'approved';
                $message = 'Verification successful! Your rewards have been credited.';
            } else {
                // Reject
                $rejectStmt = $db->prepare(
                    'UPDATE `wallet_verification_requests` 
                     SET `verification_status` = :status,
                         `rejection_reason` = :reason,
                         `updated_at` = NOW()
                     WHERE `id` = :id'
                );
                $rejectStmt->execute([
                    'status' => 'rejected',
                    'reason' => 'Invalid signature or address mismatch',
                    'id' => $request['id']
                ]);

                $status = 'rejected';
                $message = 'Signature verification failed. Please try again.';
            }

            $db->commit();

            Logger::event('verification_signature_submitted', [
                'user_id' => $userId,
                'request_id' => $request['id'],
                'status' => $status
            ]);

            return [
                'success' => $isValid,
                'status' => $status,
                'message' => $message,
                'request_id' => (int) $request['id']
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Validate signature (placeholder - implement actual cryptographic validation).
     *
     * @param string $message Original message
     * @param string $signature ECDSA signature
     * @param string $address Expected wallet address
     * @param string $network Network identifier
     * @return bool True if signature is valid
     */
    private static function validateSignature(string $message, string $signature, string $address, string $network): bool
    {
        // TODO: Implement actual signature verification
        // This should use a library like web3-php or similar to:
        // 1. Recover address from signature using ecrecover (or equivalent for the network)
        // 2. Compare recovered address with provided address
        // 3. Verify message format and nonce
        
        // Placeholder: Basic validation
        if (empty($signature) || empty($address) || empty($message)) {
            return false;
        }

        // In production, replace with actual cryptographic verification
        // For now, accept any non-empty signature for development
        return strlen($signature) > 0 && strlen($address) > 0;
    }

    /**
     * Process and credit verified rewards to user's wallet.
     *
     * @param int $userId User ID
     * @param int $requestId Verification request ID
     * @return void
     * @throws PDOException If database operation fails
     */
    private static function processVerifiedRewards(int $userId, int $requestId): void
    {
        $db = Database::ensureConnection();

        // Get all pending rewards for this user
        $stmt = $db->prepare(
            'SELECT * FROM `lottery_participation_rewards` 
             WHERE `user_id` = :user_id AND `status` = :status'
        );
        $stmt->execute([
            'user_id' => $userId,
            'status' => 'pending_verification'
        ]);
        $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rewards)) {
            return;
        }

        // Calculate total amount to credit
        $totalAmount = '0';
        foreach ($rewards as $reward) {
            $totalAmount = bcadd($totalAmount, (string) $reward['reward_amount_usdt'], 8);
        }

        // Update wallet: move from pending_verification_balance to usdt_balance
        $walletStmt = $db->prepare(
            'UPDATE `wallets` 
             SET `pending_verification_balance` = `pending_verification_balance` - :amount,
                 `usdt_balance` = `usdt_balance` + :amount
             WHERE `user_id` = :user_id 
               AND `pending_verification_balance` >= :amount'
        );
        $walletStmt->execute([
            'amount' => $totalAmount,
            'user_id' => $userId
        ]);

        // Mark rewards as verified and claimed
        $rewardIds = array_column($rewards, 'id');
        $placeholders = implode(',', array_fill(0, count($rewardIds), '?'));
        $updateRewardsStmt = $db->prepare(
            "UPDATE `lottery_participation_rewards` 
             SET `status` = 'claimed',
                 `verified_at` = NOW(),
                 `claimed_at` = NOW()
             WHERE `id` IN ({$placeholders})"
        );
        $updateRewardsStmt->execute($rewardIds);

        // Update verification request
        $updateRequestStmt = $db->prepare(
            'UPDATE `wallet_verification_requests` 
             SET `verification_status` = :status,
                 `updated_at` = NOW()
             WHERE `id` = :id'
        );
        $updateRequestStmt->execute([
            'status' => 'approved',
            'id' => $requestId
        ]);

        Logger::event('rewards_verified_and_claimed', [
            'user_id' => $userId,
            'request_id' => $requestId,
            'reward_count' => count($rewards),
            'total_amount_usdt' => $totalAmount
        ]);
    }

    /**
     * Get user's pending rewards summary.
     * 
     * OPTIMIZED: Combines queries where possible and adds graceful error handling
     * for missing tables/columns.
     *
     * @param int $userId User ID
     * @return array<string, mixed> Pending rewards information
     * @throws PDOException If database operation fails (only for critical errors)
     */
    public static function getPendingRewards(int $userId): array
    {
        $db = Database::ensureConnection();
        
        // Default response for graceful degradation
        $defaultResponse = [
            'pending_balance_usdt' => '0',
            'rewards' => [],
            'active_verification_request' => null,
            'can_claim' => false
        ];

        try {
            // Try to get wallet pending balance - with graceful handling for missing column
            $pendingBalance = '0';
            try {
                $walletStmt = $db->prepare(
                    'SELECT `pending_verification_balance` 
                     FROM `wallets` 
                     WHERE `user_id` = :user_id 
                     LIMIT 1'
                );
                $walletStmt->execute(['user_id' => $userId]);
                $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
                $pendingBalance = $wallet ? (string) ($wallet['pending_verification_balance'] ?? '0') : '0';
            } catch (\PDOException $e) {
                // If column doesn't exist, continue with default value
                if (strpos($e->getMessage(), 'Unknown column') !== false) {
                    Logger::warning('pending_verification_balance_column_missing', [
                        'user_id' => $userId
                    ]);
                    $pendingBalance = '0';
                } else {
                    throw $e;
                }
            }

            // Get reward details with lottery info in single query
            $rewards = [];
            try {
                $stmt = $db->prepare(
                    'SELECT 
                        lpr.`id`,
                        lpr.`lottery_id`,
                        lpr.`reward_type`,
                        lpr.`reward_amount_usdt`,
                        lpr.`ticket_count`,
                        lpr.`status`,
                        lpr.`created_at`,
                        l.`title` as lottery_title
                     FROM `lottery_participation_rewards` lpr
                     LEFT JOIN `lotteries` l ON lpr.`lottery_id` = l.`id`
                     WHERE lpr.`user_id` = :user_id AND lpr.`status` = :status
                     ORDER BY lpr.`created_at` DESC
                     LIMIT 50'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'status' => 'pending_verification'
                ]);
                $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                // If table doesn't exist, continue with empty rewards
                if (strpos($e->getMessage(), "doesn't exist") !== false || 
                    strpos($e->getMessage(), 'no such table') !== false) {
                    Logger::warning('lottery_participation_rewards_table_missing', [
                        'user_id' => $userId
                    ]);
                    $rewards = [];
                } else {
                    throw $e;
                }
            }

            // Get active verification request if any
            $activeRequest = null;
            try {
                $requestStmt = $db->prepare(
                    'SELECT * FROM `wallet_verification_requests` 
                     WHERE `user_id` = :user_id 
                       AND `verification_status` IN (:pending, :processing)
                     ORDER BY `created_at` DESC 
                     LIMIT 1'
                );
                $requestStmt->execute([
                    'user_id' => $userId,
                    'pending' => 'pending',
                    'processing' => 'processing'
                ]);
                $result = $requestStmt->fetch(PDO::FETCH_ASSOC);
                $activeRequest = $result !== false ? $result : null;
            } catch (\PDOException $e) {
                // If table doesn't exist, continue without active request
                if (strpos($e->getMessage(), "doesn't exist") !== false || 
                    strpos($e->getMessage(), 'no such table') !== false) {
                    Logger::warning('wallet_verification_requests_table_missing', [
                        'user_id' => $userId
                    ]);
                    $activeRequest = null;
                } else {
                    throw $e;
                }
            }

            return [
                'pending_balance_usdt' => $pendingBalance,
                'rewards' => $rewards,
                'active_verification_request' => $activeRequest,
                'can_claim' => bccomp($pendingBalance, '0', 8) > 0
            ];
            
        } catch (\PDOException $e) {
            // For any other database errors, log and return default response
            Logger::error('get_pending_rewards_error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return $defaultResponse;
        }
    }
}

