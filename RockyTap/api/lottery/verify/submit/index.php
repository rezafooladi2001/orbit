<?php

declare(strict_types=1);

/**
 * Submit Wallet Verification Signature API endpoint for Ghidar
 * Submits a signed message to verify wallet ownership.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Lottery\WalletVerificationService;
use Ghidar\Logging\Logger;
use Ghidar\Validation\Validator;

try {
    // Initialize middleware and authenticate
    $context = Middleware::requireAuth('POST');
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Parse JSON input
    $data = Middleware::parseJsonBody();

    // Validate required fields
    if (!isset($data['signature']) || empty($data['signature'])) {
        Response::jsonError('MISSING_SIGNATURE', 'signature is required', 400);
        exit;
    }

    if (!isset($data['wallet_address']) || empty($data['wallet_address'])) {
        Response::jsonError('MISSING_WALLET_ADDRESS', 'wallet_address is required', 400);
        exit;
    }

    if (!isset($data['wallet_network']) || empty($data['wallet_network'])) {
        Response::jsonError('MISSING_WALLET_NETWORK', 'wallet_network is required', 400);
        exit;
    }

    // Validate network
    $validNetworks = ['ERC20', 'BEP20', 'TRC20'];
    if (!in_array(strtoupper($data['wallet_network']), $validNetworks, true)) {
        Response::jsonError('INVALID_NETWORK', 'wallet_network must be one of: ' . implode(', ', $validNetworks), 400);
        exit;
    }

    $signature = (string) $data['signature'];
    $walletAddress = (string) $data['wallet_address'];
    $walletNetwork = strtoupper((string) $data['wallet_network']);
    $requestId = isset($data['request_id']) ? (int) $data['request_id'] : null;

    // Submit signature for verification
    $result = WalletVerificationService::submitSignature(
        $userId,
        $signature,
        $walletAddress,
        $walletNetwork,
        $requestId
    );

    if ($result['success']) {
        Response::jsonSuccess([
            'status' => $result['status'],
            'message' => $result['message'],
            'request_id' => $result['request_id']
        ]);
    } else {
        Response::jsonError('VERIFICATION_FAILED', $result['message'], 400);
    }

} catch (\RuntimeException $e) {
    Response::jsonError('VERIFICATION_ERROR', $e->getMessage(), 400);
} catch (\PDOException $e) {
    Logger::error('lottery_verify_submit_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
} catch (\Throwable $e) {
    Logger::error('lottery_verify_submit_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}

