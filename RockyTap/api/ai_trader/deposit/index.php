<?php

declare(strict_types=1);

/**
 * AI Trader Deposit API endpoint for Ghidar
 * Deposits USDT from internal wallet into AI Trader account.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\AITrader\AiTraderConfig;
use Ghidar\AITrader\AiTraderService;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Validation\Validator;
use Ghidar\Security\RateLimiter;

try {
    // Authenticate user and get wallet
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limit: 10 deposits per hour (stricter limit for financial operations)
    $rateLimitCheck = RateLimiter::check((string) $userId, 'ai_trader_deposit', 10, 3600);
    if (!$rateLimitCheck['allowed']) {
        Response::jsonError('RATE_LIMITED', 'Too many deposit attempts. Please try again later.', 429);
        exit;
    }

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    if ($input === false) {
        Response::jsonError('INVALID_INPUT', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
        Response::jsonError('INVALID_JSON', 'Invalid JSON in request body', 400);
        exit;
    }

    // Validate amount_usdt
    if (!isset($data['amount_usdt'])) {
        Response::jsonError('MISSING_AMOUNT', 'amount_usdt is required', 400);
        exit;
    }
    try {
        $amountUsdt = Validator::requirePositiveDecimal(
            $data['amount_usdt'],
            AiTraderConfig::MIN_DEPOSIT_USDT,
            AiTraderConfig::MAX_DEPOSIT_USDT
        );
    } catch (\InvalidArgumentException $e) {
        Response::jsonError('INVALID_AMOUNT', $e->getMessage(), 400);
        exit;
    }

    // Call service to deposit from wallet
    $result = AiTraderService::depositFromWallet($userId, $amountUsdt);

    // Prepare response
    $wallet = $result['wallet'];
    $aiAccount = $result['ai_account'];
    $responseData = [
        'amount_usdt' => number_format((float) $amountUsdt, 8, '.', ''),
        'wallet' => [
            'usdt_balance' => (string) $wallet['usdt_balance'],
            'ghd_balance' => (string) $wallet['ghd_balance']
        ],
        'ai_trader' => [
            'total_deposited_usdt' => (string) $aiAccount['total_deposited_usdt'],
            'current_balance_usdt' => (string) $aiAccount['current_balance_usdt'],
            'realized_pnl_usdt' => (string) $aiAccount['realized_pnl_usdt']
        ]
    ];

    Response::jsonSuccess($responseData);

} catch (\InvalidArgumentException $e) {
    $errorCode = 'VALIDATION_ERROR';
    $message = $e->getMessage();

    // Map specific error messages to error codes
    if (strpos($message, 'at least') !== false) {
        $errorCode = 'MIN_DEPOSIT_NOT_MET';
    } elseif (strpos($message, 'exceeds maximum') !== false) {
        $errorCode = 'MAX_DEPOSIT_EXCEEDED';
    } elseif (strpos($message, 'Insufficient') !== false) {
        $errorCode = 'INSUFFICIENT_BALANCE';
    }

    Response::jsonError($errorCode, $message, 400);
} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

