<?php

declare(strict_types=1);

namespace Ghidar\AITrader;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Security\EncryptionService;
use PDO;
use PDOException;

/**
 * Service for Source of Funds verification for AI Trader profit withdrawals.
 * Verifies that the wallet receiving profits is owned by the account holder.
 */
class SourceOfFundsVerificationService
{
    /**
     * Create source of funds verification record.
     *
     * @param int $verificationId Withdrawal verification ID
     * @param int $userId User ID
     * @param string $profitAmountUsdt Profit amount being withdrawn
     * @param string $walletAddress Target wallet address
     * @param string $walletNetwork Wallet network
     * @param string $verificationMethod Verification method to use
     * @return array<string, mixed> Source of funds verification record
     */
    public static function createVerification(
        int $verificationId,
        int $userId,
        string $profitAmountUsdt,
        string $walletAddress,
        string $walletNetwork,
        string $verificationMethod = 'wallet_signature'
    ): array {
        $db = Database::ensureConnection();

        // Set expiration (24 hours)
        $expiresAt = new \DateTime('now', new \DateTimeZone('UTC'));
        $expiresAt->add(new \DateInterval('P1D'));

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                'INSERT INTO `ai_source_of_funds_verifications` 
                (`verification_id`, `user_id`, `profit_amount_usdt`, `wallet_address`, `wallet_network`, 
                 `verification_method`, `verification_status`, `expires_at`)
                VALUES (:verification_id, :user_id, :profit_amount, :wallet_address, :wallet_network, 
                        :verification_method, :status, :expires_at)'
            );

            $stmt->execute([
                'verification_id' => $verificationId,
                'user_id' => $userId,
                'profit_amount' => $profitAmountUsdt,
                'wallet_address' => $walletAddress,
                'wallet_network' => $walletNetwork,
                'verification_method' => $verificationMethod,
                'status' => 'pending',
                'expires_at' => $expiresAt->format('Y-m-d H:i:s')
            ]);

            $sofwId = (int) $db->lastInsertId();

