<?php

declare(strict_types=1);

/**
 * Me API endpoint for Ghidar
 * Returns current authenticated user and wallet information.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;

try {
    // Get current user with wallet
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $wallet = $context['wallet'];

    // Prepare user data (only relevant fields)
    $userData = [
        'id' => (int) $user['id'],
        'telegram_id' => (int) $user['id'],
        'username' => $user['username'] ?? null,
        'first_name' => $user['first_name'] ?? null,
        'last_name' => $user['last_name'] ?? null,
        'is_premium' => isset($user['is_premium']) ? (bool) $user['is_premium'] : false,
        'language_code' => $user['language_code'] ?? 'en',
        'joining_date' => isset($user['joining_date']) ? (int) $user['joining_date'] : null,
    ];

    // Prepare wallet data
    $walletData = [
        'usdt_balance' => (string) $wallet['usdt_balance'],
        'ghd_balance' => (string) $wallet['ghd_balance'],
        'created_at' => $wallet['created_at'] ?? null,
        'updated_at' => $wallet['updated_at'] ?? null,
    ];

    // Return unified response
    Response::jsonSuccess([
        'user' => $userData,
        'wallet' => $walletData
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

