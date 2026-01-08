<?php

declare(strict_types=1);

/**
 * Integration API endpoint for processing verified requests
 * Processes verified requests and routes them to appropriate business services
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Integration\VerificationIntegrationService;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Logging\Logger;

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    if ($input === false) {
        Response::jsonErrorLegacy('invalid_input', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null || !isset($data['verification_id'])) {
        Response::jsonErrorLegacy('invalid_input', 'verification_id is required', 400);
        exit;
    }

    $verificationId = (int) $data['verification_id'];

    // Verify user owns this verification
    $integrationService = new VerificationIntegrationService();
    $verificationData = $integrationService->getVerificationOwnership($verificationId, $userId);

    if (!$verificationData) {
        Response::jsonErrorLegacy('verification_not_found', 'Verification not found or access denied', 403);
        exit;
    }

    // Process the verified request
    $result = $integrationService->processVerifiedRequest($verificationId);

    Response::jsonSuccessLegacy([
        'success' => true,
        'data' => $result,
        'message' => 'Request processed successfully after verification',
        'next_steps' => [
            'Funds transferred to your account',
            'Transaction complete',
            'Check your balance for confirmation'
        ]
    ]);

} catch (\InvalidArgumentException $e) {
    Logger::warning('Integration processing validation error', [
        'error' => $e->getMessage(),
        'user_id' => $userId ?? null
    ]);
    Response::jsonErrorLegacy('validation_error', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    Logger::warning('Integration processing runtime error', [
        'error' => $e->getMessage(),
        'user_id' => $userId ?? null
    ]);
    Response::jsonErrorLegacy('processing_error', $e->getMessage(), 403);
} catch (\Exception $e) {
    Logger::error('Integration processing error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'user_id' => $userId ?? null
    ]);
    Response::jsonErrorLegacy('internal_error', 'An internal error occurred. Please try again.', 500);
}