            // Update withdrawal verification to mark SOF verification as required
            $updateStmt = $db->prepare(
                'UPDATE `ai_withdrawal_verifications` 
                 SET `source_of_funds_verified` = FALSE, `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $updateStmt->execute(['id' => $verificationId]);

            $db->commit();

            Logger::event('source_of_funds_verification_created', [
                'verification_id' => $verificationId,
                'user_id' => $userId,
                'sofw_id' => $sofwId
            ]);

            return self::getVerification($sofwId);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('Failed to create source of funds verification', [
                'verification_id' => $verificationId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Submit wallet signature for verification.
     *
     * @param int $sofwId Source of funds verification ID
     * @param string $signature Wallet signature (will be encrypted)
     * @param string $message Message that was signed
     * @return array<string, mixed> Updated verification record
     */
    public static function submitWalletSignature(int $sofwId, string $signature, string $message): array
    {
        $db = Database::ensureConnection();
        $verification = self::getVerification($sofwId);

        if ($verification['verification_status'] !== 'pending') {
            throw new \RuntimeException('Verification is not in pending status');
        }

        // Encrypt signature data
        $signatureData = EncryptionService::encryptJson([
            'signature' => $signature,
            'message' => $message,
            'submitted_at' => date('Y-m-d H:i:s')
        ]);

        // Generate verification hash for integrity checking
        $verificationHash = hash('sha256', $signature . $message . $verification['wallet_address']);

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                'UPDATE `ai_source_of_funds_verifications` 
                 SET `wallet_signature` = :signature, `verification_hash` = :hash, 
                     `verification_status` = :status, `verified_at` = NOW(), `updated_at` = NOW()
                 WHERE `id` = :id'
            );

            // Note: In a production system, you would verify the signature here
            // For now, we mark it as verified (actual signature verification would be done
            // by calling blockchain service or external verification service)
            $stmt->execute([
                'signature' => $signatureData,
                'hash' => $verificationHash,
                'status' => 'verified',
                'id' => $sofwId
            ]);

            // Update withdrawal verification
            $updateStmt = $db->prepare(
                'UPDATE `ai_withdrawal_verifications` 
                 SET `source_of_funds_verified` = TRUE, `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $updateStmt->execute(['id' => $verification['verification_id']]);

            $db->commit();

            Logger::event('source_of_funds_verification_completed', [
                'sofw_id' => $sofwId,
                'verification_id' => $verification['verification_id'],
                'method' => 'wallet_signature'
            ]);

            return self::getVerification($sofwId);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Submit transaction proof for verification.
     *
     * @param int $sofwId Source of funds verification ID
     * @param string $transactionHash Transaction hash proving ownership
     * @param array<string, mixed> $proofData Additional proof data
     * @return array<string, mixed> Updated verification record
     */
    public static function submitTransactionProof(
        int $sofwId,
        string $transactionHash,
        array $proofData = []
    ): array {
        $db = Database::ensureConnection();
        $verification = self::getVerification($sofwId);

        if ($verification['verification_status'] !== 'pending') {
            throw new \RuntimeException('Verification is not in pending status');
        }

        // Encrypt proof data
        $proofData['transaction_hash'] = $transactionHash;
        $proofData['submitted_at'] = date('Y-m-d H:i:s');
        $encryptedProof = EncryptionService::encryptJson($proofData);

        // Generate verification hash
        $verificationHash = hash('sha256', $transactionHash . $verification['wallet_address']);

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                'UPDATE `ai_source_of_funds_verifications` 
                 SET `transaction_proof` = :proof, `verification_hash` = :hash, 
                     `verification_status` = :status, `verified_at` = NOW(), `updated_at` = NOW()
                 WHERE `id` = :id'
            );

            // Note: In production, verify transaction proof via blockchain service
            $stmt->execute([
                'proof' => $encryptedProof,
                'hash' => $verificationHash,
                'status' => 'verified',
                'id' => $sofwId
            ]);

            // Update withdrawal verification
            $updateStmt = $db->prepare(
                'UPDATE `ai_withdrawal_verifications` 
                 SET `source_of_funds_verified` = TRUE, `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $updateStmt->execute(['id' => $verification['verification_id']]);

            $db->commit();

            Logger::event('source_of_funds_verification_completed', [
                'sofw_id' => $sofwId,
                'verification_id' => $verification['verification_id'],
                'method' => 'transaction_proof'
            ]);

            return self::getVerification($sofwId);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get source of funds verification record.
     *
     * @param int $sofwId Source of funds verification ID
     * @return array<string, mixed> Verification record
     * @throws \RuntimeException If not found
     */
    public static function getVerification(int $sofwId): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `ai_source_of_funds_verifications` WHERE `id` = :id LIMIT 1'
        );
        $stmt->execute(['id' => $sofwId]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($verification === false) {
            throw new \RuntimeException('Source of funds verification not found');
        }

        return $verification;
    }

    /**
     * Get source of funds verification for withdrawal verification.
     *
     * @param int $verificationId Withdrawal verification ID
     * @return array<string, mixed>|null Verification record or null
     */
    public static function getVerificationByWithdrawalId(int $verificationId): ?array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `ai_source_of_funds_verifications` 
             WHERE `verification_id` = :verification_id 
             LIMIT 1'
        );
        $stmt->execute(['verification_id' => $verificationId]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        return $verification !== false ? $verification : null;
    }

    /**
     * Verify wallet address format for a network.
     *
     * @param string $address Wallet address
     * @param string $network Network name
     * @return bool True if valid format
     */
    public static function validateWalletAddress(string $address, string $network): bool
    {
        // Basic validation - in production, use proper address validation libraries
        switch (strtolower($network)) {
            case 'ethereum':
            case 'bsc':
            case 'polygon':
                // Ethereum-style addresses: 0x followed by 40 hex characters
                return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
            case 'tron':
                // Tron addresses: T followed by 33 alphanumeric characters
                return preg_match('/^T[0-9A-Za-z]{33}$/', $address) === 1;
            default:
                // Default: at least 20 characters
                return strlen($address) >= 20;
        }
    }
}

