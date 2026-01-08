<?php

declare(strict_types=1);

/**
 * Verification Completed Webhook endpoint
 * POST /webhooks/verification/completed
 * Receives notifications when verification is completed by external services
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\Database;
use Ghidar\Security\WalletVerificationService;
use Ghidar\Security\WalletVerificationWebhookService;
use Ghidar\Logging\Logger;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

try {
    // Verify webhook signature for security
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
    $newStatus = $status === 'approved' ? WalletVerificationService::STATUS_APPROVED : $verification['status'];
    
    $stmt = $db->prepare(
        'UPDATE `wallet_verifications`
         SET `status` = :status,
             `verified_at` = NOW(),
             `updated_at` = NOW()
         WHERE `id` = :verification_id'
    );
    $stmt->execute([
        'verification_id' => $verificationId,
        'status' => $newStatus
    ]);

    // Log webhook event
    Logger::info('verification_webhook_completed', [
        'verification_id' => $verificationId,
        'user_id' => $userId,
        'status' => $status,
        'details' => $details
    ]);

    // Trigger internal webhook if needed
    WalletVerificationWebhookService::queueWebhook(
        $verificationId,
        $userId,
        'verification_completed',
        array_merge($details, ['webhook_source' => 'external'])
    );

    Response::jsonSuccess([
        'verification_id' => $verificationId,
        'status' => $newStatus,
        'message' => 'Webhook processed successfully'
    ]);

} catch (\Exception $e) {
    Logger::error('verification_webhook_error', [
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

