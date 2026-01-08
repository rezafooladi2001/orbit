<?php

declare(strict_types=1);

/**
 * Support Ticket Creation API endpoint for Ghidar
 * Creates a new support ticket
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use PDO;

try {
    $context = UserContext::requireCurrentUser();
    $user = $context['user'];
    $userId = (int) $user['id'];
    $pdo = Database::ensureConnection();

    // Create support_tickets table if it doesn't exist
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `support_tickets` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `message` TEXT NOT NULL,
                `status` VARCHAR(32) DEFAULT 'open',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_user_id` (`user_id`),
                KEY `idx_status` (`status`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\PDOException $e) {
        // Table might already exist, ignore error
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::jsonError('METHOD_NOT_ALLOWED', 'Method not allowed', 405);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($data)) {
        Response::jsonError('INVALID_REQUEST', 'Invalid request body', 400);
        exit;
    }

    $subject = trim($data['subject'] ?? '');
    $message = trim($data['message'] ?? '');

    if (empty($subject) || empty($message)) {
        Response::jsonError('INVALID_REQUEST', 'Subject and message are required', 400);
        exit;
    }

    if (strlen($subject) > 255) {
        Response::jsonError('INVALID_REQUEST', 'Subject is too long (max 255 characters)', 400);
        exit;
    }

    // Create ticket
    $stmt = $pdo->prepare("
        INSERT INTO `support_tickets` (`user_id`, `subject`, `message`, `status`)
        VALUES (:user_id, :subject, :message, 'open')
    ");
    
    $stmt->execute([
        'user_id' => $userId,
        'subject' => $subject,
        'message' => $message,
    ]);

    $ticketId = (int) $pdo->lastInsertId();

    Response::jsonSuccess([
        'ticket_id' => $ticketId,
        'status' => 'open',
        'message' => 'Support ticket created successfully. We will respond within 24-48 hours.',
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

