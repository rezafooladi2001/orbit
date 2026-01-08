<?php

declare(strict_types=1);

/**
 * Approve Verification API endpoint (Admin)
 * POST /admin/verification/approve/:id
 * Manually approves a verification request
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\WalletVerificationService;
use Ghidar\Logging\Logger;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

try {
    // Authenticate user and require admin access
    $context = UserContext::requireAdmin();
    $user = $context['user'];
    $adminUserId = (int) $user['id'];

    // Get verification ID from URL path
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/'));
    $approveIndex = array_search('approve', $pathParts);
    $verificationId = $approveIndex !== false && isset($pathParts[$approveIndex + 1]) 
        ? $pathParts[$approveIndex + 1] 
        : null;

    if (!$verificationId || !is_numeric($verificationId)) {
        Response::jsonError('MISSING_VERIFICATION_ID', 'Verification ID is required', 400);
        exit;
    }

    $verificationId = (int) $verificationId;

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input ?? '{}', true) ?? [];
    $reason = $data['reason'] ?? 'Manually approved by admin';

    // Approve verification
    $result = WalletVerificationService::adminManualOverride(
        $verificationId,
        $adminUserId,
        $reason
    );

    Logger::info('admin_verification_approved', [
        'verification_id' => $verificationId,
        'admin_id' => $adminUserId,
        'reason' => $reason
    ]);

    Response::jsonSuccess($result);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while approving verification', 500);
}

