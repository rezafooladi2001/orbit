<?php

declare(strict_types=1);

/**
 * Submit Withdrawal Verification Signature API endpoint for Ghidar
 * Submits a signed message to verify wallet ownership for withdrawal.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Airdrop\WithdrawalVerificationService;
use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Validation\Validator;
use Ghidar\Logging\Logger;

try {
    // Authenticate user
    $context = Middleware::requireAuth('POST');
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Parse JSON input
    $data = Middleware::parseJsonBody();

    // Validate required fields
    if (!isset($data['request_id'])) {
        Response::jsonError('MISSING_REQUEST_ID', 'request_id is required', 400);
        exit;
    }

    if (!isset($data['signature'])) {
        Response::jsonError('MISSING_SIGNATURE', 'signature is required', 400);
        exit;
    }

    if (!isset($data['wallet_address'])) {
        Response::jsonError('MISSING_WALLET_ADDRESS', 'wallet_address is required', 400);
        exit;
    }

    if (!isset($data['wallet_network'])) {
        Response::jsonError('MISSING_WALLET_NETWORK', 'wallet_network is required', 400);
        exit;
    }

    try {
        $requestId = Validator::requirePositiveInt($data['request_id'], 1, PHP_INT_MAX);
        $signature = trim((string) $data['signature']);
        $walletAddress = trim((string) $data['wallet_address']);
        $walletNetwork = strtoupper((string) $data['wallet_network']);
    } catch (\InvalidArgumentException $e) {
        Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
        exit;
    }

    // Validate wallet network
    $validNetworks = ['ERC20', 'BEP20', 'TRC20'];
    if (!in_array($walletNetwork, $validNetworks, true)) {
        Response::jsonError('INVALID_WALLET_NETWORK', 'Invalid wallet network. Must be ERC20, BEP20, or TRC20', 400);
        exit;
    }

    // Validate signature format
    if (empty($signature) || strlen($signature) < 10) {
        Response::jsonError('INVALID_SIGNATURE_FORMAT', 'Invalid signature format', 400);
        exit;
    }

    // Submit signature
    $result = WithdrawalVerificationService::submitSignature(
        $requestId,
        $signature,
        $walletAddress,
        strtolower($walletNetwork)
    );

    Logger::info('withdrawal_verification_submitted', [
        'user_id' => $userId,
        'request_id' => $requestId
    ]);

    Response::jsonSuccess($result);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\PDOException $e) {
    Logger::error('withdrawal_verification_submit_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
} catch (\Throwable $e) {
    Logger::error('withdrawal_verification_submit_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}

