<?php

declare(strict_types=1);

/**
 * Lottery Win Notifications API
 * 
 * Returns pending win notifications for the current user.
 * These trigger the congratulation popup in the app.
 * 
 * GET /api/lottery/win-notifications/
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\UserContext;
use Ghidar\Core\Response;
use Ghidar\Lottery\UniversalWinnerService;
use Ghidar\Logging\Logger;

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get pending win notifications
    $notifications = UniversalWinnerService::getPendingWinNotifications($userId);

    Response::jsonSuccess([
        'has_pending' => !empty($notifications),
        'notifications' => array_map(function($n) {
            return [
                'id' => (int) $n['id'],
                'lottery_id' => (int) $n['lottery_id'],
                'lottery_title' => $n['lottery_title'],
                'prize_amount_usdt' => (string) $n['prize_amount_usdt'],
                'winner_rank' => (int) $n['winner_rank'],
                'is_grand_prize' => (bool) $n['is_grand_prize'],
                'created_at' => $n['created_at']
            ];
        }, $notifications)
    ]);

} catch (\Exception $e) {
    Logger::error('lottery_notifications_error', [
        'error' => $e->getMessage()
    ]);
    Response::jsonError('INTERNAL_ERROR', $e->getMessage(), 500);
}

