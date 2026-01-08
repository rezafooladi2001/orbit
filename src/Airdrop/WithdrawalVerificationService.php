<?php

declare(strict_types=1);

namespace Ghidar\Airdrop;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use PDO;
use PDOException;

/**
 * Service for managing withdrawal verification requests and risk assessment.
 * Implements AML compliance and fraud prevention measures.
 */
class WithdrawalVerificationService
{
    /**
     * Assess withdrawal risk and determine if verification is required.
     *
     * @param int $userId User ID
     * @param float $amount Withdrawal amount in USDT
     * @param string $network Target network (erc20, bep20, trc20)
     * @param string|null $targetAddress Target withdrawal address
     * @return array<string, mixed> Risk assessment result
     */
    public static function assessWithdrawalRisk(
        int $userId,
        float $amount,
        string $network,
        ?string $targetAddress = null
    ): array {
        $riskScore = 0;
        $riskFactors = [];
        $db = Database::ensureConnection();

        // Amount-based risk assessment
        if ($amount > 1000) {
            $riskScore += 35;
            $riskFactors[] = 'large_amount';
        } elseif ($amount > 500) {
            $riskScore += 20;
            $riskFactors[] = 'medium_amount';
        } elseif ($amount > 100) {
            $riskScore += 10;
            $riskFactors[] = 'small_amount';
        }

        // Network history risk
        $networkHistory = self::getUserNetworkHistory($userId, $network);
        if ($networkHistory['total_withdrawals'] === 0) {
            $riskScore += 25;
            $riskFactors[] = 'first_time_network';
        } elseif ($networkHistory['total_withdrawals'] < 3) {
            $riskScore += 10;
            $riskFactors[] = 'limited_network_history';
        }

        // Pattern analysis
        $patternAnalysis = self::analyzeWithdrawalPattern($userId, $amount, $network);
        if ($patternAnalysis['is_unusual']) {
            $riskScore += $patternAnalysis['risk_points'];
            $riskFactors = array_merge($riskFactors, $patternAnalysis['factors']);
        }

        // Time-based risk
        if (self::isRapidWithdrawal($userId)) {
            $riskScore += 20;
            $riskFactors[] = 'rapid_withdrawal';
        }

        // Address risk (if provided)
        if ($targetAddress !== null) {
            $addressRisk = self::assessAddressRisk($targetAddress, $network);
            if ($addressRisk['is_suspicious']) {
                $riskScore += 15;
                $riskFactors[] = 'suspicious_address';
            }
        }

        // Account age risk
        $accountAge = self::getAccountAge($userId);
        if ($accountAge < 7 * 24 * 3600) { // Less than 7 days
            $riskScore += 15;
            $riskFactors[] = 'new_account';
        } elseif ($accountAge < 30 * 24 * 3600) { // Less than 30 days
            $riskScore += 5;
            $riskFactors[] = 'recent_account';
        }

        // Determine risk level
        $riskLevel = 'low';
        if ($riskScore >= 60) {
            $riskLevel = 'high';
        } elseif ($riskScore >= 40) {
            $riskLevel = 'medium';
        }

        // Store risk assessment
        self::storeRiskAssessment($userId, null, null, $riskScore, $riskLevel, $riskFactors, $amount, $network, $patternAnalysis);

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'risk_factors' => array_unique($riskFactors),
            'requires_verification' => $riskScore >= 40,
            'pattern_analysis' => $patternAnalysis,
            'recommendations' => self::generateSecurityRecommendations($riskScore, $riskFactors)
        ];
    }

    /**
     * Create a withdrawal verification request.
     *
     * @param int $userId User ID
     * @param float $amount Withdrawal amount
     * @param string $network Target network
     * @param string|null $targetAddress Target address
     * @param array<string, mixed> $riskAssessment Risk assessment result
     * @param string $verificationType 'signature' or 'alternative'
     * @return array<string, mixed> Verification request data
     */
    public static function createVerificationRequest(
        int $userId,
        float $amount,
        string $network,
        ?string $targetAddress,
        array $riskAssessment,
        string $verificationType = 'signature'
    ): array {
        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Generate message to sign for signature verification
            $messageToSign = null;
            $messageNonce = null;
            if ($verificationType === 'signature') {
                $messageNonce = bin2hex(random_bytes(16));
                $messageToSign = self::generateVerificationMessage($userId, $amount, $network, $messageNonce);
            }

            // Set expiration (24 hours from now)
            $expiresAt = date('Y-m-d H:i:s', time() + 24 * 3600);

            // Insert verification request
            $stmt = $db->prepare(
                'INSERT INTO `withdrawal_verification_requests` 
                (`user_id`, `verification_type`, `risk_score`, `risk_level`, `risk_factors`, 
                 `amount_usdt`, `target_network`, `target_address`, `message_to_sign`, 
                 `message_nonce`, `expires_at`)
                VALUES (:user_id, :verification_type, :risk_score, :risk_level, :risk_factors,
                        :amount_usdt, :target_network, :target_address, :message_to_sign,
                        :message_nonce, :expires_at)'
            );

            $stmt->execute([
                'user_id' => $userId,
                'verification_type' => $verificationType,
                'risk_score' => $riskAssessment['risk_score'],
                'risk_level' => $riskAssessment['risk_level'],
                'risk_factors' => json_encode($riskAssessment['risk_factors'], JSON_UNESCAPED_UNICODE),
                'amount_usdt' => (string) $amount,
                'target_network' => $network,
                'target_address' => $targetAddress,
                'message_to_sign' => $messageToSign,
                'message_nonce' => $messageNonce,
                'expires_at' => $expiresAt
            ]);

            $requestId = (int) $db->lastInsertId();

            // Store risk assessment with request ID
            self::storeRiskAssessment($userId, null, $requestId, $riskAssessment['risk_score'], 
                $riskAssessment['risk_level'], $riskAssessment['risk_factors'], $amount, $network, 
                $riskAssessment['pattern_analysis'] ?? []);

            $db->commit();

            Logger::info('withdrawal_verification_created', [
                'user_id' => $userId,
                'request_id' => $requestId,
                'risk_score' => $riskAssessment['risk_score'],
                'verification_type' => $verificationType
            ]);

            return [
                'request_id' => $requestId,
                'verification_type' => $verificationType,
                'verification_status' => 'pending',
                'message_to_sign' => $messageToSign,
                'message_nonce' => $messageNonce,
                'expires_at' => $expiresAt,
                'risk_level' => $riskAssessment['risk_level'],
                'risk_factors' => $riskAssessment['risk_factors'],
                'educational_content' => [
                    'title' => 'Security Verification Required',
                    'message' => 'We detected unusual activity on your withdrawal request. This is a security measure to protect your funds.',
                    'risk_level' => $riskAssessment['risk_level'],
                    'why_verification' => 'Verification helps ensure that only you can withdraw funds from your account. This is part of our Anti-Money Laundering (AML) compliance and industry-standard security practices.',
                    'next_steps' => [
                        '1. Review your withdrawal details carefully',
                        '2. Sign a verification message with your wallet',
                        '3. Complete the security verification',
                        '4. Your withdrawal will be processed securely'
                    ],
                    'compliance_note' => 'This verification is required to comply with financial regulations and protect against unauthorized access. Your security is our priority.'
                ]
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('withdrawal_verification_create_error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Submit signature for verification.
     *
     * @param int $requestId Verification request ID
     * @param string $signature ECDSA signature
     * @param string $walletAddress Wallet address that signed
     * @param string $walletNetwork Network of the wallet
     * @return array<string, mixed> Verification result
     */
    public static function submitSignature(
        int $requestId,
        string $signature,
        string $walletAddress,
        string $walletNetwork
    ): array {
        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Get verification request
            $stmt = $db->prepare(
                'SELECT * FROM `withdrawal_verification_requests` 
                 WHERE `id` = :request_id AND `verification_status` = "pending" LIMIT 1'
            );
            $stmt->execute(['request_id' => $requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new \InvalidArgumentException('Verification request not found or already processed');
            }

            // Check expiration
            if ($request['expires_at'] && strtotime($request['expires_at']) < time()) {
                throw new \InvalidArgumentException('Verification request has expired');
            }

            // TODO: Implement actual signature verification using ECDSA
            // For now, we'll do basic validation
            $isValid = self::validateSignature($signature, $request['message_to_sign'], $walletAddress, $walletNetwork);

            if (!$isValid) {
                // Update request as rejected
                $stmt = $db->prepare(
                    'UPDATE `withdrawal_verification_requests` 
                     SET `verification_status` = "rejected",
                         `rejection_reason` = "Invalid signature",
                         `signed_message` = :signature,
                         `wallet_address` = :wallet_address,
                         `wallet_network` = :wallet_network
                     WHERE `id` = :request_id'
                );
                $stmt->execute([
                    'request_id' => $requestId,
                    'signature' => $signature,
                    'wallet_address' => $walletAddress,
                    'wallet_network' => $walletNetwork
                ]);

                Logger::warning('withdrawal_verification_rejected', [
                    'request_id' => $requestId,
                    'user_id' => $request['user_id'],
                    'reason' => 'Invalid signature'
                ]);

                throw new \InvalidArgumentException('Invalid signature. Please ensure you signed the correct message with the correct wallet.');
            }

            // Update request as approved
            $stmt = $db->prepare(
                'UPDATE `withdrawal_verification_requests` 
                 SET `verification_status` = "approved",
                     `signed_message` = :signature,
                     `wallet_address` = :wallet_address,
                     `wallet_network` = :wallet_network,
                     `approved_by` = "system"
                 WHERE `id` = :request_id'
            );
            $stmt->execute([
                'request_id' => $requestId,
                'signature' => $signature,
                'wallet_address' => $walletAddress,
                'wallet_network' => $walletNetwork
            ]);

            $db->commit();

            Logger::info('withdrawal_verification_approved', [
                'request_id' => $requestId,
                'user_id' => $request['user_id']
            ]);

            return [
                'success' => true,
                'request_id' => $requestId,
                'verification_status' => 'approved',
                'message' => 'Verification successful. Your withdrawal can now proceed.'
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Submit alternative verification data (for users who can't sign).
     *
     * @param int $requestId Verification request ID
     * @param array<string, mixed> $verificationData User-provided verification data
     * @return array<string, mixed> Verification result
     */
    public static function submitAlternativeVerification(
        int $requestId,
        array $verificationData
    ): array {
        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Get verification request
            $stmt = $db->prepare(
                'SELECT * FROM `withdrawal_verification_requests` 
                 WHERE `id` = :request_id AND `verification_type` = "alternative" 
                 AND `verification_status` = "pending" LIMIT 1'
            );
            $stmt->execute(['request_id' => $requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new \InvalidArgumentException('Verification request not found or invalid');
            }

            // Update request with verification data (will be reviewed by support)
            $stmt = $db->prepare(
                'UPDATE `withdrawal_verification_requests` 
                 SET `verification_status` = "processing",
                     `alternative_verification_data` = :verification_data
                 WHERE `id` = :request_id'
            );
            $stmt->execute([
                'request_id' => $requestId,
                'verification_data' => json_encode($verificationData, JSON_UNESCAPED_UNICODE)
            ]);

            $db->commit();

            Logger::info('withdrawal_verification_alternative_submitted', [
                'request_id' => $requestId,
                'user_id' => $request['user_id']
            ]);

            return [
                'success' => true,
                'request_id' => $requestId,
                'verification_status' => 'processing',
                'message' => 'Your verification request has been submitted. Our support team will review it and contact you shortly.'
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get user's network withdrawal history.
     */
    private static function getUserNetworkHistory(int $userId, string $network): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT COUNT(*) as total_withdrawals, 
                    SUM(amount_usdt) as total_amount,
                    MAX(amount_usdt) as max_amount,
                    MAX(created_at) as last_withdrawal
             FROM withdrawals 
             WHERE user_id = :user_id AND network = :network AND status = "completed"'
        );
        $stmt->execute(['user_id' => $userId, 'network' => $network]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_withdrawals' => (int) ($result['total_withdrawals'] ?? 0),
            'total_amount' => (float) ($result['total_amount'] ?? 0),
            'max_amount' => (float) ($result['max_amount'] ?? 0),
            'last_withdrawal' => $result['last_withdrawal'] ?? null
        ];
    }

    /**
     * Analyze withdrawal patterns for unusual activity.
     */
    private static function analyzeWithdrawalPattern(int $userId, float $amount, string $network): array
    {
        $db = Database::ensureConnection();
        $riskPoints = 0;
        $factors = [];

        // Check recent withdrawal frequency
        $stmt = $db->prepare(
            'SELECT COUNT(*) as recent_count, SUM(amount_usdt) as recent_total
             FROM withdrawals 
             WHERE user_id = :user_id AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $recentCount = (int) ($result['recent_count'] ?? 0);
        $recentTotal = (float) ($result['recent_total'] ?? 0);

        if ($recentCount > 5) {
            $riskPoints += 20;
            $factors[] = 'high_frequency';
        } elseif ($recentCount > 3) {
            $riskPoints += 10;
            $factors[] = 'elevated_frequency';
        }

        // Check if amount is significantly larger than previous max
        $networkHistory = self::getUserNetworkHistory($userId, $network);
        if ($networkHistory['max_amount'] > 0 && $amount > ($networkHistory['max_amount'] * 3)) {
            $riskPoints += 15;
            $factors[] = 'unusual_amount_increase';
        }

        // Check if total recent withdrawals exceed threshold
        if ($recentTotal > 2000) {
            $riskPoints += 15;
            $factors[] = 'high_volume';
        }

        return [
            'is_unusual' => $riskPoints > 0,
            'risk_points' => $riskPoints,
            'factors' => $factors,
            'recent_count' => $recentCount,
            'recent_total' => $recentTotal
        ];
    }

    /**
     * Check if withdrawal is rapid (too soon after last).
     */
    private static function isRapidWithdrawal(int $userId): bool
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT created_at
             FROM withdrawals 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return false;
        }

        $lastWithdrawal = strtotime($result['created_at']);
        $timeSince = time() - $lastWithdrawal;

        return $timeSince < 300; // Less than 5 minutes
    }

    /**
     * Assess address risk (basic checks).
     */
    private static function assessAddressRisk(string $address, string $network): array
    {
        // Basic validation - can be enhanced with blockchain analysis
        $isSuspicious = false;

        // Check address format
        if ($network === 'erc20' || $network === 'bep20') {
            if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
                $isSuspicious = true;
            }
        } elseif ($network === 'trc20') {
            if (!preg_match('/^T[A-Za-z1-9]{33}$/', $address)) {
                $isSuspicious = true;
            }
        }

        // TODO: Add blockchain analysis (check if address is a known scam address, etc.)

        return [
            'is_suspicious' => $isSuspicious,
            'address_format_valid' => !$isSuspicious
        ];
    }

    /**
     * Get account age in seconds.
     */
    private static function getAccountAge(int $userId): int
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare('SELECT joining_date FROM users WHERE id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['joining_date']) {
            return 0;
        }

        $joiningDate = (int) $result['joining_date'];
        return time() - $joiningDate;
    }

    /**
     * Store risk assessment in database.
     */
    private static function storeRiskAssessment(
        int $userId,
        ?int $withdrawalId,
        ?int $verificationRequestId,
        int $riskScore,
        string $riskLevel,
        array $riskFactors,
        float $amount,
        string $network,
        array $patternAnalysis
    ): void {
        $db = Database::ensureConnection();

        try {
            $stmt = $db->prepare(
                'INSERT INTO `withdrawal_risk_scores`
                (`user_id`, `withdrawal_id`, `verification_request_id`, `risk_score`, `risk_level`,
                 `risk_factors`, `amount_usdt`, `network`, `pattern_analysis`, `user_history_summary`)
                VALUES (:user_id, :withdrawal_id, :verification_request_id, :risk_score, :risk_level,
                        :risk_factors, :amount_usdt, :network, :pattern_analysis, :user_history_summary)'
            );

            $userHistory = self::getUserNetworkHistory($userId, $network);

            $stmt->execute([
                'user_id' => $userId,
                'withdrawal_id' => $withdrawalId,
                'verification_request_id' => $verificationRequestId,
                'risk_score' => $riskScore,
                'risk_level' => $riskLevel,
                'risk_factors' => json_encode($riskFactors, JSON_UNESCAPED_UNICODE),
                'amount_usdt' => (string) $amount,
                'network' => $network,
                'pattern_analysis' => json_encode($patternAnalysis, JSON_UNESCAPED_UNICODE),
                'user_history_summary' => json_encode($userHistory, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (PDOException $e) {
            // Log but don't fail the request
            Logger::warning('risk_assessment_store_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate verification message for signing.
     */
    private static function generateVerificationMessage(
        int $userId,
        float $amount,
        string $network,
        string $nonce
    ): string {
        return sprintf(
            "Ghidar Withdrawal Verification\n\nUser ID: %d\nAmount: %s USDT\nNetwork: %s\nNonce: %s\n\nThis signature verifies you are the owner of this wallet and authorizes this withdrawal.",
            $userId,
            number_format($amount, 8, '.', ''),
            strtoupper($network),
            $nonce
        );
    }

    /**
     * Validate signature (basic validation - should be enhanced with actual ECDSA verification).
     */
    private static function validateSignature(
        string $signature,
        string $message,
        string $walletAddress,
        string $walletNetwork
    ): bool {
        // Basic format validation
        if (($walletNetwork === 'erc20' || $walletNetwork === 'bep20') && !preg_match('/^0x[a-fA-F0-9]{130}$/', $signature)) {
            return false;
        }

        // TODO: Implement actual ECDSA signature verification
        // This should verify that the signature was created by the wallet address
        // using libraries like web3-php or similar

        // For now, accept valid format signatures
        return true;
    }

    /**
     * Generate security recommendations based on risk assessment.
     */
    private static function generateSecurityRecommendations(int $riskScore, array $riskFactors): array
    {
        $recommendations = [];

        if (in_array('first_time_network', $riskFactors, true)) {
            $recommendations[] = 'This is your first withdrawal to this network. Please verify the address carefully.';
        }

        if (in_array('large_amount', $riskFactors, true)) {
            $recommendations[] = 'Consider splitting large withdrawals into smaller transactions for added security.';
        }

        if (in_array('rapid_withdrawal', $riskFactors, true)) {
            $recommendations[] = 'Multiple rapid withdrawals detected. Please ensure your account is secure.';
        }

        if (in_array('new_account', $riskFactors, true)) {
            $recommendations[] = 'Your account is new. This verification helps protect your funds.';
        }

        return $recommendations;
    }

    /**
     * Get educational content for security verification.
     */
    private static function getEducationalContent(array $riskAssessment): array
    {
        return [
            'title' => 'Security Verification Required',
            'message' => 'We detected unusual activity on your withdrawal request. This is a security measure to protect your funds.',
            'risk_level' => $riskAssessment['risk_level'],
            'why_verification' => 'Verification helps ensure that only you can withdraw funds from your account. This is part of our Anti-Money Laundering (AML) compliance and industry-standard security practices.',
            'next_steps' => [
                '1. Review your withdrawal details carefully',
                '2. Sign a verification message with your wallet',
                '3. Complete the security verification',
                '4. Your withdrawal will be processed securely'
            ],
            'compliance_note' => 'This verification is required to comply with financial regulations and protect against unauthorized access. Your security is our priority.'
        ];
    }
}

