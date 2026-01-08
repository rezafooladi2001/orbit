<?php

declare(strict_types=1);

/**
 * Reject Verification API endpoint (Admin)
 * POST /admin/verification/reject/:id
 * Rejects a verification request with a reason
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
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
    $rejectIndex = array_search('reject', $pathParts);
    $verificationId = $rejectIndex !== false && isset($pathParts[$rejectIndex + 1]) 
        ? $pathParts[$rejectIndex + 1] 
        : null;

    if (!$verificationId || !is_numeric($verificationId)) {
        Response::jsonError('MISSING_VERIFICATION_ID', 'Verification ID is required', 400);
        exit;
    }

    $verificationId = (int) $verificationId;

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

    $reason = $data['reason'] ?? null;
    if (!$reason) {
        Response::jsonError('MISSING_REASON', 'Rejection reason is required', 400);
        exit;
    }

    $db = Database::getConnection();

    // Get verification request
    $stmt = $db->prepare(
        'SELECT * FROM `wallet_verifications`
         WHERE `id` = :verification_id LIMIT 1'
    );
    $stmt->execute(['verification_id' => $verificationId]);
    $verification = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$verification) {
        Response::jsonError('VERIFICATION_NOT_FOUND', 'Verification request not found', 404);
        exit;
    }

    // Update verification status
    $stmt = $db->prepare(
        'UPDATE `wallet_verifications`
         SET `status` = :status,
             `rejection_reason` = :reason,
             `admin_override_by` = :admin_id,
             `updated_at` = NOW()
         WHERE `id` = :verification_id'
    );
    $stmt->execute([
        'verification_id' => $verificationId,
        'status' => WalletVerificationService::STATUS_REJECTED,
        'reason' => $reason,
        'admin_id' => $adminUserId
    ]);

    // Create audit log
    $auditStmt = $db->prepare(
        'INSERT INTO `wallet_verification_audit_log`
        (`verification_id`, `user_id`, `action_type`, `action_details`, `ip_address`, `user_agent`)
        VALUES (:verification_id, :user_id, :action_type, :action_details, :ip_address, :user_agent)'
    );
    $auditStmt->execute([
        'verification_id' => $verificationId,
        'user_id' => $verification['user_id'],
        'action_type' => 'admin_rejected',
        'action_details' => json_encode([
            'admin_id' => $adminUserId,
            'reason' => $reason
        ], JSON_UNESCAPED_UNICODE),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    Logger::info('admin_verification_rejected', [
        'verification_id' => $verificationId,
        'admin_id' => $adminUserId,
        'reason' => $reason
    ]);

    Response::jsonSuccess([
        'verification_id' => $verificationId,
        'status' => 'rejected',
        'message' => 'Verification rejected successfully'
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while rejecting verification', 500);
}

