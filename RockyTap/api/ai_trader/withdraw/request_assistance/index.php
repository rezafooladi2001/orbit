<?php

declare(strict_types=1);

/**
 * AI Trader Withdrawal Assisted Verification Request API endpoint
 * Requests premium customer support for wallet verification issues.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\AITrader\AssistedVerificationService;
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

    if (!isset($data['reason']) || empty(trim($data['reason']))) {
        Response::jsonError('MISSING_REASON', 'reason is required', 400);
        exit;
    }

    $verificationId = (int) $data['verification_id'];
    $reason = trim($data['reason']);

    // Get verification and verify ownership
    $verification = WithdrawalVerificationService::getVerification($verificationId);
    if ((int) $verification['user_id'] !== $userId) {
        Response::jsonError('UNAUTHORIZED', 'You are not authorized to request assistance for this verification', 403);
        exit;
    }

    // Check if already has assisted verification
    $existingAssistance = AssistedVerificationService::getAssistedVerificationByVerificationId($verificationId);
    if ($existingAssistance !== null) {
        Response::jsonError('ASSISTANCE_ALREADY_REQUESTED', 'Assisted verification has already been requested', 400);
        exit;
    }

    // Get user-provided info if available
    $userInfo = $data['user_info'] ?? null;

    // Request assistance
    $assistedVerification = AssistedVerificationService::requestAssistance(
        $verificationId,
        $userId,
        $reason,
        $userInfo
    );

    // Prepare response
    $responseData = [
        'assisted_verification_id' => (int) $assistedVerification['id'],
        'support_ticket_id' => $assistedVerification['support_ticket_id'],
        'status' => $assistedVerification['status'],
        'message' => 'Your assistance request has been submitted. Our support team will contact you shortly.'
    ];

    Response::jsonSuccess($responseData);

} catch (\RuntimeException $e) {
    Response::jsonError('NOT_FOUND', $e->getMessage(), 404);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

