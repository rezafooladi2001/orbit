<?php

declare(strict_types=1);

/**
 * Tapping Guru Booster API endpoint for Ghidar
 * Activates 5x tap multiplier for 20 seconds.
 * Users get 3 free tapping guru uses per day.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;

// Configuration
const TAPPING_GURU_PER_DAY = 3;
const TAPPING_GURU_RESET_PERIOD_MS = 24 * 60 * 60 * 1000; // 24 hours in milliseconds

try {
    // Authenticate user using Telegram initData
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: 20 requests per 60 seconds
    if (!RateLimiter::checkAndIncrement($userId, 'tapping_guru', 20, 60)) {
        Response::jsonErrorLegacy('rate_limit_exceeded', 'Too many requests', 429);
        exit;
    }

    $pdo = Database::ensureConnection();

    $pdo->beginTransaction();
    try {
        // Get user data
        $stmt = $pdo->prepare(
            'SELECT `id`, `tappingGuruLeft`, `tappingGuruNextTime`, `tappingGuruStarted` 
             FROM `users` WHERE `id` = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$get_user) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('user_not_found', 'User not found', 404);
            exit;
        }

        $tappingGuruLeft = (int) $get_user['tappingGuruLeft'];
        $tappingGuruNextTime = (float) ($get_user['tappingGuruNextTime'] ?? 0);
        
        // Current time in milliseconds
        $currentTimeMs = microtime(true) * 1000;

        // Check if tapping guru charges should be reset (new period)
        if ($currentTimeMs >= $tappingGuruNextTime) {
            // Reset charges for new period
            $tappingGuruLeft = TAPPING_GURU_PER_DAY;
            $tappingGuruNextTime = $currentTimeMs + TAPPING_GURU_RESET_PERIOD_MS;
        }

        // Check if user has tapping guru charges left
        if ($tappingGuruLeft <= 0) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('no_tapping_guru_left', 'No tapping guru attempts left', 400);
            exit;
        }

        // Use one tapping guru charge
        $tappingGuruLeft--;

        // Set the start time for tapping guru (used by tap endpoint to apply 5x multiplier)
        $tappingGuruStarted = $currentTimeMs;

        // Update user
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `tappingGuruStarted` = :tappingGuruStarted, 
                 `tappingGuruLeft` = :tappingGuruLeft, 
                 `tappingGuruNextTime` = :tappingGuruNextTime 
             WHERE `id` = :user_id LIMIT 1'
        );
        $stmt->execute([
            'tappingGuruStarted' => $tappingGuruStarted,
            'tappingGuruLeft' => $tappingGuruLeft,
            'tappingGuruNextTime' => $tappingGuruNextTime,
            'user_id' => $userId,
        ]);

        $pdo->commit();

        Response::jsonSuccessLegacy([
            'message' => 'Tapping guru activated',
            'tappingGuruLeft' => $tappingGuruLeft,
            'tappingGuruNextTime' => (int) $tappingGuruNextTime,
            'tappingGuruStarted' => (int) $tappingGuruStarted,
        ]);

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('TappingGuru endpoint error: ' . $e->getMessage());
        Response::jsonErrorLegacy('database_error', 'Database error occurred', 500);
    }

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('TappingGuru endpoint error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
