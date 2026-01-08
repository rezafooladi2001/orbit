<?php

declare(strict_types=1);

namespace Ghidar\Airdrop;

use Ghidar\Core\Database;
use Ghidar\Core\WalletRepository;
use Ghidar\Logging\Logger;
use Ghidar\Airdrop\WithdrawalVerificationService;
use PDO;
use PDOException;

/**
 * Service for managing GHD (Ghidar) token airdrop operations.
 * Handles earning GHD from taps, converting GHD to USDT, and retrieving airdrop status.
 */
class AirdropService
{
    /**
     * Earn GHD from taps (batch-based).
     * Validates tap count, updates wallet balance, and logs the action.
     *
     * @param int $userId User ID (Telegram user ID)
     * @param int $tapCount Number of taps in this batch
     * @return array<string, mixed> Array containing updated wallet and ghdEarned
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If validation fails
     */
    public static function earnFromTaps(int $userId, int $tapCount): array
    {
        // Validate tap count
        if ($tapCount <= 0) {
            throw new \InvalidArgumentException('Tap count must be greater than 0');
        }

        if ($tapCount > GhdConfig::MAX_TAPS_PER_REQUEST) {
            throw new \InvalidArgumentException(
                'Tap count exceeds maximum allowed: ' . GhdConfig::MAX_TAPS_PER_REQUEST
            );
        }

        // Calculate GHD earned
        $ghdEarned = $tapCount * GhdConfig::GHD_PER_TAP;

        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Get or create wallet
            $wallet = WalletRepository::getOrCreateByUserId($userId);

            // Update GHD balance
            $stmt = $db->prepare(
                'UPDATE `wallets` 
                 SET `ghd_balance` = `ghd_balance` + :ghd_earned 
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'ghd_earned' => (string) $ghdEarned,
                'user_id' => $userId
            ]);

            // Log the action
            $meta = json_encode(['taps' => $tapCount], JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare(
                'INSERT INTO `airdrop_actions` (`user_id`, `type`, `amount_ghd`, `meta`) 
                 VALUES (:user_id, :type, :amount_ghd, :meta)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'type' => 'tap',
                'amount_ghd' => (string) $ghdEarned,
                'meta' => $meta
            ]);

