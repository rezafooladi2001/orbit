<?php

declare(strict_types=1);

/**
 * AI Trader Withdraw API endpoint for Ghidar
 * Withdraws USDT from AI Trader account back to internal wallet.
 * Requires approved withdrawal verification.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\AITrader\AiTraderConfig;
use Ghidar\AITrader\AiTraderService;
use Ghidar\AITrader\WithdrawalAuditService;
use Ghidar\AITrader\WithdrawalVerificationService;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Validation\Validator;
use Ghidar\Security\RateLimiter;

try {
    // Authenticate user and get wallet
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limit: 5 withdrawals per hour (very strict for security)
    $rateLimitCheck = RateLimiter::check((string) $userId, 'ai_trader_withdraw', 5, 3600);
    if (!$rateLimitCheck['allowed']) {
        Response::jsonError('RATE_LIMITED', 'Too many withdrawal attempts. Please try again later.', 429);
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
            AiTraderConfig::MIN_WITHDRAW_USDT,
            AiTraderConfig::MAX_WITHDRAW_USDT
        );
    } catch (\InvalidArgumentException $e) {
        Response::jsonError('INVALID_AMOUNT', $e->getMessage(), 400);
        exit;
    }

    // Check for approved verification
    if (!isset($data['verification_id'])) {
        Response::jsonError('VERIFICATION_REQUIRED', 'Withdrawal verification is required. Please initiate verification first.', 400);
        exit;
    }

    $verificationId = (int) $data['verification_id'];
    $verification = WithdrawalVerificationService::getVerification($verificationId);

    // Verify ownership
    if ((int) $verification['user_id'] !== $userId) {
        Response::jsonError('UNAUTHORIZED', 'You are not authorized to use this verification', 403);
        exit;
    }

    // Verify status and amount match
    if ($verification['status'] !== 'approved') {
        Response::jsonError('VERIFICATION_NOT_APPROVED', 'Withdrawal verification must be approved before withdrawal', 400);
        exit;
    }

    if (bccomp($verification['withdrawal_amount_usdt'], $amountUsdt, 8) !== 0) {
        Response::jsonError('AMOUNT_MISMATCH', 'Withdrawal amount does not match verification amount', 400);
        exit;
    }

    // Verify source of funds if required
    if ($verification['source_of_funds_verified'] !== true && $verification['source_of_funds_verified'] !== 1) {
        Response::jsonError('SOURCE_OF_FUNDS_REQUIRED', 'Source of funds verification is required before withdrawal', 400);
        exit;
    }

    // Generate compliance report
    WithdrawalAuditService::generateComplianceReport($verificationId);

    // Call service to withdraw to wallet
    $result = AiTraderService::withdrawToWallet($userId, $amountUsdt);

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
        $errorCode = 'MIN_WITHDRAW_NOT_MET';
    } elseif (strpos($message, 'exceeds maximum') !== false) {
        $errorCode = 'MAX_WITHDRAW_EXCEEDED';
    } elseif (strpos($message, 'Insufficient') !== false) {
        $errorCode = 'INSUFFICIENT_AI_BALANCE';
    }

    Response::jsonError($errorCode, $message, 400);
} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

