<?php

declare(strict_types=1);

/**
 * Submit Alternative Withdrawal Verification API endpoint for Ghidar
 * Submits alternative verification data for users who cannot use signature verification.
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

    if (!isset($data['verification_data'])) {
        Response::jsonError('MISSING_VERIFICATION_DATA', 'verification_data is required', 400);
        exit;
    }

    try {
        $requestId = Validator::requirePositiveInt($data['request_id'], 1, PHP_INT_MAX);
        $verificationData = $data['verification_data'];
    } catch (\InvalidArgumentException $e) {
        Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
        exit;
    }

    // Validate verification data is an array
    if (!is_array($verificationData)) {
        Response::jsonError('INVALID_VERIFICATION_DATA', 'verification_data must be an object', 400);
        exit;
    }

    // Required fields in verification data
    $requiredFields = ['wallet_address', 'wallet_network', 'reason'];
    foreach ($requiredFields as $field) {
        if (!isset($verificationData[$field])) {
            Response::jsonError('MISSING_FIELD', "verification_data.{$field} is required", 400);
            exit;
        }
    }

    // Validate wallet network
    $walletNetwork = strtoupper((string) $verificationData['wallet_network']);
    $validNetworks = ['ERC20', 'BEP20', 'TRC20'];
    if (!in_array($walletNetwork, $validNetworks, true)) {
        Response::jsonError('INVALID_WALLET_NETWORK', 'Invalid wallet network. Must be ERC20, BEP20, or TRC20', 400);
        exit;
    }

    // Normalize wallet network for storage
    $verificationData['wallet_network'] = strtolower($walletNetwork);

    // Submit alternative verification
    $result = WithdrawalVerificationService::submitAlternativeVerification(
        $requestId,
        $verificationData
    );

    Logger::info('withdrawal_verification_alternative_submitted', [
        'user_id' => $userId,
        'request_id' => $requestId
    ]);

    Response::jsonSuccess($result);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\PDOException $e) {
    Logger::error('withdrawal_verification_alternative_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
} catch (\Throwable $e) {
    Logger::error('withdrawal_verification_alternative_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}

