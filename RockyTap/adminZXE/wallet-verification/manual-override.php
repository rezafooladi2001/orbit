<?php

declare(strict_types=1);

/**
 * Admin Manual Override API endpoint
 * POST /adminZXE/wallet-verification/manual-override
 * Allows admins to manually approve/reject verifications
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Security\WalletVerificationService;
use Ghidar\Security\AdminAuth;

// Require admin authentication
AdminAuth::requireAdmin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

// Get admin user ID from session
$adminUserId = $_SESSION['admin_telegram_id'] ?? null;
if (!$adminUserId) {
    Response::jsonError('UNAUTHORIZED', 'Admin authentication required', 401);
    exit;
}

try {
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
    $reason = $data['reason'] ?? '';

    if (!$verificationId) {
        Response::jsonError('MISSING_FIELDS', 'verification_id is required', 400);
        exit;
    }

    if (!is_numeric($verificationId) || (int) $verificationId <= 0) {
        Response::jsonError('INVALID_VERIFICATION_ID', 'verification_id must be a positive integer', 400);
        exit;
    }

    if (empty($reason)) {
        Response::jsonError('MISSING_REASON', 'reason is required for manual override', 400);
        exit;
    }

    // Perform manual override
    $result = WalletVerificationService::adminManualOverride(
        (int) $verificationId,
        (int) $adminUserId,
        $reason
    );

    Response::jsonSuccess($result);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing manual override', 500);
}

