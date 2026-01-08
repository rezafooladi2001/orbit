<?php

declare(strict_types=1);

/**
 * Notifications API endpoint for Ghidar
 * Returns user notifications with optional filtering
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use PDO;

try {
    $context = UserContext::requireCurrentUser();
    $user = $context['user'];
    $userId = (int) $user['id'];
    $pdo = Database::ensureConnection();

    // Create notifications table if it doesn't exist
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `message` TEXT NOT NULL,
                `type` VARCHAR(64) NOT NULL DEFAULT 'info',
                `read` TINYINT(1) DEFAULT 0,
                `metadata` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_user_id` (`user_id`),
                KEY `idx_read` (`read`),
                KEY `idx_created_at` (`created_at`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\PDOException $e) {
        // Table might already exist, ignore error
    }

    $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    $query = "SELECT * FROM `notifications` WHERE `user_id` = :user_id";
    $params = ['user_id' => $userId];

    if ($unreadOnly) {
        $query .= " AND `read` = 0";
    }

    $query .= " ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unread count
    $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM `notifications` WHERE `user_id` = :user_id AND `read` = 0");
    $countStmt->execute(['user_id' => $userId]);
    $unreadCount = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Format notifications
    $formattedNotifications = [];
    foreach ($notifications as $notification) {
        $formattedNotifications[] = [
            'id' => (int) $notification['id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'read' => (bool) $notification['read'],
            'created_at' => $notification['created_at'],
            'metadata' => $notification['metadata'] ? json_decode($notification['metadata'], true) : null,
        ];
    }

    Response::jsonSuccess([
        'notifications' => $formattedNotifications,
        'unread_count' => $unreadCount,
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

