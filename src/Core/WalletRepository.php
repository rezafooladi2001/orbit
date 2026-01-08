<?php

declare(strict_types=1);

namespace Ghidar\Core;

use PDO;
use PDOException;

/**
 * Repository for managing user wallets.
 * Handles CRUD operations for wallet balances (USDT and GHD).
 */
class WalletRepository
{
    /**
     * Get or create wallet for a user.
     * If wallet doesn't exist, creates one with zero balances.
     *
     * @param int $userId User ID (Telegram user ID)
     * @return array<string, mixed> Wallet record as associative array
     * @throws PDOException If database operation fails
     */
    public static function getOrCreateByUserId(int $userId): array
    {
        $db = Database::ensureConnection();

        // Try to get existing wallet
        $stmt = $db->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($wallet !== false) {
            return $wallet;
        }

        // Create new wallet with zero balances
        $stmt = $db->prepare(
            'INSERT INTO `wallets` (`user_id`, `usdt_balance`, `ghd_balance`) 
             VALUES (:user_id, 0, 0)'
        );
        $stmt->execute(['user_id' => $userId]);

        // Return the newly created wallet
        $stmt = $db->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($wallet === false) {
            throw new PDOException('Failed to create wallet for user: ' . $userId);
        }

        return $wallet;
    }

    /**
     * Find wallet by user ID.
     * Returns null if wallet doesn't exist.
     *
     * @param int $userId User ID (Telegram user ID)
     * @return array<string, mixed>|null Wallet record or null if not found
     * @throws PDOException If database operation fails
     */
    public static function findByUserId(int $userId): ?array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        return $wallet !== false ? $wallet : null;
    }
}

