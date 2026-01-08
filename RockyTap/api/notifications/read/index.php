<?php

declare(strict_types=1);

/**
 * Mark Notification as Read API endpoint for Ghidar
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

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($data)) {
        Response::jsonError('INVALID_REQUEST', 'Invalid request body', 400);
        exit;
    }

    $notificationId = isset($data['notification_id']) ? (int) $data['notification_id'] : 0;

    if ($notificationId <= 0) {
        Response::jsonError('INVALID_REQUEST', 'Notification ID is required', 400);
        exit;
    }

    // Verify notification belongs to user
    $stmt = $pdo->prepare("SELECT * FROM `notifications` WHERE `id` = :id AND `user_id` = :user_id LIMIT 1");
    $stmt->execute([
        'id' => $notificationId,
        'user_id' => $userId,
    ]);

    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        Response::jsonError('NOTIFICATION_NOT_FOUND', 'Notification not found', 404);
        exit;
    }

    // Mark as read
    $updateStmt = $pdo->prepare("UPDATE `notifications` SET `read` = 1 WHERE `id` = :id AND `user_id` = :user_id");
    $updateStmt->execute([
        'id' => $notificationId,
        'user_id' => $userId,
    ]);

    Response::jsonSuccess([
        'message' => 'Notification marked as read',
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

