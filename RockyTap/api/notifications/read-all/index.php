<?php

declare(strict_types=1);

/**
 * Mark All Notifications as Read API endpoint for Ghidar
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use PDO;

try {
    $context = UserContext::requireCurrentUser();
    $user = $context['user'];
    $userId = (int) $user['id'];
    $pdo = Database::ensureConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::jsonError('METHOD_NOT_ALLOWED', 'Method not allowed', 405);
        exit;
    }

    // Mark all notifications as read for this user
    $stmt = $pdo->prepare("UPDATE `notifications` SET `read` = 1 WHERE `user_id` = :user_id AND `read` = 0");
    $stmt->execute(['user_id' => $userId]);

    $affectedRows = $stmt->rowCount();

    Response::jsonSuccess([
        'message' => "Marked {$affectedRows} notification(s) as read",
        'count' => $affectedRows,
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

