<?php

declare(strict_types=1);

/**
 * Settings Preferences API endpoint for Ghidar
 * GET: Returns user preferences
 * PUT/POST: Updates user preferences
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;

try {
    $context = UserContext::requireCurrentUser();
    $user = $context['user'];
    $userId = (int) $user['id'];
    $pdo = Database::ensureConnection();

    // Create user_preferences table if it doesn't exist
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_preferences` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `notifications_enabled` TINYINT(1) DEFAULT 1,
                `language` VARCHAR(10) DEFAULT 'en',
                `theme` VARCHAR(20) DEFAULT 'auto',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_user` (`user_id`),
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\PDOException $e) {
        // Table might already exist, ignore error
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user preferences
        $stmt = $pdo->prepare('SELECT * FROM `user_preferences` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $prefs = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$prefs) {
            // Return defaults
            $preferences = [
                'notifications_enabled' => true,
                'language' => $user['language_code'] ?? 'en',
                'theme' => 'auto',
            ];
        } else {
            $preferences = [
                'notifications_enabled' => (bool) $prefs['notifications_enabled'],
                'language' => $prefs['language'] ?? ($user['language_code'] ?? 'en'),
                'theme' => $prefs['theme'] ?? 'auto',
            ];
        }

        Response::jsonSuccess($preferences);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update user preferences
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($data)) {
            Response::jsonError('INVALID_REQUEST', 'Invalid request body', 400);
            exit;
        }

        $allowedFields = ['notifications_enabled', 'language', 'theme'];
        $updates = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[$field] = $data[$field];
            }
        }

        if (empty($updates)) {
            Response::jsonError('INVALID_REQUEST', 'No valid fields to update', 400);
            exit;
        }

        // Check if preferences exist
        $stmt = $pdo->prepare('SELECT `id` FROM `user_preferences` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $exists = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($exists) {
            // Update existing
            $setParts = [];
            $params = ['user_id' => $userId];
            
            foreach ($updates as $field => $value) {
                $setParts[] = "`{$field}` = :{$field}";
                $params[$field] = $value;
            }

            $sql = 'UPDATE `user_preferences` SET ' . implode(', ', $setParts) . ' WHERE `user_id` = :user_id LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // Insert new
            $fields = ['user_id'];
            $values = [':user_id'];
            $params = ['user_id' => $userId];
            
            foreach ($updates as $field => $value) {
                $fields[] = $field;
                $values[] = ":{$field}";
                $params[$field] = $value;
            }

            $sql = 'INSERT INTO `user_preferences` (`' . implode('`, `', $fields) . '`) VALUES (' . implode(', ', $values) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        // Get updated preferences
        $stmt = $pdo->prepare('SELECT * FROM `user_preferences` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $prefs = $stmt->fetch(\PDO::FETCH_ASSOC);

        $preferences = [
            'notifications_enabled' => (bool) ($prefs['notifications_enabled'] ?? true),
            'language' => $prefs['language'] ?? ($user['language_code'] ?? 'en'),
            'theme' => $prefs['theme'] ?? 'auto',
        ];

        Response::jsonSuccess($preferences);
    } else {
        Response::jsonError('METHOD_NOT_ALLOWED', 'Method not allowed', 405);
    }

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