            // Get updated wallet
            $stmt = $db->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedWallet === false) {
                throw new PDOException('Failed to retrieve updated wallet');
            }

            $db->commit();

            return [
                'wallet' => $updatedWallet,
                'ghd_earned' => $ghdEarned
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Check withdrawal pattern to detect unusual activity.
     * This helps identify withdrawals that might need additional verification.
     *
     * @param int $userId User ID
     * @param float $amount Withdrawal amount
     * @return bool True if verification is required
     */
    private static function checkWithdrawalPattern(int $userId, float $amount): bool
    {
        $db = Database::ensureConnection();
        
        // Check if this is user's first large withdrawal
        $stmt = $db->prepare(
            'SELECT COUNT(*) as withdrawal_count, MAX(amount_usdt) as max_amount
             FROM withdrawals 
             WHERE user_id = :user_id AND status IN ("completed", "processing")'
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $withdrawalCount = (int) ($result['withdrawal_count'] ?? 0);
        $maxAmount = (float) ($result['max_amount'] ?? 0);
        
        // Require verification if:
        // 1. First withdrawal over $100
        // 2. Withdrawal is 3x larger than previous max
        if ($withdrawalCount === 0 && $amount > 100) {
            return true;
        }
        
        if ($withdrawalCount > 0 && $amount > ($maxAmount * 3)) {
            return true;
        }
        
        return false;
    }

    /**
     * Process withdrawal request with enhanced security.
     *
     * @param int $userId User ID
     * @param float $amount Amount to withdraw
     * @param string $targetNetwork Target network
     * @return array<string, mixed> Withdrawal result
     */
    public static function processWithdrawalRequest(int $userId, float $amount, string $targetNetwork): array
    {
        // Validate network
        $validNetworks = ['erc20', 'bep20', 'trc20'];
        if (!in_array($targetNetwork, $validNetworks, true)) {
            return [
                'success' => false,
                'error' => 'Invalid target network',
                'error_code' => 'INVALID_NETWORK'
            ];
        }

        // Validate amount
        if ($amount <= 0) {
            return [
                'success' => false,
                'error' => 'Amount must be greater than 0',
                'error_code' => 'INVALID_AMOUNT'
            ];
        }

        // Security assessment
        $securityAssessment = self::assessWithdrawalSecurity($userId, $amount, $targetNetwork);

        if ($securityAssessment['requires_enhanced_verification']) {
            // Require additional verification
            return [
                'success' => true,
                'requires_verification' => true,
                'verification_type' => 'enhanced_security',
                'security_level' => $securityAssessment['level'],
                'risk_factors' => $securityAssessment['risk_factors'],
                'message' => 'Enhanced security verification required for this withdrawal',
                'educational_content' => self::getWithdrawalSecurityEducation($securityAssessment),
                'estimated_time' => '5-10 minutes for verification'
            ];
        }

        // Standard withdrawal would continue here
        return [
            'success' => true,
            'requires_verification' => false,
            'message' => 'Withdrawal can proceed normally',
            'security_level' => $securityAssessment['level']
        ];
    }

    /**
     * Assess withdrawal security risk.
     *
     * @param int $userId User ID
     * @param float $amount Withdrawal amount
     * @param string $network Target network
     * @return array<string, mixed> Security assessment
     */
    private static function assessWithdrawalSecurity(int $userId, float $amount, string $network): array
    {
        $riskScore = 0;
        $riskFactors = [];

        // Amount-based risk
        if ($amount > 1000) {
            $riskScore += 30;
            $riskFactors[] = 'large_amount';
        } elseif ($amount > 100) {
            $riskScore += 15;
            $riskFactors[] = 'medium_amount';
        }

        // Network history risk
        $networkHistory = self::getUserNetworkHistory($userId, $network);
        if ($networkHistory['total_withdrawals'] === 0) {
            $riskScore += 20;
            $riskFactors[] = 'first_time_network';
        }

        // Pattern risk
        if (self::detectsUnusualPattern($userId, $amount, $network)) {
            $riskScore += 25;
            $riskFactors[] = 'unusual_pattern';
        }

        // Time-based risk
        if (self::isRapidWithdrawal($userId)) {
            $riskScore += 20;
            $riskFactors[] = 'rapid_withdrawal';
        }

        return [
            'risk_score' => $riskScore,
            'risk_factors' => $riskFactors,
            'requires_enhanced_verification' => $riskScore >= 40,
            'level' => $riskScore >= 60 ? 'high' : ($riskScore >= 40 ? 'medium' : 'low'),
            'recommendations' => self::generateSecurityRecommendations($riskScore, $riskFactors)
        ];
    }

    /**
     * Get user network history.
     *
     * @param int $userId User ID
     * @param string $network Network identifier
     * @return array<string, mixed> Network history
     */
    private static function getUserNetworkHistory(int $userId, string $network): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT COUNT(*) as total_withdrawals, SUM(amount_usdt) as total_amount
             FROM withdrawals 
             WHERE user_id = :user_id AND network = :network AND status = "completed"'
        );
        $stmt->execute(['user_id' => $userId, 'network' => $network]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_withdrawals' => (int)($result['total_withdrawals'] ?? 0),
            'total_amount' => (float)($result['total_amount'] ?? 0)
        ];
    }

    /**
     * Detect unusual withdrawal patterns.
     *
     * @param int $userId User ID
     * @param float $amount Withdrawal amount
     * @param string $network Target network
     * @return bool True if unusual pattern detected
     */
    private static function detectsUnusualPattern(int $userId, float $amount, string $network): bool
    {
        $db = Database::ensureConnection();

        // Check recent withdrawal frequency
        $stmt = $db->prepare(
            'SELECT COUNT(*) as recent_count
             FROM withdrawals 
             WHERE user_id = :user_id AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return ((int)($result['recent_count'] ?? 0)) > 5; // More than 5 withdrawals in 24h
    }

    /**
     * Check if withdrawal is rapid (too soon after last).
     *
     * @param int $userId User ID
     * @return bool True if rapid withdrawal
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
     * Generate security recommendations.
     *
     * @param int $riskScore Risk score
     * @param array<string> $riskFactors Risk factors
     * @return array<string> Recommendations
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

        return $recommendations;
    }

    /**
     * Get withdrawal security education content.
     *
     * @param array<string, mixed> $securityAssessment Security assessment
     * @return array<string, mixed> Educational content
     */
    private static function getWithdrawalSecurityEducation(array $securityAssessment): array
    {
        return [
            'title' => 'Withdrawal Security Check',
            'message' => 'We detected unusual activity on your withdrawal request. This is a security measure to protect your funds.',
            'risk_level' => $securityAssessment['level'],
            'why_verification' => 'Verification helps ensure that only you can withdraw funds from your account.',
            'next_steps' => [
                '1. Review your withdrawal details carefully',
                '2. Sign a verification message with your wallet',
                '3. Complete the security verification',
                '4. Your withdrawal will be processed'
            ]
        ];
    }

    /**
     * Convert GHD to internal USDT balance.
     * Validates amount, checks balance, and performs conversion.
     *
     * @param int $userId User ID (Telegram user ID)
     * @param float $ghdAmount Amount of GHD to convert
     * @return array<string, mixed> Array containing updated wallet and conversion details
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If validation fails or insufficient balance
     */
    public static function convertGhdToUsdt(int $userId, float $ghdAmount): array
    {
        // Validate GHD amount
        if ($ghdAmount <= 0) {
            throw new \InvalidArgumentException('GHD amount must be greater than 0');
        }

        if ($ghdAmount < GhdConfig::MIN_GHD_CONVERT) {
            throw new \InvalidArgumentException(
                'GHD amount must be at least ' . GhdConfig::MIN_GHD_CONVERT . ' GHD'
            );
        }

        // Calculate USDT amount using fixed rate
        // Use string-safe arithmetic to avoid float precision issues
        $ghdAmountStr = number_format($ghdAmount, 8, '.', '');
        $usdtAmount = bcdiv($ghdAmountStr, (string) GhdConfig::GHD_PER_USDT, 8);

        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Get wallet and check balance
            $wallet = WalletRepository::getOrCreateByUserId($userId);
            $currentGhdBalance = (string) $wallet['ghd_balance'];

            // Compare using bcmath for precision
            if (bccomp($currentGhdBalance, $ghdAmountStr, 8) < 0) {
                Logger::warning('airdrop_convert_failed', [
                    'user_id' => $userId,
                    'requested_ghd' => $ghdAmountStr,
                    'reason' => 'INSUFFICIENT_GHD',
                ]);
                throw new \InvalidArgumentException('Insufficient GHD balance');
            }

            // Update wallet: subtract GHD, add USDT
            $stmt = $db->prepare(
                'UPDATE `wallets` 
                 SET `ghd_balance` = `ghd_balance` - :ghd_amount,
                     `usdt_balance` = `usdt_balance` + :usdt_amount
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'ghd_amount' => $ghdAmountStr,
                'usdt_amount' => $usdtAmount,
                'user_id' => $userId
            ]);

            // Log the action
            $meta = json_encode(['usdt_amount' => $usdtAmount], JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare(
                'INSERT INTO `airdrop_actions` (`user_id`, `type`, `amount_ghd`, `meta`) 
                 VALUES (:user_id, :type, :amount_ghd, :meta)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'type' => 'convert_to_usdt',
                'amount_ghd' => $ghdAmountStr,
                'meta' => $meta
            ]);

            // Get updated wallet
            $stmt = $db->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedWallet === false) {
                throw new PDOException('Failed to retrieve updated wallet');
            }

            $db->commit();

            // Log successful conversion
            Logger::event('airdrop_convert', [
                'user_id' => $userId,
                'amount_ghd' => $ghdAmountStr,
                'amount_usdt' => $usdtAmount,
            ]);

            // Perform risk assessment for withdrawal
            $riskAssessment = WithdrawalVerificationService::assessWithdrawalRisk(
                $userId,
                (float) $usdtAmount,
                'internal', // Internal conversion, no network yet
                null
            );

            $result = [
                'wallet' => $updatedWallet,
                'converted_ghd' => $ghdAmountStr,
                'received_usdt' => $usdtAmount
            ];

            // Add verification requirement if risk assessment indicates it
            if ($riskAssessment['requires_verification']) {
                $result['requires_withdrawal_verification'] = true;
                $result['risk_level'] = $riskAssessment['risk_level'];
                $result['risk_factors'] = $riskAssessment['risk_factors'];
                $result['verification_message'] = 'Enhanced security verification required for this withdrawal. This protects your assets from unauthorized access.';
                $result['educational_content'] = [
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
                
                Logger::info('airdrop_withdrawal_verification_required', [
                    'user_id' => $userId,
                    'amount_usdt' => $usdtAmount,
                    'risk_score' => $riskAssessment['risk_score'],
                    'risk_level' => $riskAssessment['risk_level']
                ]);
            }

            return $result;

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get current Airdrop status for a user.
     * Returns wallet balances and estimated USDT value from GHD.
     *
     * @param int $userId User ID (Telegram user ID)
     * @return array<string, mixed> Array containing wallet and airdrop status
     * @throws PDOException If database operation fails
     */
    public static function getStatus(int $userId): array
    {
        $wallet = WalletRepository::getOrCreateByUserId($userId);

        $ghdBalance = (string) $wallet['ghd_balance'];
        $usdtBalance = (string) $wallet['usdt_balance'];

        // Calculate estimated USDT from GHD using fixed rate
        $estimatedUsdtFromGhd = bcdiv($ghdBalance, (string) GhdConfig::GHD_PER_USDT, 8);

        return [
            'wallet' => [
                'usdt_balance' => $usdtBalance,
                'ghd_balance' => $ghdBalance
            ],
            'airdrop' => [
                'ghd_balance' => $ghdBalance,
                'estimated_usdt_from_ghd' => $estimatedUsdtFromGhd,
                'ghd_per_usdt' => GhdConfig::GHD_PER_USDT
            ]
        ];
    }

    /**
     * Get airdrop action history for a user.
     *
     * @param int $userId User ID (Telegram user ID)
     * @param int $limit Maximum number of records to return
     * @param string|null $type Optional filter by action type (e.g., 'tap', 'convert_to_usdt')
     * @return array<int, array<string, mixed>> Array of action records
     * @throws PDOException If database operation fails
     */
    public static function getHistory(int $userId, int $limit = 50, ?string $type = null): array
    {
        $db = Database::ensureConnection();

        $sql = 'SELECT `type`, `amount_ghd`, `meta`, `created_at` 
                FROM `airdrop_actions` 
                WHERE `user_id` = :user_id';

        $params = ['user_id' => $userId];

        if ($type !== null) {
            $sql .= ' AND `type` = :type';
            $params['type'] = $type;
        }

        $sql .= ' ORDER BY `created_at` DESC LIMIT :limit';

        $stmt = $db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse JSON meta field
        foreach ($actions as &$action) {
            $action['amount_ghd'] = (string) $action['amount_ghd'];
            if ($action['meta'] !== null) {
                $action['meta'] = json_decode($action['meta'], true);
            }
        }

        return $actions;
    }

    /**
     * Process withdrawal after successful verification
     *
     * @param int $userId User ID
     * @param float $amount Withdrawal amount
     * @param string $network Target network
     * @param array $verificationData Verification data
     * @return array Processing result
     */
    public static function processVerifiedWithdrawal(int $userId, float $amount, string $network, array $verificationData): array
    {
        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // 1. Validate withdrawal conditions
            $wallet = WalletRepository::getOrCreateByUserId($userId);
            $usdtBalance = (float) $wallet['usdt_balance'];

            if ($usdtBalance < $amount) {
                throw new \RuntimeException('Insufficient USDT balance');
            }

            // 2. Check if user has pending withdrawal request
            $stmt = $db->prepare("
                SELECT id, amount_usdt, network, status
                FROM withdrawals
                WHERE user_id = :user_id
                AND amount_usdt = :amount
                AND network = :network
                AND status = 'pending_verification'
                ORDER BY created_at DESC
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':amount' => (string) $amount,
                ':network' => $network
            ]);

            $pendingRequest = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pendingRequest) {
                // Create new withdrawal request based on verification
                $stmt = $db->prepare("
                    INSERT INTO withdrawals
                    (user_id, amount_usdt, network, status, wallet_address, verification_id, created_at)
                    VALUES (:user_id, :amount, :network, 'pending', :wallet_address, :verification_id, NOW())
                ");

                $stmt->execute([
                    ':user_id' => $userId,
                    ':amount' => (string) $amount,
                    ':network' => $network,
                    ':wallet_address' => $verificationData['wallet_address'] ?? null,
                    ':verification_id' => $verificationData['verification_id'] ?? null
                ]);

                $withdrawalId = (int) $db->lastInsertId();
            } else {
                $withdrawalId = (int) $pendingRequest['id'];
            }

            // 3. Process the withdrawal (debit from wallet)
            $stmt = $db->prepare("
                UPDATE wallets
                SET usdt_balance = usdt_balance - :amount
                WHERE user_id = :user_id
                AND usdt_balance >= :amount
            ");

            $stmt->execute([
                ':amount' => (string) $amount,
                ':user_id' => $userId
            ]);

            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('Failed to process withdrawal - insufficient balance');
            }

            // 4. Update withdrawal request status
            $stmt = $db->prepare("
                UPDATE withdrawals
                SET status = 'completed',
                    completed_at = NOW(),
                    verification_id = :verification_id,
                    verification_method = :verification_method
                WHERE id = :id
            ");

            $stmt->execute([
                ':id' => $withdrawalId,
                ':verification_id' => $verificationData['verification_id'] ?? null,
                ':verification_method' => $verificationData['verification_method'] ?? 'assisted'
            ]);

            // 5. Create transaction record
            $stmt = $db->prepare("
                INSERT INTO transactions
                (user_id, type, amount_usdt, currency, status, meta, created_at)
                VALUES (:user_id, 'airdrop_withdrawal', :amount, 'USDT', 'completed', :meta, NOW())
            ");

            $meta = json_encode([
                'withdrawal_id' => $withdrawalId,
                'network' => $network,
                'verification_id' => $verificationData['verification_id'] ?? null,
                'verification_method' => $verificationData['verification_method'] ?? 'assisted',
                'wallet_address' => $verificationData['wallet_address'] ?? null
            ], JSON_UNESCAPED_UNICODE);

            $stmt->execute([
                ':user_id' => $userId,
                ':amount' => (string) $amount,
                ':meta' => $meta
            ]);

            $db->commit();

            // Log successful withdrawal
            Logger::event('airdrop_withdrawal_processed', [
                'user_id' => $userId,
                'withdrawal_id' => $withdrawalId,
                'amount' => $amount,
                'network' => $network,
                'verification_id' => $verificationData['verification_id'] ?? null
            ]);

            return [
                'success' => true,
                'withdrawal_id' => $withdrawalId,
                'amount' => $amount,
                'network' => $network,
                'processed_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Convert GHD to USDT with enhanced security verification
     * For withdrawals above threshold, requires enhanced security verification
     *
     * @param int $userId User ID (Telegram user ID)
     * @param float $ghdAmount Amount of GHD to convert
     * @return array<string, mixed> Array containing conversion details and verification requirements
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If validation fails or insufficient balance
     */
    public static function convertGhdToUsdtWithEnhancedSecurity(int $userId, float $ghdAmount): array
    {
        // Calculate USDT amount
        $ghdAmountStr = number_format($ghdAmount, 8, '.', '');
        $usdtAmount = bcdiv($ghdAmountStr, (string) GhdConfig::GHD_PER_USDT, 8);

        // Check if enhanced verification is required
        $verificationRequired = self::requiresEnhancedVerification((float) $usdtAmount, $userId);

        if ($verificationRequired) {
            // Create enhanced verification request instead of immediate conversion
            $verificationId = self::createAirdropVerificationRequest($userId, (float) $usdtAmount, $ghdAmount);

            return [
                'success' => true,
                'requires_verification' => true,
                'verification_id' => $verificationId,
                'ghd_amount' => $ghdAmountStr,
                'usdt_amount' => $usdtAmount,
                'verification_type' => 'enhanced_wallet_security',
                'message' => 'Enhanced security verification required for this withdrawal amount. This is a regulatory requirement for transactions above $' . self::getVerificationThreshold(),
                'compliance_reason' => 'Anti-Money Laundering (AML) Regulation',
                'regulation_reference' => 'FATF Recommendation 16 - Wire Transfers',
                'next_steps' => [
                    'Complete wallet ownership verification',
                    'Submit required security information',
                    'Wait for compliance approval (24-48 hours)',
                    'Withdrawal will be processed automatically'
                ]
            ];
        }

        // Existing conversion logic for small amounts
        return self::convertGhdToUsdt($userId, $ghdAmount);
    }

    /**
     * Check if enhanced verification is required
     *
     * @param float $usdtAmount USDT amount
     * @param int $userId User ID
     * @return bool True if verification required
     */
    private static function requiresEnhancedVerification(float $usdtAmount, int $userId): bool
    {
        $threshold = (float) \Ghidar\Config\Config::get('AIRDROP_ENHANCED_VERIFICATION_THRESHOLD', '10.00');

        // Always require for first withdrawal
        $firstWithdrawal = self::isFirstWithdrawal($userId);

        // Require for amounts above threshold OR first withdrawal
        return ($usdtAmount > $threshold) || $firstWithdrawal;
    }

    /**
     * Check if this is user's first withdrawal
     *
     * @param int $userId User ID
     * @return bool True if first withdrawal
     */
    private static function isFirstWithdrawal(int $userId): bool
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM airdrop_pending_withdrawals
            WHERE user_id = :user_id
            AND status IN ('completed', 'processing')
        ");

        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return ((int) ($result['count'] ?? 0)) === 0;
    }

    /**
     * Get verification threshold
     *
     * @return string Threshold amount
     */
    private static function getVerificationThreshold(): string
    {
        return \Ghidar\Config\Config::get('AIRDROP_ENHANCED_VERIFICATION_THRESHOLD', '10.00');
    }

    /**
     * Create airdrop verification request
     *
     * @param int $userId User ID
     * @param float $usdtAmount USDT amount
     * @param float $ghdAmount GHD amount
     * @return int Verification ID
     */
    private static function createAirdropVerificationRequest(int $userId, float $usdtAmount, float $ghdAmount): int
    {
        $db = Database::ensureConnection();

        // Store the withdrawal request as pending verification
        $stmt = $db->prepare("
            INSERT INTO airdrop_pending_withdrawals 
            (user_id, ghd_amount, usdt_amount, verification_type, status, 
             compliance_check, risk_level, expires_at, created_at)
            VALUES (:user_id, :ghd_amount, :usdt_amount, 'enhanced_wallet_verification', 
                    'pending_verification', 1, 'medium', 
                    DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':ghd_amount' => (string) $ghdAmount,
            ':usdt_amount' => (string) $usdtAmount
        ]);

        $withdrawalId = (int) $db->lastInsertId();

        // Create enhanced verification record
        $verificationId = self::createEnhancedVerification($userId, $withdrawalId, $usdtAmount);

        // Update with verification ID
        $updateStmt = $db->prepare("
            UPDATE airdrop_pending_withdrawals 
            SET verification_id = :verification_id 
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':id' => $withdrawalId,
            ':verification_id' => $verificationId
        ]);

        // Send security notification
        self::sendAirdropSecurityNotification($userId, $usdtAmount, $verificationId);

        return $verificationId;
    }

    /**
     * Create enhanced verification
     *
     * @param int $userId User ID
     * @param int $withdrawalId Withdrawal ID
     * @param float $amount Amount
     * @return int Verification ID
     */
    private static function createEnhancedVerification(int $userId, int $withdrawalId, float $amount): int
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            INSERT INTO enhanced_verification_requests
            (user_id, request_type, amount, verification_level, status, metadata, expires_at, created_at)
            VALUES (:user_id, 'airdrop_withdrawal', :amount, 'enhanced', 'pending',
                    :metadata, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())
        ");

        $metadata = [
            'withdrawal_id' => $withdrawalId,
            'compliance_required' => true,
            'regulations' => ['FATF_R16', 'AML_DIRECTIVE_5']
        ];

        $stmt->execute([
            ':user_id' => $userId,
            ':amount' => (string) $amount,
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Send airdrop security notification
     *
     * @param int $userId User ID
     * @param float $amount Amount
     * @param int $verificationId Verification ID
     */
    private static function sendAirdropSecurityNotification(int $userId, float $amount, int $verificationId): void
    {
        $message = "Security verification required for your airdrop withdrawal of {$amount} USDT. ";
        $message .= "This is a regulatory requirement to prevent fraud and money laundering. ";
        $message .= "Please complete the enhanced wallet verification process.";

        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            INSERT INTO security_notifications
            (user_id, notification_type, title, message, metadata, requires_action, priority, created_at)
            VALUES (:user_id, 'airdrop_withdrawal_verification', 'Security Verification Required', 
                    :message, :metadata, 1, 'high', NOW())
        ");

        $metadata = [
            'amount' => (string) $amount,
            'verification_id' => $verificationId,
            'regulation' => 'FATF Recommendation 16',
            'deadline' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'support_reference' => 'SEC-VERIFY-' . $verificationId
        ];

        $stmt->execute([
            ':user_id' => $userId,
            ':message' => $message,
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)
        ]);

        Logger::info('Airdrop security notification sent', [
            'user_id' => $userId,
            'amount' => $amount,
            'verification_id' => $verificationId
        ]);
    }
}

