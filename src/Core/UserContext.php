<?php

declare(strict_types=1);

namespace Ghidar\Core;

use Ghidar\Auth\TelegramAuth;
use Ghidar\Config\Config;

/**
 * User context helper that combines user authentication and wallet data.
 * Provides a unified way to get the current authenticated user with their wallet.
 */
class UserContext
{
    /**
     * Require current authenticated user with their wallet.
     * Validates Telegram authentication and returns both user and wallet data.
     *
     * @return array<string, array<string, mixed>> Array with 'user' and 'wallet' keys
     * @throws \RuntimeException If authentication fails or user is banned
     */
    public static function requireCurrentUserWithWallet(): array
    {
        // Get authenticated user from Telegram initData
        $user = TelegramAuth::requireUserFromRequest();

        // Check if user is banned
        if (isset($user['step']) && $user['step'] === 'banned') {
            throw new \RuntimeException('User is banned');
        }

        // Get or create wallet for user
        $userId = (int) $user['id'];
        $wallet = WalletRepository::getOrCreateByUserId($userId);

        return [
            'user' => $user,
            'wallet' => $wallet
        ];
    }

    /**
     * Check if a user is an admin.
     * Reads admin user IDs from environment configuration.
     *
     * @param int $userId User ID to check
     * @return bool True if user is admin, false otherwise
     */
    public static function isAdmin(int $userId): bool
    {
        // Try ADMIN_TELEGRAM_IDS first, then fallback to ADMINS_USER_ID
        $adminIds = Config::getIntArray('ADMIN_TELEGRAM_IDS', []);
        if (empty($adminIds)) {
            $adminIds = Config::getIntArray('ADMINS_USER_ID', []);
        }
        
        return in_array($userId, $adminIds, true);
    }

    /**
     * Require current authenticated user to be an admin.
     * Throws exception if user is not authenticated or not an admin.
     *
     * @return array<string, array<string, mixed>> Array with 'user' and 'wallet' keys
     * @throws \RuntimeException If authentication fails, user is banned, or user is not admin
     */
    public static function requireAdmin(): array
    {
        $context = self::requireCurrentUserWithWallet();
        $userId = (int) $context['user']['id'];
        
        if (!self::isAdmin($userId)) {
            throw new \RuntimeException('Admin access required');
        }
        
        return $context;
    }
}

