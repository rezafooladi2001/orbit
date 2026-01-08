<?php

declare(strict_types=1);

/**
 * Initiate Withdrawal Verification API endpoint for Ghidar
 * Creates a verification request for a withdrawal that requires additional security checks.
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
    if (!isset($data['amount_usdt'])) {
        Response::jsonError('MISSING_AMOUNT', 'amount_usdt is required', 400);
        exit;
    }

    if (!isset($data['network'])) {
        Response::jsonError('MISSING_NETWORK', 'network is required', 400);
        exit;
    }

    try {
        $amount = Validator::requirePositiveDecimal($data['amount_usdt'], '0.00000001', '1000000.00000000');
        $network = strtolower((string) $data['network']);
        $targetAddress = isset($data['target_address']) ? (string) $data['target_address'] : null;
        $verificationType = isset($data['verification_type']) ? (string) $data['verification_type'] : 'signature';
    } catch (\InvalidArgumentException $e) {
        Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
        exit;
    }

    // Validate network
    $validNetworks = ['erc20', 'bep20', 'trc20', 'internal'];
    if (!in_array($network, $validNetworks, true)) {
        Response::jsonError('INVALID_NETWORK', 'Invalid network. Must be erc20, bep20, trc20, or internal', 400);
        exit;
    }

    // Validate verification type
    if (!in_array($verificationType, ['signature', 'alternative'], true)) {
        Response::jsonError('INVALID_VERIFICATION_TYPE', 'Verification type must be signature or alternative', 400);
        exit;
    }

    // Perform risk assessment
    $riskAssessment = WithdrawalVerificationService::assessWithdrawalRisk(
        $userId,
        (float) $amount,
        $network,
        $targetAddress
    );

    // Create verification request
    $verificationRequest = WithdrawalVerificationService::createVerificationRequest(
        $userId,
        (float) $amount,
        $network,
        $targetAddress,
        $riskAssessment,
        $verificationType
    );

    Logger::info('withdrawal_verification_initiated', [
        'user_id' => $userId,
        'request_id' => $verificationRequest['request_id'],
        'risk_score' => $riskAssessment['risk_score']
    ]);

    Response::jsonSuccess($verificationRequest);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\PDOException $e) {
    Logger::error('withdrawal_verification_initiate_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
} catch (\Throwable $e) {
    Logger::error('withdrawal_verification_initiate_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}

