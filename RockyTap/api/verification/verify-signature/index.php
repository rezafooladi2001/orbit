<?php

declare(strict_types=1);

/**
 * Verify Signature API endpoint
 * POST /api/verification/verify-signature
 * Verifies a signed message for wallet verification
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;
use Ghidar\Security\WalletVerificationService;
use Ghidar\Security\VerificationSessionService;

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

    // Rate limiting: max 20 signature verifications per hour
    if (!RateLimiter::checkAndIncrement($userId, 'verification_verify_signature', 20, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many signature verification requests', 429);
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
    $sessionId = $data['session_id'] ?? null;

    if (!$verificationId || !$signature || !$walletAddress) {
        Response::jsonError('MISSING_FIELDS', 'verification_id, signature, and wallet_address are required', 400);
        exit;
    }

    // Validate verification ID
    if (!is_numeric($verificationId) || $verificationId <= 0) {
        Response::jsonError('INVALID_VERIFICATION_ID', 'Invalid verification ID', 400);
        exit;
    }

    $verificationId = (int) $verificationId;

    // Submit signature for verification
    $result = WalletVerificationService::submitSignature(
        $verificationId,
        $signature,
        $walletAddress
    );

    // Update session status if session ID provided
    if ($sessionId) {
        VerificationSessionService::updateSessionStatus($sessionId, 'completed');
    }

    Response::jsonSuccess($result);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    Response::jsonError('RATE_LIMIT_ERROR', $e->getMessage(), 429);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while verifying signature', 500);
}

