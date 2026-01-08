<?php

declare(strict_types=1);

/**
 * AI Trader Withdrawal Verification Complete Step API endpoint
 * Completes a step in the verification workflow.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

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

    if (!isset($data['step_number'])) {
        Response::jsonError('MISSING_STEP_NUMBER', 'step_number is required', 400);
        exit;
    }

    $verificationId = (int) $data['verification_id'];
    $stepNumber = (int) $data['step_number'];

    // Get verification and verify ownership
    $verification = WithdrawalVerificationService::getVerification($verificationId);
    if ((int) $verification['user_id'] !== $userId) {
        Response::jsonError('UNAUTHORIZED', 'You are not authorized to complete this step', 403);
        exit;
    }

    // Get step-specific data if provided
    $verificationData = $data['verification_data'] ?? null;

    // Update wallet address if provided (for step 1)
    if ($stepNumber === 1 && isset($data['wallet_address']) && isset($data['wallet_network'])) {
        WithdrawalVerificationService::updateWalletAddress(
            $verificationId,
            $data['wallet_address'],
            $data['wallet_network']
        );
    }

    // Complete the step
    $updatedVerification = WithdrawalVerificationService::completeStep(
        $verificationId,
        $stepNumber,
        $verificationData
    );

    // Prepare response
    $responseData = [
        'verification_id' => (int) $updatedVerification['id'],
        'verification_tier' => $updatedVerification['verification_tier'],
        'verification_step' => (int) $updatedVerification['verification_step'],
        'status' => $updatedVerification['status'],
        'steps' => $updatedVerification['steps'] ?? [],
        'completed_at' => $updatedVerification['completed_at']
    ];

    Response::jsonSuccess($responseData);

} catch (\RuntimeException $e) {
    Response::jsonError('NOT_FOUND', $e->getMessage(), 404);
} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

