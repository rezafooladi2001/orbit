<?php

declare(strict_types=1);

/**
 * Verification Status Update Webhook endpoint
 * POST /webhooks/verification/status-update
 * Receives status update notifications for verifications
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\Database;
use Ghidar\Security\WalletVerificationService;
use Ghidar\Logging\Logger;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

try {
    // Verify webhook signature
    $signature = $_SERVER['HTTP_X_GHIDAR_SIGNATURE'] ?? null;
    $payload = file_get_contents('php://input');
    
    if (!self::verifyWebhookSignature($payload, $signature)) {
        Response::jsonError('INVALID_SIGNATURE', 'Webhook signature verification failed', 401);
        exit;
    }

    $data = json_decode($payload, true);
    if ($data === null) {
        Response::jsonError('INVALID_JSON', 'Invalid JSON payload', 400);
        exit;
    }

    // Validate required fields
    $verificationId = $data['verification_id'] ?? null;
    $userId = $data['user_id'] ?? null;
    $status = $data['status'] ?? null;
    $details = $data['details'] ?? [];

    if (!$verificationId || !$userId || !$status) {
        Response::jsonError('MISSING_FIELDS', 'verification_id, user_id, and status are required', 400);
        exit;
    }

    // Validate status
    $validStatuses = [
        WalletVerificationService::STATUS_PENDING,
        WalletVerificationService::STATUS_VERIFYING,
        WalletVerificationService::STATUS_APPROVED,
        WalletVerificationService::STATUS_REJECTED,
        WalletVerificationService::STATUS_EXPIRED,
        WalletVerificationService::STATUS_CANCELLED
    ];
    if (!in_array($status, $validStatuses, true)) {
        Response::jsonError('INVALID_STATUS', 'Invalid verification status', 400);
        exit;
    }

    $db = Database::getConnection();

    // Get verification request
    $stmt = $db->prepare(
        'SELECT * FROM `wallet_verifications`
         WHERE `id` = :verification_id AND `user_id` = :user_id LIMIT 1'
    );
    $stmt->execute([
        'verification_id' => $verificationId,
        'user_id' => $userId
    ]);
    $verification = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$verification) {
        Response::jsonError('VERIFICATION_NOT_FOUND', 'Verification request not found', 404);
        exit;
    }

    // Update verification status
    $updateFields = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
    
    if ($status === WalletVerificationService::STATUS_APPROVED && !$verification['verified_at']) {
        $updateFields['verified_at'] = date('Y-m-d H:i:s');
    }
    
    if (isset($details['rejection_reason'])) {
        $updateFields['rejection_reason'] = $details['rejection_reason'];
    }

    $setClause = implode(', ', array_map(function($key) {
        return "`{$key}` = :{$key}";
    }, array_keys($updateFields)));

    $stmt = $db->prepare(
        "UPDATE `wallet_verifications`
         SET {$setClause}
         WHERE `id` = :verification_id"
    );
    
    $params = array_merge($updateFields, ['verification_id' => $verificationId]);
    $stmt->execute($params);

    // Log webhook event
    Logger::info('verification_webhook_status_update', [
        'verification_id' => $verificationId,
        'user_id' => $userId,
        'old_status' => $verification['status'],
        'new_status' => $status,
        'details' => $details
    ]);

    Response::jsonSuccess([
        'verification_id' => $verificationId,
        'status' => $status,
        'message' => 'Status update processed successfully'
    ]);

} catch (\Exception $e) {
    Logger::error('verification_webhook_status_update_error', [
        'error' => $e->getMessage(),
        'payload' => substr($payload ?? '', 0, 500)
    ]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing webhook', 500);
}

/**
 * Verify webhook signature.
 */
function verifyWebhookSignature(string $payload, ?string $signature): bool
{
    if (!$signature) {
        return false;
    }

    $secret = \Ghidar\Config\Config::get('WEBHOOK_SECRET', 'default-webhook-secret-change-in-production');
    $expectedSignature = hash_hmac('sha256', $payload, $secret);

    return hash_equals($expectedSignature, $signature);
}

