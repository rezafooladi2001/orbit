<?php

declare(strict_types=1);

/**
 * AI Trader Status API endpoint for Ghidar
 * Returns current AI Trader status and wallet overview for the authenticated user.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\AITrader\AiTraderService;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;

try {
    // Authenticate user and get wallet
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $wallet = $context['wallet'];
    $userId = (int) $user['id'];

    // Get or create AI Trader account
    $aiAccount = AiTraderService::getOrCreateAccount($userId);

    // Prepare user data
    $userData = [
        'id' => $userId,
        'telegram_id' => $userId,
        'username' => $user['username'] ?? null,
    ];

    // Prepare AI Trader summary from account data
    $aiTraderSummary = [
        'total_deposited_usdt' => (string) ($aiAccount['total_deposited_usdt'] ?? '0.00000000'),
        'current_balance_usdt' => (string) ($aiAccount['current_balance_usdt'] ?? '0.00000000'),
        'realized_pnl_usdt' => (string) ($aiAccount['realized_pnl_usdt'] ?? '0.00000000'),
    ];

    // Prepare response
    $responseData = [
        'user' => $userData,
        'wallet' => [
            'usdt_balance' => (string) $wallet['usdt_balance'],
            'ghd_balance' => (string) $wallet['ghd_balance']
        ],
        'ai_trader' => $aiTraderSummary
    ];

    Response::jsonSuccess($responseData);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', $e->getMessage(), 500);
}

