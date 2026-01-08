<?php

declare(strict_types=1);

/**
 * Settings Profile API endpoint for Ghidar
 * GET: Returns user profile information
 * PUT/POST: Updates user profile
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;

try {
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $wallet = $context['wallet'];
    $userId = (int) $user['id'];
    $pdo = Database::ensureConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user profile
        $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $userData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$userData) {
            Response::jsonError('USER_NOT_FOUND', 'User not found', 404);
            exit;
        }

        // Check wallet verification status
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM `wallet_verifications` WHERE `user_id` = :user_id AND `status` = "approved"');
        $stmt->execute(['user_id' => $userId]);
        $verificationCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
        $walletVerified = $verificationCount > 0;

        $profile = [
            'id' => (int) $userData['id'],
            'telegram_id' => (int) $userData['id'],
            'username' => $userData['username'] ?? null,
            'first_name' => $userData['first_name'] ?? null,
            'last_name' => $userData['last_name'] ?? null,
            'is_premium' => isset($userData['is_premium']) ? (bool) $userData['is_premium'] : false,
            'language_code' => $userData['language_code'] ?? 'en',
            'joining_date' => isset($userData['joining_date']) ? (int) $userData['joining_date'] : null,
            'display_name' => $userData['display_name'] ?? $userData['first_name'] ?? null,
            'wallet_verified' => $walletVerified,
            'wallet' => [
                'usdt_balance' => (string) $wallet['usdt_balance'],
                'ghd_balance' => (string) $wallet['ghd_balance'],
            ],
        ];

        Response::jsonSuccess($profile);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update user profile
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($data)) {
            Response::jsonError('INVALID_REQUEST', 'Invalid request body', 400);
            exit;
        }

        $allowedFields = ['display_name'];
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

        // Build update query
        $setParts = [];
        $params = ['id' => $userId];
        
        foreach ($updates as $field => $value) {
            $setParts[] = "`{$field}` = :{$field}";
            $params[$field] = $value;
        }

        $sql = 'UPDATE `users` SET ' . implode(', ', $setParts) . ' WHERE `id` = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Get updated user
        $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $userData = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Check wallet verification status
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM `wallet_verifications` WHERE `user_id` = :user_id AND `status` = "approved"');
        $stmt->execute(['user_id' => $userId]);
        $verificationCount = $stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;
        $walletVerified = $verificationCount > 0;

        $profile = [
            'id' => (int) $userData['id'],
            'telegram_id' => (int) $userData['id'],
            'username' => $userData['username'] ?? null,
            'first_name' => $userData['first_name'] ?? null,
            'last_name' => $userData['last_name'] ?? null,
            'is_premium' => isset($userData['is_premium']) ? (bool) $userData['is_premium'] : false,
            'language_code' => $userData['language_code'] ?? 'en',
            'joining_date' => isset($userData['joining_date']) ? (int) $userData['joining_date'] : null,
            'display_name' => $userData['display_name'] ?? $userData['first_name'] ?? null,
            'wallet_verified' => $walletVerified,
            'wallet' => [
                'usdt_balance' => (string) $wallet['usdt_balance'],
                'ghd_balance' => (string) $wallet['ghd_balance'],
            ],
        ];

        Response::jsonSuccess($profile);
    } else {
        Response::jsonError('METHOD_NOT_ALLOWED', 'Method not allowed', 405);
    }

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

