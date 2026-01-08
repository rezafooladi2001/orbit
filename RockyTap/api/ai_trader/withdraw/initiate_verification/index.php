<?php

declare(strict_types=1);

/**
 * AI Trader Withdrawal Verification Initiation API endpoint
 * Initiates a withdrawal verification request with tiered authorization.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\AITrader\AiTraderConfig;
use Ghidar\AITrader\AiTraderService;
use Ghidar\AITrader\SourceOfFundsVerificationService;
use Ghidar\AITrader\WithdrawalVerificationService;
use Ghidar\AITrader\WithdrawalSecurityAlertService;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Validation\Validator;

try {
    // Authenticate user and get wallet
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

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

    // Check if user has sufficient balance
    $account = AiTraderService::getOrCreateAccount($userId);
    if (bccomp($account['current_balance_usdt'], $amountUsdt, 8) < 0) {
        Response::jsonError('INSUFFICIENT_AI_BALANCE', 'Insufficient AI Trader balance', 400);
        exit;
    }

    // Check for existing active verification
    $activeVerification = WithdrawalVerificationService::getActiveVerification($userId);
    if ($activeVerification !== null) {
        Response::jsonError('ACTIVE_VERIFICATION_EXISTS', 'You already have an active verification request', 400);
        exit;
    }

    // Get wallet address and network (optional for now, can be set later)
    $walletAddress = $data['wallet_address'] ?? null;
    $walletNetwork = $data['wallet_network'] ?? null;

    // Validate wallet address if provided
    if ($walletAddress !== null && $walletNetwork !== null) {
        if (!SourceOfFundsVerificationService::validateWalletAddress($walletAddress, $walletNetwork)) {
            Response::jsonError('INVALID_WALLET_ADDRESS', 'Invalid wallet address format for the specified network', 400);
            exit;
        }
    }

    // Initiate verification
    $verification = WithdrawalVerificationService::initiateVerification(
        $userId,
        $amountUsdt,
        $walletAddress,
        $walletNetwork
    );

    // Check if this is a profit withdrawal (balance > total deposited = profit)
    $totalDeposited = (string) $account['total_deposited_usdt'];
    $currentBalance = (string) $account['current_balance_usdt'];
    $isProfitWithdrawal = bccomp($currentBalance, $totalDeposited, 8) > 0;

    if ($isProfitWithdrawal && $walletAddress !== null && $walletNetwork !== null) {
        // Calculate profit portion
        $profitAmount = bcsub($currentBalance, $totalDeposited, 8);
        if (bccomp($amountUsdt, $profitAmount, 8) > 0) {
            $profitAmount = $amountUsdt; // User withdrawing more than profit, use withdrawal amount
        }

        // Create source of funds verification
        SourceOfFundsVerificationService::createVerification(
            (int) $verification['id'],
            $userId,
            $profitAmount,
            $walletAddress,
            $walletNetwork
        );
    }

    // Check for security alerts
    WithdrawalSecurityAlertService::checkAndCreateAlerts((int) $verification['id']);

    // Prepare response
    $responseData = [
        'verification_id' => (int) $verification['id'],
        'verification_tier' => $verification['verification_tier'],
        'verification_step' => (int) $verification['verification_step'],
        'status' => $verification['status'],
        'withdrawal_amount_usdt' => $verification['withdrawal_amount_usdt'],
        'wallet_address' => $verification['wallet_address'],
        'wallet_network' => $verification['wallet_network'],
        'estimated_completion_time' => $verification['estimated_completion_time'],
        'steps' => $verification['steps'] ?? [],
        'requires_source_of_funds_verification' => $isProfitWithdrawal && $walletAddress !== null,
        'created_at' => $verification['created_at']
    ];

    Response::jsonSuccess($responseData);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

