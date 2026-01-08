<?php

declare(strict_types=1);

/**
 * Multitap Upgrade API endpoint for Ghidar
 * Handles upgrading user's multitap level (max level: 20).
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;

// Price list for multitap upgrades (level 1-19 -> 2-20)
const MULTITAP_PRICES = [200, 500, 1000, 2000, 4000, 8000, 16000, 25000, 50000, 100000, 200000, 300000, 400000, 500000, 600000, 700000, 800000, 900000, 1000000];
const MULTITAP_MAX_LEVEL = 20;

try {
    // Authenticate user using Telegram initData
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: 20 requests per 60 seconds
    if (!RateLimiter::checkAndIncrement($userId, 'multitap', 20, 60)) {
        Response::jsonErrorLegacy('rate_limit_exceeded', 'Too many requests', 429);
        exit;
    }

    $pdo = Database::ensureConnection();

    $pdo->beginTransaction();
    try {
        // Get user data
        $stmt = $pdo->prepare('SELECT `id`, `multitap`, `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$get_user) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('user_not_found', 'User not found', 404);
            exit;
        }

        $multitap = (int) $get_user['multitap'];
        $currentBalance = (int) $get_user['balance'];

        // Check if already at max level
        if ($multitap >= MULTITAP_MAX_LEVEL) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('max_level_reached', 'Multitap is already at maximum level', 400);
            exit;
        }

        // Get upgrade cost (multitap is 1-based, array is 0-indexed)
        $upgradeCost = MULTITAP_PRICES[$multitap - 1];

        // Check if user has enough balance
        if ($currentBalance < $upgradeCost) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('insufficient_balance', 'Insufficient balance', 400);
            exit;
        }

        // Calculate new values
        $newBalance = $currentBalance - $upgradeCost;
        $newMultitap = $multitap + 1;

        // Ensure balance doesn't go negative (safety check)
        if ($newBalance < 0) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('insufficient_balance', 'Insufficient balance', 400);
            exit;
        }

        // Apply upgrade
        $stmt = $pdo->prepare(
            'UPDATE `users` SET `multitap` = :multitap, `balance` = :balance WHERE `id` = :user_id LIMIT 1'
        );
        $stmt->execute([
            'multitap' => $newMultitap,
            'balance' => $newBalance,
            'user_id' => $userId,
        ]);

        $pdo->commit();

        Response::jsonSuccessLegacy([
            'multitap' => $newMultitap,
            'balance' => $newBalance,
            'upgradeCost' => $upgradeCost,
        ]);

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Multitap endpoint error: ' . $e->getMessage());
        Response::jsonErrorLegacy('database_error', 'Database error occurred', 500);
    }

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('Multitap endpoint error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
