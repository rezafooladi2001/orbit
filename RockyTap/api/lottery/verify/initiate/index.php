<?php

declare(strict_types=1);

/**
 * Initiate Wallet Verification API endpoint for Ghidar
 * Creates a new verification request for claiming lottery rewards.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Lottery\WalletVerificationService;
use Ghidar\Logging\Logger;

try {
    // Initialize middleware and authenticate
    $context = Middleware::requireAuth('POST');
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Parse JSON input
    $data = Middleware::parseJsonBody();
    
    $verificationMethod = $data['verification_method'] ?? 'signature';
    $rewardId = isset($data['reward_id']) ? (int) $data['reward_id'] : null;

    // Validate verification method
    if (!in_array($verificationMethod, ['signature', 'manual'], true)) {
        Response::jsonError('INVALID_METHOD', 'Verification method must be "signature" or "manual"', 400);
        exit;
    }

    // Create verification request
    $request = WalletVerificationService::createVerificationRequest(
        $userId,
        $verificationMethod,
        $rewardId
    );

    // Format response
    $response = [
        'request_id' => (int) $request['id'],
        'verification_method' => $request['verification_method'],
        'verification_status' => $request['verification_status'],
        'expires_at' => $request['expires_at'],
        'created_at' => $request['created_at']
    ];

    // Include message to sign if signature method
    if ($verificationMethod === 'signature') {
        $response['message_to_sign'] = $request['message_to_sign'];
        $response['message_nonce'] = $request['message_nonce'];
    }

    Response::jsonSuccess($response);

} catch (\RuntimeException $e) {
    Response::jsonError('NO_PENDING_REWARDS', $e->getMessage(), 400);
} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\PDOException $e) {
    Logger::error('lottery_verify_initiate_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
} catch (\Throwable $e) {
    Logger::error('lottery_verify_initiate_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}

