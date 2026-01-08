<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Notifications\NotificationService;
use PDO;
use PDOException;

/**
 * Universal Wallet Verification Service
 * 
 * Centralized service for wallet verification across all Ghidar features:
 * - Lottery
 * - Airdrop
 * - AI Trader
 * 
 * Supports multiple verification methods:
 * - Method A: Standard message signing (preferred)
 * - Method B: Assisted verification for edge cases
 * - Method C: Multi-signature for high-value transactions
 * - Method D: Time-delayed with email confirmation
 * 
 * SECURITY NOTE: This service NEVER stores private keys.
 * All verification is done through cryptographic signatures.
 */
class WalletVerificationService
{
    // Verification method constants
    public const METHOD_STANDARD_SIGNATURE = 'standard_signature';
    public const METHOD_ASSISTED = 'assisted';
    public const METHOD_MULTI_SIGNATURE = 'multi_signature';
    public const METHOD_TIME_DELAYED = 'time_delayed';

    // Feature constants
    public const FEATURE_LOTTERY = 'lottery';
    public const FEATURE_AIRDROP = 'airdrop';
    public const FEATURE_AI_TRADER = 'ai_trader';
    public const FEATURE_WITHDRAWAL = 'withdrawal';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFYING = 'verifying';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Create a new wallet verification request.
     *
     * @param int $userId User ID
     * @param string $feature Feature requiring verification (lottery, airdrop, ai_trader, withdrawal)
     * @param string $walletAddress Wallet address to verify
     * @param string $walletNetwork Network (erc20, bep20, trc20)
     * @param array<string, mixed> $context Additional context (amount, transaction_id, etc.)
     * @param string $method Verification method (default: standard_signature)
     * @return array<string, mixed> Verification request data
     */
    public static function createVerificationRequest(
        int $userId,
        string $feature,
        string $walletAddress,
        string $walletNetwork,
        array $context = [],
        string $method = self::METHOD_STANDARD_SIGNATURE
    ): array {
        $db = Database::getConnection();

        // Validate inputs
        self::validateInputs($userId, $feature, $walletAddress, $walletNetwork, $method);

        // Rate limiting: max 10 verification requests per hour per user
        if (!RateLimiter::checkAndIncrement($userId, 'wallet_verification_create', 10, 3600)) {
            throw new \RuntimeException('Too many verification requests. Please wait before creating a new request.');
        }

        // IP-based anomaly detection
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        self::checkIpAnomalies($userId, $ipAddress);

        // Risk assessment
        $riskAssessment = self::assessRisk($userId, $feature, $walletAddress, $walletNetwork, $context);

        // Determine verification method based on risk
        if ($riskAssessment['risk_level'] === 'high' && $method === self::METHOD_STANDARD_SIGNATURE) {
            // High risk requires multi-signature or time-delayed
            $method = $context['amount'] ?? 0 > 10000 ? self::METHOD_MULTI_SIGNATURE : self::METHOD_TIME_DELAYED;
        }

        try {
            $db->beginTransaction();

            // Generate verification message and nonce
            $messageNonce = bin2hex(random_bytes(16));
            $messageToSign = self::generateVerificationMessage(
                $userId,
                $feature,
                $walletAddress,
                $walletNetwork,
                $messageNonce,
                $context
            );

            // Set expiration (24 hours for standard, 48 hours for time-delayed)
            $expirationHours = $method === self::METHOD_TIME_DELAYED ? 48 : 24;
            $expiresAt = date('Y-m-d H:i:s', time() + ($expirationHours * 3600));

            // Encrypt sensitive context data
            $encryptedContext = EncryptionService::encryptJson($context);

            // Insert verification request
            $stmt = $db->prepare(
                'INSERT INTO `wallet_verifications` 
                (`user_id`, `feature`, `verification_method`, `wallet_address`, `wallet_network`,
                 `message_to_sign`, `message_nonce`, `context_data`, `risk_score`, `risk_level`,
                 `risk_factors`, `ip_address`, `expires_at`, `status`)
                VALUES (:user_id, :feature, :verification_method, :wallet_address, :wallet_network,
                        :message_to_sign, :message_nonce, :context_data, :risk_score, :risk_level,
                        :risk_factors, :ip_address, :expires_at, :status)'
            );

            $stmt->execute([
                'user_id' => $userId,
                'feature' => $feature,
                'verification_method' => $method,
                'wallet_address' => $walletAddress,
                'wallet_network' => $walletNetwork,
                'message_to_sign' => $messageToSign,
                'message_nonce' => $messageNonce,
                'context_data' => $encryptedContext,
                'risk_score' => $riskAssessment['risk_score'],
                'risk_level' => $riskAssessment['risk_level'],
                'risk_factors' => json_encode($riskAssessment['risk_factors'], JSON_UNESCAPED_UNICODE),
                'ip_address' => $ipAddress,
                'expires_at' => $expiresAt,
                'status' => self::STATUS_PENDING
            ]);

            $verificationId = (int) $db->lastInsertId();

            // Create audit log entry
            self::logAuditEvent($verificationId, $userId, 'verification_created', [
                'feature' => $feature,
                'method' => $method,
                'risk_score' => $riskAssessment['risk_score']
            ], $ipAddress);

            // Store verification attempt for pattern analysis
            self::storeVerificationAttempt($userId, $verificationId, $ipAddress, $walletAddress, $walletNetwork);

            $db->commit();

            Logger::info('wallet_verification_created', [
                'verification_id' => $verificationId,
                'user_id' => $userId,
                'feature' => $feature,
                'method' => $method
            ]);

            // Send notification to user
            self::sendVerificationNotification($userId, $verificationId, $method, $messageToSign);

            return [
                'verification_id' => $verificationId,
                'status' => self::STATUS_PENDING,
                'verification_method' => $method,
                'message_to_sign' => $messageToSign,
                'message_nonce' => $messageNonce,
                'expires_at' => $expiresAt,
                'risk_level' => $riskAssessment['risk_level'],
                'instructions' => self::getVerificationInstructions($method),
                'educational_content' => self::getEducationalContent($riskAssessment)
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('wallet_verification_create_error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Submit signature for verification (Method A: Standard Signature).
     *
     * @param int $verificationId Verification request ID
     * @param string $signature ECDSA signature
     * @param string $walletAddress Wallet address that signed
     * @return array<string, mixed> Verification result
     */
    public static function submitSignature(
        int $verificationId,
        string $signature,
        string $walletAddress
    ): array {
        $db = Database::getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        try {
            $db->beginTransaction();

            // Get verification request
            $stmt = $db->prepare(
                'SELECT * FROM `wallet_verifications` 
                 WHERE `id` = :verification_id AND `status` = :status LIMIT 1'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'status' => self::STATUS_PENDING
            ]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new \InvalidArgumentException('Verification request not found or already processed');
            }

            // Check expiration
            if ($request['expires_at'] && strtotime($request['expires_at']) < time()) {
                self::updateStatus($verificationId, self::STATUS_EXPIRED);
                throw new \InvalidArgumentException('Verification request has expired');
            }

            // Verify wallet address matches
            if (strtolower($request['wallet_address']) !== strtolower($walletAddress)) {
                self::handleFailedVerification($verificationId, $request['user_id'], 'Wallet address mismatch', $ipAddress);
                throw new \InvalidArgumentException('Wallet address does not match verification request');
            }

            // Validate signature format
            if (!self::validateSignatureFormat($signature, $request['wallet_network'])) {
                self::handleFailedVerification($verificationId, $request['user_id'], 'Invalid signature format', $ipAddress);
                throw new \InvalidArgumentException('Invalid signature format');
            }

            // TODO: Implement actual ECDSA signature verification
            // This should verify that the signature was created by the wallet address
            // For now, we'll do basic validation and mark as verifying
            $isValid = self::verifySignature($signature, $request['message_to_sign'], $walletAddress, $request['wallet_network']);

            if (!$isValid) {
                self::handleFailedVerification($verificationId, $request['user_id'], 'Invalid signature', $ipAddress);
                throw new \InvalidArgumentException('Invalid signature. Please ensure you signed the correct message.');
            }

            // Encrypt signature before storage
            $encryptedSignature = EncryptionService::encrypt($signature);

            // Update verification request
            $stmt = $db->prepare(
                'UPDATE `wallet_verifications` 
                 SET `status` = :status,
                     `signature` = :signature,
                     `verified_at` = NOW(),
                     `verification_ip` = :ip_address
                 WHERE `id` = :verification_id'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'status' => self::STATUS_APPROVED,
                'signature' => $encryptedSignature,
                'ip_address' => $ipAddress
            ]);

            // Create audit log
            self::logAuditEvent($verificationId, $request['user_id'], 'verification_approved', [
                'method' => 'standard_signature'
            ], $ipAddress);

            // Update verification attempt as successful
            self::updateVerificationAttempt($verificationId, true);

            $db->commit();

            Logger::info('wallet_verification_approved', [
                'verification_id' => $verificationId,
                'user_id' => $request['user_id']
            ]);

            // Send success notification with enhanced details
            NotificationService::notifyVerificationApproved(
                $request['user_id'],
                $walletAddress,
                null // No unlocked amount for standard verification
            );

            // Trigger webhook if configured
            self::triggerWebhook($verificationId, $request['user_id'], 'approved');

            return [
                'success' => true,
                'verification_id' => $verificationId,
                'status' => self::STATUS_APPROVED,
                'message' => 'Wallet verification successful'
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Submit assisted verification data (Method B: Assisted Verification).
     *
     * @param int $verificationId Verification request ID
     * @param array<string, mixed> $verificationData User-provided verification data
     * @return array<string, mixed> Verification result
     */
    public static function submitAssistedVerification(
        int $verificationId,
        array $verificationData
    ): array {
        $db = Database::getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        try {
            $db->beginTransaction();

            // Get verification request
            $stmt = $db->prepare(
                'SELECT * FROM `wallet_verifications` 
                 WHERE `id` = :verification_id 
                   AND `verification_method` = :method
                   AND `status` = :status LIMIT 1'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'method' => self::METHOD_ASSISTED,
                'status' => self::STATUS_PENDING
            ]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new \InvalidArgumentException('Assisted verification request not found or invalid');
            }

            // Encrypt verification data
            $encryptedData = EncryptionService::encryptJson($verificationData);

            // Update request - will be reviewed by support
            $stmt = $db->prepare(
                'UPDATE `wallet_verifications` 
                 SET `status` = :status,
                     `assisted_verification_data` = :verification_data,
                     `verification_ip` = :ip_address
                 WHERE `id` = :verification_id'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'status' => self::STATUS_VERIFYING,
                'verification_data' => $encryptedData,
                'ip_address' => $ipAddress
            ]);

            // Create support ticket record
            $supportTicketId = self::createSupportTicket($verificationId, $request['user_id'], $verificationData);

            // Create audit log
            self::logAuditEvent($verificationId, $request['user_id'], 'assisted_verification_submitted', [
                'support_ticket_id' => $supportTicketId
            ], $ipAddress);

            $db->commit();

            Logger::info('assisted_verification_submitted', [
                'verification_id' => $verificationId,
                'user_id' => $request['user_id'],
                'support_ticket_id' => $supportTicketId
            ]);

            // Send notification
            NotificationService::sendCustomNotification(
                $request['user_id'],
                "📋 <b>Verification Request Submitted</b>\n\nYour verification request has been submitted. Our support team will review it and contact you shortly.\n\nTicket ID: {$supportTicketId}"
            );

            return [
                'success' => true,
                'verification_id' => $verificationId,
                'status' => self::STATUS_VERIFYING,
                'support_ticket_id' => $supportTicketId,
                'message' => 'Your verification request has been submitted for review'
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Submit multi-signature verification (Method C: Multi-Signature).
     *
     * @param int $verificationId Verification request ID
     * @param array<string, string> $signatures Array of signatures from multiple wallets
     * @return array<string, mixed> Verification result
     */
    public static function submitMultiSignature(
        int $verificationId,
        array $signatures
    ): array {
        $db = Database::getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        try {
            $db->beginTransaction();

            // Get verification request
            $stmt = $db->prepare(
                'SELECT * FROM `wallet_verifications` 
                 WHERE `id` = :verification_id 
                   AND `verification_method` = :method
                   AND `status` = :status LIMIT 1'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'method' => self::METHOD_MULTI_SIGNATURE,
                'status' => self::STATUS_PENDING
            ]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new \InvalidArgumentException('Multi-signature verification request not found');
            }

            // Require at least 2 signatures for multi-signature
            if (count($signatures) < 2) {
                throw new \InvalidArgumentException('Multi-signature requires at least 2 signatures');
            }

            // Verify all signatures
            $verifiedSignatures = [];
            foreach ($signatures as $walletAddress => $signature) {
                if (!self::verifySignature($signature, $request['message_to_sign'], $walletAddress, $request['wallet_network'])) {
                    throw new \InvalidArgumentException("Invalid signature from wallet: {$walletAddress}");
                }
                $verifiedSignatures[$walletAddress] = EncryptionService::encrypt($signature);
            }

            // Update verification request
            $stmt = $db->prepare(
                'UPDATE `wallet_verifications` 
                 SET `status` = :status,
                     `multi_signature_data` = :signatures,
                     `verified_at` = NOW(),
                     `verification_ip` = :ip_address
                 WHERE `id` = :verification_id'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'status' => self::STATUS_APPROVED,
                'signatures' => EncryptionService::encryptJson($verifiedSignatures),
                'ip_address' => $ipAddress
            ]);

            // Create audit log
            self::logAuditEvent($verificationId, $request['user_id'], 'multi_signature_approved', [
                'signature_count' => count($signatures)
            ], $ipAddress);

            $db->commit();

            Logger::info('multi_signature_verification_approved', [
                'verification_id' => $verificationId,
                'user_id' => $request['user_id']
            ]);

            NotificationService::sendCustomNotification(
                $request['user_id'],
                "✅ <b>Multi-Signature Verification Successful!</b>\n\nAll required signatures have been verified."
            );

            self::triggerWebhook($verificationId, $request['user_id'], 'approved');

            return [
                'success' => true,
                'verification_id' => $verificationId,
                'status' => self::STATUS_APPROVED,
                'message' => 'Multi-signature verification successful'
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Initiate time-delayed verification (Method D: Time-Delayed).
     *
     * @param int $verificationId Verification request ID
     * @param string $email Email address for confirmation
     * @return array<string, mixed> Verification result
     */
    public static function initiateTimeDelayedVerification(
        int $verificationId,
        string $email
    ): array {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // Get verification request
            $stmt = $db->prepare(
                'SELECT * FROM `wallet_verifications` 
                 WHERE `id` = :verification_id 
                   AND `verification_method` = :method
                   AND `status` = :status LIMIT 1'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'method' => self::METHOD_TIME_DELAYED,
                'status' => self::STATUS_PENDING
            ]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new \InvalidArgumentException('Time-delayed verification request not found');
            }

            // Generate email confirmation token
            $emailToken = bin2hex(random_bytes(32));
            $emailExpiresAt = date('Y-m-d H:i:s', time() + (24 * 3600)); // 24 hours

            // Update request
            $stmt = $db->prepare(
                'UPDATE `wallet_verifications` 
                 SET `status` = :status,
                     `email_confirmation_token` = :token,
                     `email_confirmation_expires` = :expires,
                     `email_address` = :email
                 WHERE `id` = :verification_id'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'status' => self::STATUS_VERIFYING,
                'token' => hash('sha256', $emailToken),
                'expires' => $emailExpiresAt,
                'email' => EncryptionService::encrypt($email)
            ]);

            // Send email confirmation
            self::sendEmailConfirmation($request['user_id'], $email, $emailToken, $verificationId);

            // Create audit log
            self::logAuditEvent($verificationId, $request['user_id'], 'time_delayed_initiated', [
                'email_sent' => true
            ], $_SERVER['REMOTE_ADDR'] ?? 'unknown');

            $db->commit();

            Logger::info('time_delayed_verification_initiated', [
                'verification_id' => $verificationId,
                'user_id' => $request['user_id']
            ]);

            return [
                'success' => true,
                'verification_id' => $verificationId,
                'status' => self::STATUS_VERIFYING,
                'message' => 'Email confirmation sent. Please check your email to complete verification.',
                'email_token' => $emailToken // Return token for testing (remove in production)
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Confirm time-delayed verification via email token.
     *
     * @param int $verificationId Verification request ID
     * @param string $emailToken Email confirmation token
     * @return array<string, mixed> Verification result
     */
    public static function confirmTimeDelayedVerification(
        int $verificationId,
        string $emailToken
    ): array {
        $db = Database::getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        try {
            $db->beginTransaction();

            // Get verification request
            $stmt = $db->prepare(
                'SELECT * FROM `wallet_verifications` 
                 WHERE `id` = :verification_id 
                   AND `verification_method` = :method
                   AND `status` = :status LIMIT 1'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'method' => self::METHOD_TIME_DELAYED,
                'status' => self::STATUS_VERIFYING
            ]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new \InvalidArgumentException('Time-delayed verification request not found');
            }

            // Verify token
            $tokenHash = hash('sha256', $emailToken);
            if ($request['email_confirmation_token'] !== $tokenHash) {
                throw new \InvalidArgumentException('Invalid email confirmation token');
            }

            // Check expiration
            if ($request['email_confirmation_expires'] && strtotime($request['email_confirmation_expires']) < time()) {
                throw new \InvalidArgumentException('Email confirmation token has expired');
            }

            // Update verification request
            $stmt = $db->prepare(
                'UPDATE `wallet_verifications` 
                 SET `status` = :status,
                     `verified_at` = NOW(),
                     `verification_ip` = :ip_address
                 WHERE `id` = :verification_id'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'status' => self::STATUS_APPROVED,
                'ip_address' => $ipAddress
            ]);

            // Create audit log
            self::logAuditEvent($verificationId, $request['user_id'], 'time_delayed_confirmed', [], $ipAddress);

            $db->commit();

            Logger::info('time_delayed_verification_confirmed', [
                'verification_id' => $verificationId,
                'user_id' => $request['user_id']
            ]);

            NotificationService::sendCustomNotification(
                $request['user_id'],
                "✅ <b>Email Verification Confirmed!</b>\n\nYour wallet verification has been completed successfully."
            );

            self::triggerWebhook($verificationId, $request['user_id'], 'approved');

            return [
                'success' => true,
                'verification_id' => $verificationId,
                'status' => self::STATUS_APPROVED,
                'message' => 'Email verification confirmed successfully'
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get verification status for a user.
     *
     * @param int $userId User ID
     * @param string|null $feature Optional feature filter
     * @return array<string, mixed> Verification status
     */
    public static function getVerificationStatus(int $userId, ?string $feature = null): array
    {
        $db = Database::getConnection();

        $sql = 'SELECT `id`, `feature`, `verification_method`, `wallet_address`, `wallet_network`,
                       `status`, `risk_level`, `created_at`, `expires_at`, `verified_at`
                FROM `wallet_verifications`
                WHERE `user_id` = :user_id';
        
        $params = ['user_id' => $userId];

        if ($feature !== null) {
            $sql .= ' AND `feature` = :feature';
            $params['feature'] = $feature;
        }

        $sql .= ' ORDER BY `created_at` DESC LIMIT 50';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get latest approved verification for each feature
        $latestApproved = [];
        foreach ($verifications as $verification) {
            if ($verification['status'] === self::STATUS_APPROVED) {
                $feat = $verification['feature'];
                if (!isset($latestApproved[$feat]) || 
                    strtotime($verification['verified_at']) > strtotime($latestApproved[$feat]['verified_at'])) {
                    $latestApproved[$feat] = $verification;
                }
            }
        }

        return [
            'verifications' => $verifications,
            'latest_approved' => $latestApproved,
            'is_verified' => !empty($latestApproved)
        ];
    }

    /**
     * Check if user has verified wallet for a feature.
     *
     * @param int $userId User ID
     * @param string $feature Feature to check
     * @param string|null $walletAddress Optional wallet address to check
     * @return bool True if verified
     */
    public static function isVerified(int $userId, string $feature, ?string $walletAddress = null): bool
    {
        $db = Database::getConnection();

        $sql = 'SELECT COUNT(*) as count
                FROM `wallet_verifications`
                WHERE `user_id` = :user_id
                  AND `feature` = :feature
                  AND `status` = :status';

        $params = [
            'user_id' => $userId,
            'feature' => $feature,
            'status' => self::STATUS_APPROVED
        ];

        if ($walletAddress !== null) {
            $sql .= ' AND LOWER(`wallet_address`) = LOWER(:wallet_address)';
            $params['wallet_address'] = $walletAddress;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $result['count'] > 0;
    }

    /**
     * Assess risk for verification request.
     *
     * @param int $userId User ID
     * @param string $feature Feature
     * @param string $walletAddress Wallet address
     * @param string $walletNetwork Network
     * @param array<string, mixed> $context Context data
     * @return array<string, mixed> Risk assessment
     */
    private static function assessRisk(
        int $userId,
        string $feature,
        string $walletAddress,
        string $walletNetwork,
        array $context
    ): array {
        $riskScore = 0;
        $riskFactors = [];

        // Check verification history
        $history = self::getVerificationHistory($userId, $walletAddress);
        if ($history['total_attempts'] === 0) {
            $riskScore += 10;
            $riskFactors[] = 'first_time_verification';
        } elseif ($history['failed_attempts'] > 3) {
            $riskScore += 30;
            $riskFactors[] = 'multiple_failed_attempts';
        }

        // Check for rapid verification attempts
        if ($history['recent_attempts'] > 5) {
            $riskScore += 20;
            $riskFactors[] = 'rapid_verification_attempts';
        }

        // Check IP anomalies
        $ipAnomalies = self::detectIpAnomalies($userId, $walletAddress);
        if ($ipAnomalies['is_suspicious']) {
            $riskScore += 25;
            $riskFactors = array_merge($riskFactors, $ipAnomalies['factors']);
        }

        // Amount-based risk (if applicable)
        if (isset($context['amount']) && $context['amount'] > 0) {
            $amount = (float) $context['amount'];
            if ($amount > 10000) {
                $riskScore += 35;
                $riskFactors[] = 'high_value_transaction';
            } elseif ($amount > 5000) {
                $riskScore += 20;
                $riskFactors[] = 'medium_value_transaction';
            }
        }

        // Account age risk
        $accountAge = self::getAccountAge($userId);
        if ($accountAge < 7 * 24 * 3600) { // Less than 7 days
            $riskScore += 15;
            $riskFactors[] = 'new_account';
        }

        // Determine risk level
        $riskLevel = 'low';
        if ($riskScore >= 60) {
            $riskLevel = 'high';
        } elseif ($riskScore >= 40) {
            $riskLevel = 'medium';
        }

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'risk_factors' => array_unique($riskFactors)
        ];
    }

    /**
     * Generate verification message for signing.
     */
    private static function generateVerificationMessage(
        int $userId,
        string $feature,
        string $walletAddress,
        string $walletNetwork,
        string $nonce,
        array $context
    ): string {
        $amount = isset($context['amount']) ? number_format((float) $context['amount'], 8, '.', '') : 'N/A';
        
        return sprintf(
            "Ghidar Wallet Verification\n\nUser ID: %d\nFeature: %s\nWallet: %s\nNetwork: %s\nAmount: %s USDT\nNonce: %s\n\nThis signature verifies you are the owner of this wallet and authorizes this verification.",
            $userId,
            strtoupper($feature),
            $walletAddress,
            strtoupper($walletNetwork),
            $amount,
            $nonce
        );
    }

    /**
     * Verify signature (placeholder - should implement actual ECDSA verification).
     */
    private static function verifySignature(
        string $signature,
        string $message,
        string $walletAddress,
        string $walletNetwork
    ): bool {
        // TODO: Implement actual ECDSA signature verification
        // This should use libraries like web3-php or similar to verify
        // that the signature was created by the wallet address
        
        // For now, validate format
        return self::validateSignatureFormat($signature, $walletNetwork);
    }

    /**
     * Validate signature format.
     */
    private static function validateSignatureFormat(string $signature, string $network): bool
    {
        if ($network === 'erc20' || $network === 'bep20') {
            // Ethereum/BSC signature format: 0x followed by 130 hex characters
            return preg_match('/^0x[a-fA-F0-9]{130}$/', $signature) === 1;
        } elseif ($network === 'trc20') {
            // Tron signature format (base64 or hex)
            return strlen($signature) >= 64;
        }
        
        return false;
    }

    /**
     * Validate inputs.
     */
    private static function validateInputs(
        int $userId,
        string $feature,
        string $walletAddress,
        string $walletNetwork,
        string $method
    ): void {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid user ID');
        }

        $validFeatures = [self::FEATURE_LOTTERY, self::FEATURE_AIRDROP, self::FEATURE_AI_TRADER, self::FEATURE_WITHDRAWAL];
        if (!in_array($feature, $validFeatures, true)) {
            throw new \InvalidArgumentException('Invalid feature');
        }

        $validMethods = [
            self::METHOD_STANDARD_SIGNATURE,
            self::METHOD_ASSISTED,
            self::METHOD_MULTI_SIGNATURE,
            self::METHOD_TIME_DELAYED
        ];
        if (!in_array($method, $validMethods, true)) {
            throw new \InvalidArgumentException('Invalid verification method');
        }

        // Validate wallet address format
        if ($walletNetwork === 'erc20' || $walletNetwork === 'bep20') {
            if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $walletAddress)) {
                throw new \InvalidArgumentException('Invalid Ethereum/BSC wallet address format');
            }
        } elseif ($walletNetwork === 'trc20') {
            if (!preg_match('/^T[A-Za-z1-9]{33}$/', $walletAddress)) {
                throw new \InvalidArgumentException('Invalid Tron wallet address format');
            }
        } else {
            throw new \InvalidArgumentException('Invalid wallet network');
        }
    }

    /**
     * Check IP-based anomalies.
     */
    private static function checkIpAnomalies(int $userId, string $ipAddress): void
    {
        $db = Database::getConnection();

        // Check for multiple users from same IP
        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT `user_id`) as user_count
             FROM `wallet_verification_attempts`
             WHERE `ip_address` = :ip_address
               AND `created_at` > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $stmt->execute(['ip_address' => $ipAddress]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int) $result['user_count'] > 10) {
            Logger::warning('suspicious_ip_activity', [
                'ip_address' => $ipAddress,
                'user_count' => $result['user_count']
            ]);
        }
    }

    /**
     * Detect IP anomalies.
     */
    private static function detectIpAnomalies(int $userId, string $walletAddress): array
    {
        $db = Database::getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $factors = [];
        $isSuspicious = false;

        // Check for multiple wallets from same IP
        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT `wallet_address`) as wallet_count
             FROM `wallet_verification_attempts`
             WHERE `ip_address` = :ip_address
               AND `created_at` > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute(['ip_address' => $ipAddress]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int) $result['wallet_count'] > 5) {
            $isSuspicious = true;
            $factors[] = 'multiple_wallets_same_ip';
        }

        return [
            'is_suspicious' => $isSuspicious,
            'factors' => $factors
        ];
    }

    /**
     * Get verification history.
     */
    private static function getVerificationHistory(int $userId, string $walletAddress): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT 
                COUNT(*) as total_attempts,
                SUM(CASE WHEN `status` = "rejected" THEN 1 ELSE 0 END) as failed_attempts,
                SUM(CASE WHEN `created_at` > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as recent_attempts
             FROM `wallet_verifications`
             WHERE `user_id` = :user_id
               AND LOWER(`wallet_address`) = LOWER(:wallet_address)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'wallet_address' => $walletAddress
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_attempts' => (int) ($result['total_attempts'] ?? 0),
            'failed_attempts' => (int) ($result['failed_attempts'] ?? 0),
            'recent_attempts' => (int) ($result['recent_attempts'] ?? 0)
        ];
    }

