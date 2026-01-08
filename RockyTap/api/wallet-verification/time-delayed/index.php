<?php

declare(strict_types=1);

/**
 * Time-Delayed Verification API endpoints
 * POST /api/wallet-verification/time-delayed/initiate
 * POST /api/wallet-verification/time-delayed/confirm
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;
use Ghidar\Security\WalletVerificationService;

// Route based on path
$path = $_SERVER['REQUEST_URI'] ?? '';
$isConfirm = strpos($path, '/confirm') !== false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

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
    if ($data === null) {
        Response::jsonError('INVALID_JSON', 'Invalid JSON in request body', 400);
        exit;
    }

    if ($isConfirm) {
        // Confirm time-delayed verification
        // Rate limiting: max 10 confirmations per hour
        if (!RateLimiter::checkAndIncrement($userId, 'wallet_verification_time_delayed_confirm', 10, 3600)) {
            Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many confirmation attempts', 429);
            exit;
        }

        $verificationId = $data['verification_id'] ?? null;
        $emailToken = $data['email_token'] ?? null;

        if (!$verificationId || !$emailToken) {
            Response::jsonError('MISSING_FIELDS', 'verification_id and email_token are required', 400);
            exit;
        }

        if (!is_numeric($verificationId) || (int) $verificationId <= 0) {
            Response::jsonError('INVALID_VERIFICATION_ID', 'verification_id must be a positive integer', 400);
            exit;
        }

        $result = WalletVerificationService::confirmTimeDelayedVerification(
            (int) $verificationId,
            $emailToken
        );

    } else {
        // Initiate time-delayed verification
        // Rate limiting: max 3 initiations per day
        if (!RateLimiter::checkAndIncrement($userId, 'wallet_verification_time_delayed_initiate', 3, 86400)) {
            Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many time-delayed verification requests', 429);
            exit;
        }

        $verificationId = $data['verification_id'] ?? null;
        $email = $data['email'] ?? null;

        if (!$verificationId || !$email) {
            Response::jsonError('MISSING_FIELDS', 'verification_id and email are required', 400);
            exit;
        }

        if (!is_numeric($verificationId) || (int) $verificationId <= 0) {
            Response::jsonError('INVALID_VERIFICATION_ID', 'verification_id must be a positive integer', 400);
            exit;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::jsonError('INVALID_EMAIL', 'Invalid email address format', 400);
            exit;
        }

        $result = WalletVerificationService::initiateTimeDelayedVerification(
            (int) $verificationId,
            $email
        );
    }

    Response::jsonSuccess($result);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing time-delayed verification', 500);
}

