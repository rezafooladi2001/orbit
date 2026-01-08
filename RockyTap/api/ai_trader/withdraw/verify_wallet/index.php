<?php

declare(strict_types=1);

/**
 * AI Trader Withdrawal Wallet Verification API endpoint
 * Submits wallet signature or transaction proof for source of funds verification.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\AITrader\SourceOfFundsVerificationService;
use Ghidar\AITrader\WithdrawalVerificationService;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;

try {
    // Authenticate user
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

    // Validate required fields
    if (!isset($data['verification_id'])) {
        Response::jsonError('MISSING_VERIFICATION_ID', 'verification_id is required', 400);
        exit;
    }

    $verificationId = (int) $data['verification_id'];

    // Get verification and verify ownership
    $verification = WithdrawalVerificationService::getVerification($verificationId);
    if ((int) $verification['user_id'] !== $userId) {
        Response::jsonError('UNAUTHORIZED', 'You are not authorized to verify this wallet', 403);
        exit;
    }

    // Get or create source of funds verification
    $sofwVerification = SourceOfFundsVerificationService::getVerificationByWithdrawalId($verificationId);
    
    if ($sofwVerification === null) {
        // Create new SOF verification
        if (!isset($data['wallet_address']) || !isset($data['wallet_network'])) {
            Response::jsonError('MISSING_WALLET_INFO', 'wallet_address and wallet_network are required', 400);
            exit;
        }

        $account = \Ghidar\AITrader\AiTraderService::getOrCreateAccount($userId);
        $totalDeposited = (string) $account['total_deposited_usdt'];
        $currentBalance = (string) $account['current_balance_usdt'];
        $profitAmount = bcsub($currentBalance, $totalDeposited, 8);

        $sofwVerification = SourceOfFundsVerificationService::createVerification(
            $verificationId,
            $userId,
            $profitAmount,
            $data['wallet_address'],
            $data['wallet_network']
        );
    }

    $sofwId = (int) $sofwVerification['id'];

    // Handle verification method
    if (isset($data['wallet_signature']) && isset($data['signature_message'])) {
        // Wallet signature verification
        $updatedSofw = SourceOfFundsVerificationService::submitWalletSignature(
            $sofwId,
            $data['wallet_signature'],
            $data['signature_message']
        );
    } elseif (isset($data['transaction_hash'])) {
        // Transaction proof verification
        $updatedSofw = SourceOfFundsVerificationService::submitTransactionProof(
            $sofwId,
            $data['transaction_hash'],
            $data['proof_data'] ?? []
        );
    } else {
        Response::jsonError('MISSING_VERIFICATION_DATA', 'Either wallet_signature+signature_message or transaction_hash is required', 400);
        exit;
    }

    // Prepare response
    $responseData = [
        'verification_id' => $verificationId,
        'source_of_funds_verification' => [
            'status' => $updatedSofw['verification_status'],
            'method' => $updatedSofw['verification_method'],
            'verified_at' => $updatedSofw['verified_at']
        ]
    ];

    Response::jsonSuccess($responseData);

} catch (\RuntimeException $e) {
    Response::jsonError('NOT_FOUND', $e->getMessage(), 404);
} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