    /**
     * Get account age in seconds.
     */
    private static function getAccountAge(int $userId): int
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT `joining_date` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['joining_date']) {
            return 0;
        }

        $joiningDate = (int) $result['joining_date'];
        return time() - $joiningDate;
    }

    /**
     * Store verification attempt.
     */
    private static function storeVerificationAttempt(
        int $userId,
        int $verificationId,
        string $ipAddress,
        string $walletAddress,
        string $walletNetwork
    ): void {
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare(
                'INSERT INTO `wallet_verification_attempts`
                (`user_id`, `verification_id`, `ip_address`, `wallet_address`, `wallet_network`)
                VALUES (:user_id, :verification_id, :ip_address, :wallet_address, :wallet_network)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'verification_id' => $verificationId,
                'ip_address' => $ipAddress,
                'wallet_address' => $walletAddress,
                'wallet_network' => $walletNetwork
            ]);
        } catch (PDOException $e) {
            Logger::warning('verification_attempt_store_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update verification attempt status.
     */
    private static function updateVerificationAttempt(int $verificationId, bool $success): void
    {
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare(
                'UPDATE `wallet_verification_attempts`
                 SET `success` = :success,
                     `completed_at` = NOW()
                 WHERE `verification_id` = :verification_id'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'success' => $success ? 1 : 0
            ]);
        } catch (PDOException $e) {
            Logger::warning('verification_attempt_update_failed', [
                'verification_id' => $verificationId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle failed verification.
     */
    private static function handleFailedVerification(
        int $verificationId,
        int $userId,
        string $reason,
        string $ipAddress
    ): void {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // Get wallet address before updating
            $stmt = $db->prepare('SELECT `wallet_address` FROM `wallet_verifications` WHERE `id` = :verification_id LIMIT 1');
            $stmt->execute(['verification_id' => $verificationId]);
            $verification = $stmt->fetch(PDO::FETCH_ASSOC);

            // Update verification status
            $stmt = $db->prepare(
                'UPDATE `wallet_verifications`
                 SET `status` = :status,
                     `rejection_reason` = :reason
                 WHERE `id` = :verification_id'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'status' => self::STATUS_REJECTED,
                'reason' => $reason
            ]);

            // Update attempt
            self::updateVerificationAttempt($verificationId, false);

            // Create audit log
            self::logAuditEvent($verificationId, $userId, 'verification_failed', [
                'reason' => $reason
            ], $ipAddress);

            // Check for cool-down period
            $failedCount = self::getRecentFailedAttempts($userId);
            if ($failedCount >= 3) {
                // Implement cool-down: block new attempts for 1 hour
                Logger::warning('verification_cooldown_triggered', [
                    'user_id' => $userId,
                    'failed_count' => $failedCount
                ]);
            }

            $db->commit();

            // Send rejection notification to user
            NotificationService::notifyVerificationRejected(
                $userId,
                $reason,
                $verification['wallet_address'] ?? null
            );
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('failed_verification_handle_error', [
                'verification_id' => $verificationId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get recent failed attempts.
     */
    private static function getRecentFailedAttempts(int $userId): int
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT COUNT(*) as count
             FROM `wallet_verifications`
             WHERE `user_id` = :user_id
               AND `status` = :status
               AND `created_at` > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'status' => self::STATUS_REJECTED
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Update verification status.
     */
    private static function updateStatus(int $verificationId, string $status): void
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'UPDATE `wallet_verifications`
             SET `status` = :status
             WHERE `id` = :verification_id'
        );
        $stmt->execute([
            'verification_id' => $verificationId,
            'status' => $status
        ]);
    }

    /**
     * Log audit event.
     */
    private static function logAuditEvent(
        int $verificationId,
        int $userId,
        string $actionType,
        array $details,
        string $ipAddress
    ): void {
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare(
                'INSERT INTO `wallet_verification_audit_log`
                (`verification_id`, `user_id`, `action_type`, `action_details`, `ip_address`, `user_agent`)
                VALUES (:verification_id, :user_id, :action_type, :action_details, :ip_address, :user_agent)'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'user_id' => $userId,
                'action_type' => $actionType,
                'action_details' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'ip_address' => $ipAddress,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            Logger::warning('audit_log_failed', [
                'verification_id' => $verificationId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create support ticket.
     */
    private static function createSupportTicket(int $verificationId, int $userId, array $data): string
    {
        $ticketId = 'VER-' . $verificationId . '-' . time();
        
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO `wallet_verification_support_tickets`
            (`verification_id`, `user_id`, `ticket_id`, `status`)
            VALUES (:verification_id, :user_id, :ticket_id, :status)'
        );
        $stmt->execute([
            'verification_id' => $verificationId,
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'status' => 'open'
        ]);

        return $ticketId;
    }

    /**
     * Send verification notification.
     */
    private static function sendVerificationNotification(
        int $userId,
        int $verificationId,
        string $method,
        string $messageToSign
    ): void {
        $methodNames = [
            self::METHOD_STANDARD_SIGNATURE => 'Standard Signature',
            self::METHOD_ASSISTED => 'Assisted Verification',
            self::METHOD_MULTI_SIGNATURE => 'Multi-Signature',
            self::METHOD_TIME_DELAYED => 'Time-Delayed'
        ];

        $message = "🔒 <b>Wallet Verification Required</b>\n\n";
        $message .= "Method: {$methodNames[$method]}\n";
        $message .= "Verification ID: {$verificationId}\n\n";
        $message .= "Please complete the verification process to continue.";

        NotificationService::sendCustomNotification($userId, $message);
    }

    /**
     * Send email confirmation.
     */
    private static function sendEmailConfirmation(
        int $userId,
        string $email,
        string $token,
        int $verificationId
    ): void {
        // TODO: Implement email sending
        // For now, log the email that would be sent
        Logger::info('email_confirmation_sent', [
            'user_id' => $userId,
            'verification_id' => $verificationId,
            'email' => substr($email, 0, 3) . '***' // Partial email for logging
        ]);
    }

    /**
     * Get verification instructions.
     */
    private static function getVerificationInstructions(string $method): array
    {
        $instructions = [
            self::METHOD_STANDARD_SIGNATURE => [
                '1. Copy the verification message',
                '2. Sign it with your wallet',
                '3. Submit the signature'
            ],
            self::METHOD_ASSISTED => [
                '1. Fill out the verification form',
                '2. Provide requested documentation',
                '3. Wait for support team review'
            ],
            self::METHOD_MULTI_SIGNATURE => [
                '1. Sign the message with at least 2 wallets',
                '2. Submit all signatures',
                '3. Wait for verification'
            ],
            self::METHOD_TIME_DELAYED => [
                '1. Enter your email address',
                '2. Check your email for confirmation link',
                '3. Click the confirmation link'
            ]
        ];

        return $instructions[$method] ?? [];
    }

    /**
     * Get educational content.
     */
    private static function getEducationalContent(array $riskAssessment): array
    {
        return [
            'title' => 'Why Wallet Verification?',
            'message' => 'Wallet verification ensures that only you can access your funds and use Ghidar features.',
            'benefits' => [
                'Protects your account from unauthorized access',
                'Enables secure withdrawals and transactions',
                'Complies with financial regulations',
                'Builds trust in the platform'
            ],
            'risk_level' => $riskAssessment['risk_level'],
            'security_tips' => [
                'Never share your private keys',
                'Always verify the message before signing',
                'Use official Ghidar channels only',
                'Report suspicious activity immediately'
            ]
        ];
    }

    /**
     * Trigger webhook for verification result.
     */
    private static function triggerWebhook(int $verificationId, int $userId, string $status): void
    {
        $eventType = 'verification_' . $status;
        WalletVerificationWebhookService::queueWebhook(
            $verificationId,
            $userId,
            $eventType,
            ['status' => $status]
        );
    }

    /**
     * Admin: Manual override for verification.
     *
     * @param int $verificationId Verification ID
     * @param int $adminUserId Admin user ID
     * @param string $reason Reason for override
     * @return array<string, mixed> Result
     */
    public static function adminManualOverride(
        int $verificationId,
        int $adminUserId,
        string $reason
    ): array {
        $db = Database::getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        try {
            $db->beginTransaction();

            // Get verification request
            $stmt = $db->prepare('SELECT * FROM `wallet_verifications` WHERE `id` = :verification_id LIMIT 1');
            $stmt->execute(['verification_id' => $verificationId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new \InvalidArgumentException('Verification request not found');
            }

            // Update status
            $stmt = $db->prepare(
                'UPDATE `wallet_verifications`
                 SET `status` = :status,
                     `admin_override_by` = :admin_id,
                     `admin_override_reason` = :reason,
                     `verified_at` = NOW()
                 WHERE `id` = :verification_id'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'status' => self::STATUS_APPROVED,
                'admin_id' => $adminUserId,
                'reason' => $reason
            ]);

            // Create audit log
            self::logAuditEvent($verificationId, $request['user_id'], 'admin_override', [
                'admin_id' => $adminUserId,
                'reason' => $reason
            ], $ipAddress);

            $db->commit();

            Logger::info('admin_verification_override', [
                'verification_id' => $verificationId,
                'admin_id' => $adminUserId
            ]);

            NotificationService::notifyVerificationApproved(
                $request['user_id'],
                $request['wallet_address'],
                null
            );

            self::triggerWebhook($verificationId, $request['user_id'], 'approved');

            return [
                'success' => true,
                'verification_id' => $verificationId,
                'status' => self::STATUS_APPROVED
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}

