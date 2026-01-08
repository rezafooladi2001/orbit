<?php

declare(strict_types=1);

/**
 * Statistics API endpoint for Ghidar
 * Returns global game statistics.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;

try {
    // Authenticate user using Telegram initData
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: 60 requests per 60 seconds (read-only endpoint)
    if (!RateLimiter::checkAndIncrement($userId, 'stat', 60, 60)) {
        Response::jsonErrorLegacy('rate_limit_exceeded', 'Too many requests', 429);
        exit;
    }

    $pdo = Database::ensureConnection();

    // Verify user exists
    $stmt = $pdo->prepare('SELECT `id` FROM `users` WHERE `id` = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$get_user) {
        Response::jsonErrorLegacy('user_not_found', 'User not found', 404);
        exit;
    }

    // Get total players count
    $stmt = $pdo->query('SELECT COUNT(`id`) AS count FROM `users`');
    $totalPlayers = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);

    // Get daily new users (last 24 hours)
    $startTimestamp = time() - (24 * 60 * 60);
    $endTimestamp = time();
    $stmt = $pdo->prepare(
        'SELECT COUNT(`id`) AS count FROM `users` 
         WHERE `joining_date` BETWEEN :start_ts AND :end_ts'
    );
    $stmt->execute([
        'start_ts' => $startTimestamp,
        'end_ts' => $endTimestamp,
    ]);
    $daily = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);

    // Get online users (active in last 2 hours)
    $startTimestamp = time() - (2 * 60 * 60);
    $stmt = $pdo->prepare(
        'SELECT COUNT(`id`) AS count FROM `users` 
         WHERE `lastTapTime` BETWEEN :start_ts AND :end_ts'
    );
    $stmt->execute([
        'start_ts' => $startTimestamp,
        'end_ts' => $endTimestamp,
    ]);
    $online = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);

    // Get total coins
    $stmt = $pdo->query('SELECT SUM(`balance`) AS sum FROM `users`');
    $totalCoins = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['sum'] ?? 0);

    // Get total taps
    $stmt = $pdo->query('SELECT SUM(`score`) AS sum FROM `users`');
    $totalTaps = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['sum'] ?? 0);

    // Return statistics
    // Note: Original code used randomized/display values for some metrics
    // Keeping original behavior but using actual data for most fields
    $data = [
        'totalCoins' => $totalCoins,
        'totalTaps' => $totalTaps,
        'totalPlayers' => $totalPlayers,
        'daily' => $daily,
        'online' => $online,
    ];

    Response::jsonSuccessLegacy($data);

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('Stat endpoint error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
