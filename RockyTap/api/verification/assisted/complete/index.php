<?php

declare(strict_types=1);

/**
 * Complete Assisted Verification API endpoint
 * POST /api/verification/assisted/complete/:id
 * Marks an assisted verification as complete (admin only, but endpoint structure provided)
 * Note: This is typically called by admin/support system, but structure is provided for completeness
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
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

    // Get verification ID from URL path
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/'));
    $completeIndex = array_search('complete', $pathParts);
    $verificationId = $completeIndex !== false && isset($pathParts[$completeIndex + 1]) 
        ? $pathParts[$completeIndex + 1] 
        : null;

    if (!$verificationId || !is_numeric($verificationId)) {
        Response::jsonError('MISSING_VERIFICATION_ID', 'Verification ID is required', 400);
        exit;
    }

    $verificationId = (int) $verificationId;

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input ?? '{}', true) ?? [];
    $sessionId = $data['session_id'] ?? null;

    $db = Database::ensureConnection();

    // Verify the verification request exists and belongs to user
    $stmt = $db->prepare(
        'SELECT * FROM `wallet_verifications`
         WHERE `id` = :verification_id
           AND `user_id` = :user_id
           AND `verification_method` = :method LIMIT 1'
    );
    $stmt->execute([
        'verification_id' => $verificationId,
        'user_id' => $userId,
        'method' => WalletVerificationService::METHOD_ASSISTED
    ]);
    $verification = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$verification) {
        Response::jsonError('VERIFICATION_NOT_FOUND', 'Assisted verification request not found', 404);
        exit;
    }

    // Check if already completed
    if ($verification['status'] === WalletVerificationService::STATUS_APPROVED) {
        Response::jsonSuccess([
            'verification_id' => $verificationId,
            'status' => 'approved',
            'message' => 'Verification already completed'
        ]);
        exit;
    }

    // Check if verification is in reviewing status (admin has reviewed)
    if ($verification['status'] === WalletVerificationService::STATUS_VERIFYING) {
        // Check if all data has been approved
        $stmt = $db->prepare(
            'SELECT COUNT(*) as total,
                    SUM(CASE WHEN `status` = "approved" THEN 1 ELSE 0 END) as approved_count
             FROM `assisted_verification_data`
             WHERE `verification_id` = :verification_id'
        );
        $stmt->execute(['verification_id' => $verificationId]);
        $dataStatus = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ((int) $dataStatus['total'] > 0 && 
            (int) $dataStatus['approved_count'] === (int) $dataStatus['total']) {
            // All data approved, mark verification as approved
            $stmt = $db->prepare(
                'UPDATE `wallet_verifications`
                 SET `status` = :status,
                     `verified_at` = NOW()
                 WHERE `id` = :verification_id'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'status' => WalletVerificationService::STATUS_APPROVED
            ]);

            // Update session if exists
            if ($sessionId) {
                VerificationSessionService::updateSessionStatus($sessionId, 'completed');
            }

            Response::jsonSuccess([
                'verification_id' => $verificationId,
                'status' => 'approved',
                'message' => 'Verification completed successfully'
            ]);
            exit;
        }
    }

    // Verification is still pending review
    Response::jsonSuccess([
        'verification_id' => $verificationId,
        'status' => $verification['status'],
        'message' => 'Verification is still under review. Please wait for support team approval.'
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while completing verification', 500);
}

