<?php

declare(strict_types=1);

/**
 * Recharging Speed Upgrade API endpoint for Ghidar
 * Handles upgrading user's recharging speed (max level: 5).
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;

// Price list for recharging speed upgrades (level 1-4 -> 2-5)
// Note: Original has 8 prices but max level is 5, keeping original prices array
const RECHARGING_SPEED_PRICES = [2000, 10000, 100000, 250000, 500000, 1000000, 1250000, 1500000];
const RECHARGING_SPEED_MAX_LEVEL = 5;

try {
    // Authenticate user using Telegram initData
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: 20 requests per 60 seconds
    if (!RateLimiter::checkAndIncrement($userId, 'recharging_speed', 20, 60)) {
        Response::jsonErrorLegacy('rate_limit_exceeded', 'Too many requests', 429);
        exit;
    }

    $pdo = Database::ensureConnection();

    $pdo->beginTransaction();
    try {
        // Get user data
        $stmt = $pdo->prepare('SELECT `id`, `rechargingSpeed`, `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$get_user) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('user_not_found', 'User not found', 404);
            exit;
        }

        $rechargingSpeed = (int) $get_user['rechargingSpeed'];
        $currentBalance = (int) $get_user['balance'];

        // Check if already at max level
        if ($rechargingSpeed >= RECHARGING_SPEED_MAX_LEVEL) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('max_level_reached', 'Recharging speed is already at maximum level', 400);
            exit;
        }

        // Get upgrade cost (rechargingSpeed is 1-based, array is 0-indexed)
        $upgradeCost = RECHARGING_SPEED_PRICES[$rechargingSpeed - 1];

        // Check if user has enough balance
        if ($currentBalance < $upgradeCost) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('insufficient_balance', 'Insufficient balance', 400);
            exit;
        }

        // Calculate new values
        $newBalance = $currentBalance - $upgradeCost;
        $newRechargingSpeed = $rechargingSpeed + 1;

        // Ensure balance doesn't go negative (safety check)
        if ($newBalance < 0) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('insufficient_balance', 'Insufficient balance', 400);
            exit;
        }

        // Apply upgrade
        $stmt = $pdo->prepare(
            'UPDATE `users` SET `rechargingSpeed` = :rechargingSpeed, `balance` = :balance WHERE `id` = :user_id LIMIT 1'
        );
        $stmt->execute([
            'rechargingSpeed' => $newRechargingSpeed,
            'balance' => $newBalance,
            'user_id' => $userId,
        ]);

        $pdo->commit();

        Response::jsonSuccessLegacy([
            'rechargingSpeed' => $newRechargingSpeed,
            'balance' => $newBalance,
            'upgradeCost' => $upgradeCost,
        ]);

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('RechargingSpeed endpoint error: ' . $e->getMessage());
        Response::jsonErrorLegacy('database_error', 'Database error occurred', 500);
    }

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('RechargingSpeed endpoint error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
