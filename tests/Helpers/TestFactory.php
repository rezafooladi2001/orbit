<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Ghidar\Core\Database;
use Ghidar\Core\WalletRepository;
use Ghidar\Lottery\LotteryConfig;
use PDO;

/**
 * Test factory for creating test data.
 * Provides helpers to create users, wallets, lotteries, etc.
 */
final class TestFactory
{
    /**
     * Create a test user with optional referrer.
     *
     * @param string $telegramId Telegram user ID (default: '1000')
     * @param int|null $inviterId Optional inviter user ID
     * @return array{id: int, telegram_id: string, wallet: array<string, mixed>}
     */
    public static function createUser(string $telegramId = '1000', ?int $inviterId = null): array
    {
        $pdo = Database::getConnection();

        // Insert user (id is the Telegram user ID, no separate telegram_id column)
        $stmt = $pdo->prepare(
            'INSERT INTO `users` (`id`, `inviter_id`, `joining_date`) 
             VALUES (:id, :inviter_id, UNIX_TIMESTAMP())'
        );
        $userId = (int) $telegramId;
        $stmt->execute([
            'id' => $userId,
            'inviter_id' => $inviterId,
        ]);

        // Get or create wallet
        $wallet = WalletRepository::getOrCreateByUserId($userId);

        return [
            'id' => $userId,
            'telegram_id' => $telegramId, // Return for compatibility, but it's the same as id
            'wallet' => $wallet,
        ];
    }

    /**
     * Create an active lottery.
     *
     * @param string $ticketPriceUsdt Ticket price in USDT
     * @param string $prizePoolUsdt Initial prize pool in USDT
     * @return array<string, mixed> Lottery record
     */
    public static function createActiveLottery(
        string $ticketPriceUsdt = '1.00000000',
        string $prizePoolUsdt = '0.00000000'
    ): array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'INSERT INTO `lotteries` 
             (`title`, `description`, `type`, `ticket_price_usdt`, `prize_pool_usdt`, `status`, `start_at`, `end_at`) 
             VALUES (:title, :description, :type, :ticket_price_usdt, :prize_pool_usdt, :status, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))'
        );
        $stmt->execute([
            'title' => 'Test Lottery',
            'description' => 'Test lottery for unit tests',
            'type' => 'regular',
            'ticket_price_usdt' => $ticketPriceUsdt,
            'prize_pool_usdt' => $prizePoolUsdt,
            'status' => LotteryConfig::STATUS_ACTIVE,
        ]);

        $lotteryId = (int) $pdo->lastInsertId();

        // Fetch and return the lottery
        $stmt = $pdo->prepare('SELECT * FROM `lotteries` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $lotteryId]);
        $lottery = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lottery === false) {
            throw new \RuntimeException('Failed to create lottery');
        }

        return $lottery;
    }

    /**
     * Create an AI Trader account for a user.
     *
     * @param int $userId User ID
     * @param string $initialBalance Initial balance in USDT
     * @return array<string, mixed> AI account record
     */
    public static function createAiAccount(int $userId, string $initialBalance = '0.00000000'): array
    {
        $pdo = Database::getConnection();

        // Check if account already exists
        $stmt = $pdo->prepare('SELECT * FROM `ai_accounts` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            // Update existing account
            $stmt = $pdo->prepare(
                'UPDATE `ai_accounts` 
                 SET `current_balance_usdt` = :balance, `total_deposited_usdt` = :balance 
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'balance' => $initialBalance,
                'user_id' => $userId,
            ]);
        } else {
            // Create new account
            $stmt = $pdo->prepare(
                'INSERT INTO `ai_accounts` (`user_id`, `total_deposited_usdt`, `current_balance_usdt`, `realized_pnl_usdt`) 
                 VALUES (:user_id, :total_deposited, :current_balance, 0)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'total_deposited' => $initialBalance,
                'current_balance' => $initialBalance,
            ]);
        }

        // Fetch and return the account
        $stmt = $pdo->prepare('SELECT * FROM `ai_accounts` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account === false) {
            throw new \RuntimeException('Failed to create AI account');
        }

        return $account;
    }

    /**
     * Set wallet balance for a user.
     *
     * @param int $userId User ID
     * @param string $usdtBalance USDT balance
     * @param string $ghdBalance GHD balance
     * @return array<string, mixed> Updated wallet
     */
    public static function setWalletBalance(
        int $userId,
        string $usdtBalance = '0.00000000',
        string $ghdBalance = '0.00000000'
    ): array {
        $wallet = WalletRepository::getOrCreateByUserId($userId);
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'UPDATE `wallets` 
             SET `usdt_balance` = :usdt_balance, `ghd_balance` = :ghd_balance 
             WHERE `user_id` = :user_id'
        );
        $stmt->execute([
            'usdt_balance' => $usdtBalance,
            'ghd_balance' => $ghdBalance,
            'user_id' => $userId,
        ]);

        // Fetch and return updated wallet
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($updatedWallet === false) {
            throw new \RuntimeException('Failed to update wallet');
        }

        return $updatedWallet;
    }

    /**
     * Create a pending deposit record.
     *
     * @param int $userId User ID
     * @param string $network Network (erc20, bep20, trc20)
     * @param string $productType Product type (wallet_topup, ai_trader, lottery_tickets)
     * @param string $expectedAmountUsdt Expected amount in USDT
     * @param array<string, mixed>|null $meta Optional metadata
     * @return array<string, mixed> Deposit record
     */
    public static function createPendingDeposit(
        int $userId,
        string $network = 'trc20',
        string $productType = 'wallet_topup',
        string $expectedAmountUsdt = '100.00000000',
        ?array $meta = null
    ): array {
        $pdo = Database::getConnection();

        $metaJson = $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare(
            'INSERT INTO `deposits` 
             (`user_id`, `network`, `product_type`, `status`, `address`, `expected_amount_usdt`, `meta`) 
             VALUES (:user_id, :network, :product_type, :status, :address, :expected_amount_usdt, :meta)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'network' => $network,
            'product_type' => $productType,
            'status' => 'pending',
            'address' => 'test_address_' . bin2hex(random_bytes(16)),
            'expected_amount_usdt' => $expectedAmountUsdt,
            'meta' => $metaJson,
        ]);

        $depositId = (int) $pdo->lastInsertId();

        // Fetch and return the deposit
        $stmt = $pdo->prepare('SELECT * FROM `deposits` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $depositId]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($deposit === false) {
            throw new \RuntimeException('Failed to create deposit');
        }

        return $deposit;
    }
}

