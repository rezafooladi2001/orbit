<?php

declare(strict_types=1);

/**
 * Get Assisted Verification Status API endpoint
 * GET /api/verification/assisted/status/:id
 * Gets the status of an assisted verification request
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use Ghidar\Security\WalletVerificationService;

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
    exit;
}

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get verification ID from URL path
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/'));
    $statusIndex = array_search('status', $pathParts);
    $verificationId = $statusIndex !== false && isset($pathParts[$statusIndex + 1]) 
        ? $pathParts[$statusIndex + 1] 
        : null;

    if (!$verificationId || !is_numeric($verificationId)) {
        Response::jsonError('MISSING_VERIFICATION_ID', 'Verification ID is required', 400);
        exit;
    }

    $verificationId = (int) $verificationId;

    $db = Database::ensureConnection();

    // Get verification request
    $stmt = $db->prepare(
        'SELECT `id`, `user_id`, `feature`, `wallet_address`, `wallet_network`,
                `verification_method`, `status`, `risk_level`, `created_at`, `expires_at`
         FROM `wallet_verifications`
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

    // Get assisted verification data status
    $stmt = $db->prepare(
        'SELECT `id`, `data_type`, `status`, `reviewed_by`, `reviewed_at`, `review_notes`, `created_at`
         FROM `assisted_verification_data`
         WHERE `verification_id` = :verification_id
         ORDER BY `created_at` DESC'
    );
    $stmt->execute(['verification_id' => $verificationId]);
    $dataRecords = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Get support ticket if exists
    $stmt = $db->prepare(
        'SELECT `ticket_id`, `status`, `assigned_to_admin_id`, `created_at`, `updated_at`
         FROM `wallet_verification_support_tickets`
         WHERE `verification_id` = :verification_id
         ORDER BY `created_at` DESC LIMIT 1'
    );
    $stmt->execute(['verification_id' => $verificationId]);
    $ticket = $stmt->fetch(\PDO::FETCH_ASSOC);

    Response::jsonSuccess([
        'verification_id' => $verification['id'],
        'status' => $verification['status'],
        'feature' => $verification['feature'],
        'wallet_address' => $verification['wallet_address'],
        'wallet_network' => $verification['wallet_network'],
        'risk_level' => $verification['risk_level'],
        'created_at' => $verification['created_at'],
        'expires_at' => $verification['expires_at'],
        'data_submissions' => array_map(function($record) {
            return [
                'id' => $record['id'],
                'data_type' => $record['data_type'],
                'status' => $record['status'],
                'reviewed_at' => $record['reviewed_at'],
                'review_notes' => $record['review_notes'],
                'created_at' => $record['created_at']
            ];
        }, $dataRecords),
        'support_ticket' => $ticket ? [
            'ticket_id' => $ticket['ticket_id'],
            'status' => $ticket['status'],
            'created_at' => $ticket['created_at'],
            'updated_at' => $ticket['updated_at']
        ] : null
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while fetching verification status', 500);
}

