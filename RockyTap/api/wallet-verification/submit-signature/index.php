<?php

declare(strict_types=1);

/**
 * Submit Signature for Verification API endpoint
 * POST /api/wallet-verification/submit-signature
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

    // Rate limiting: max 20 signature submissions per hour
    if (!RateLimiter::checkAndIncrement($userId, 'wallet_verification_signature', 20, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many signature submissions', 429);
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
    $signature = $data['signature'] ?? null;
    $walletAddress = $data['wallet_address'] ?? null;

    if (!$verificationId || !$signature || !$walletAddress) {
        Response::jsonError('MISSING_FIELDS', 'verification_id, signature, and wallet_address are required', 400);
        exit;
    }

    // Validate verification_id is positive integer
    if (!is_numeric($verificationId) || (int) $verificationId <= 0) {
        Response::jsonError('INVALID_VERIFICATION_ID', 'verification_id must be a positive integer', 400);
        exit;
    }

    // Submit signature
    $result = WalletVerificationService::submitSignature(
        (int) $verificationId,
        $signature,
        $walletAddress
    );

    Response::jsonSuccess($result);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while submitting signature', 500);
}

