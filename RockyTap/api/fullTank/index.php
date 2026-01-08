<?php

declare(strict_types=1);

/**
 * Full Tank Booster API endpoint for Ghidar
 * Instantly refills user's energy to maximum.
 * Users get 3 free full tank uses per day.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;

// Configuration
const FULL_TANKS_PER_DAY = 3;
const FULL_TANK_RESET_PERIOD_MS = 24 * 60 * 60 * 1000; // 24 hours in milliseconds
const ENERGY_PER_LEVEL = 500;

try {
    // Authenticate user using Telegram initData
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: 20 requests per 60 seconds
    if (!RateLimiter::checkAndIncrement($userId, 'full_tank', 20, 60)) {
        Response::jsonErrorLegacy('rate_limit_exceeded', 'Too many requests', 429);
        exit;
    }

    $pdo = Database::ensureConnection();

    $pdo->beginTransaction();
    try {
        // Get user data
        $stmt = $pdo->prepare(
            'SELECT `id`, `fullTankLeft`, `fullTankNextTime`, `energyLimit` 
             FROM `users` WHERE `id` = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$get_user) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('user_not_found', 'User not found', 404);
            exit;
        }

        $fullTankLeft = (int) $get_user['fullTankLeft'];
        $fullTankNextTime = (float) ($get_user['fullTankNextTime'] ?? 0);
        $energyLimit = (int) $get_user['energyLimit'];
        
        // Current time in milliseconds
        $currentTimeMs = microtime(true) * 1000;

        // Check if full tank charges should be reset (new period)
        if ($currentTimeMs >= $fullTankNextTime) {
            // Reset charges for new period
            $fullTankLeft = FULL_TANKS_PER_DAY;
            $fullTankNextTime = $currentTimeMs + FULL_TANK_RESET_PERIOD_MS;
        }

        // Check if user has full tank charges left
        if ($fullTankLeft <= 0) {
            $pdo->rollBack();
            // Calculate remaining time until reset
            $remainingTime = max(0, $fullTankNextTime - $currentTimeMs);
            Response::jsonErrorLegacy('no_full_tank_left', 'No full tank attempts left', 400);
            exit;
        }

        // Use one full tank charge
        $fullTankLeft--;

        // Calculate max energy based on energy limit level
        $maxEnergy = $energyLimit * ENERGY_PER_LEVEL;

        // Update user: set energy to max and decrement full tank counter
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `fullTankLeft` = :fullTankLeft, 
                 `fullTankNextTime` = :fullTankNextTime, 
                 `energy` = :energy 
             WHERE `id` = :user_id LIMIT 1'
        );
        $stmt->execute([
            'fullTankLeft' => $fullTankLeft,
            'fullTankNextTime' => $fullTankNextTime,
            'energy' => $maxEnergy,
            'user_id' => $userId,
        ]);

        $pdo->commit();

        // Calculate remaining time until next reset
        $remainingTime = max(0, $fullTankNextTime - $currentTimeMs);

        Response::jsonSuccessLegacy([
            'fullTankLeft' => $fullTankLeft,
            'fullTankNextTime' => (int) $fullTankNextTime,
            'remainingTime' => (int) $remainingTime,
            'energy' => $maxEnergy,
        ]);

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('FullTank endpoint error: ' . $e->getMessage());
        Response::jsonErrorLegacy('database_error', 'Database error occurred', 500);
    }

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('FullTank endpoint error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
