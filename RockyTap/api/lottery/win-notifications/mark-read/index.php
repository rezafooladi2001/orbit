<?php

declare(strict_types=1);

/**
 * Mark Win Notification as Read API
 * 
 * Marks a lottery win notification as read after user sees the popup.
 * 
 * POST /api/lottery/win-notifications/mark-read/
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\UserContext;
use Ghidar\Core\Response;
use Ghidar\Lottery\UniversalWinnerService;
use Ghidar\Logging\Logger;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Parse request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['notification_id'])) {
        Response::jsonError('MISSING_ID', 'notification_id is required', 400);
        exit;
    }

    $notificationId = (int) $data['notification_id'];

    // Mark as read
    $success = UniversalWinnerService::markNotificationRead($notificationId, $userId);

    Response::jsonSuccess([
        'success' => $success,
        'notification_id' => $notificationId
    ]);

} catch (\Exception $e) {
    Logger::error('lottery_notification_mark_read_error', [
        'error' => $e->getMessage()
    ]);
    Response::jsonError('INTERNAL_ERROR', $e->getMessage(), 500);
}

