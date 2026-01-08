<?php

declare(strict_types=1);

/**
 * Submit Assisted Verification API endpoint
 * POST /api/wallet-verification/assisted
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;
use Ghidar\Security\WalletVerificationService;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: max 5 assisted verification requests per day
    if (!RateLimiter::checkAndIncrement($userId, 'wallet_verification_assisted', 5, 86400)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many assisted verification requests', 429);
        exit;
    }

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    if ($input === false) {
        Response::jsonError('INVALID_INPUT', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null) {
        Response::jsonError('INVALID_JSON', 'Invalid JSON in request body', 400);
        exit;
    }

    // Validate required fields
    $verificationId = $data['verification_id'] ?? null;
    $verificationData = $data['verification_data'] ?? null;

    if (!$verificationId || !$verificationData || !is_array($verificationData)) {
        Response::jsonError('MISSING_FIELDS', 'verification_id and verification_data (object) are required', 400);
        exit;
    }

    // Validate verification_id
    if (!is_numeric($verificationId) || (int) $verificationId <= 0) {
        Response::jsonError('INVALID_VERIFICATION_ID', 'verification_id must be a positive integer', 400);
        exit;
    }

    // Submit assisted verification
    $result = WalletVerificationService::submitAssistedVerification(
        (int) $verificationId,
        $verificationData
    );

    Response::jsonSuccess($result);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while submitting assisted verification', 500);
}

