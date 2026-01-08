<?php

declare(strict_types=1);

/**
 * Wallet Recovery Verification API endpoint for Ghidar
 * Verifies user's signed message and processes recovery request.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\CrossChainRecoveryService;
use Ghidar\Security\RateLimiter;

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: max 10 verification attempts per hour per user
    if (!RateLimiter::checkAndIncrement($userId, 'wallet_recovery_verify', 10, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many verification attempts, please try again later', 429);
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

    // Validate required fields
    if (!isset($data['request_id'])) {
        Response::jsonError('INVALID_REQUEST_ID', 'request_id is required', 400);
        exit;
    }
    if (!isset($data['signature'])) {
        Response::jsonError('INVALID_SIGNATURE', 'signature is required', 400);
        exit;
    }
    if (!isset($data['signed_message'])) {
        Response::jsonError('INVALID_MESSAGE', 'signed_message is required', 400);
        exit;
    }
    if (!isset($data['wallet_address'])) {
        Response::jsonError('INVALID_ADDRESS', 'wallet_address is required', 400);
        exit;
    }

    $requestId = (int) $data['request_id'];
    $signature = trim($data['signature']);
    $signedMessage = $data['signed_message'];
    $walletAddress = trim($data['wallet_address']);

    // Initialize recovery service
    $recoveryService = new CrossChainRecoveryService();

    // Verify the recovery request belongs to this user
    $request = $recoveryService->getRecoveryStatus($requestId, $userId);
    if ($request === null) {
        Response::jsonError('REQUEST_NOT_FOUND', 'Recovery request not found or does not belong to you', 404);
        exit;
    }

    if ($request['recovery_status'] !== 'requires_signature') {
        Response::jsonError('INVALID_STATUS', 'Recovery request is not in requires_signature status', 400);
        exit;
    }

    // Verify signature and process recovery
    $isValid = $recoveryService->verifySignatureAndProcess(
        $requestId,
        $signature,
        $signedMessage,
        $walletAddress
    );

    if ($isValid) {
        Response::jsonSuccess([
            'request_id' => $requestId,
            'status' => 'processing',
            'message' => 'Signature verified successfully. Your recovery request is being processed.',
            'wallet_address' => $walletAddress
        ]);
    } else {
        Response::jsonError('VERIFICATION_FAILED', 'Signature verification failed', 400);
    }

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    Response::jsonError('VERIFICATION_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

