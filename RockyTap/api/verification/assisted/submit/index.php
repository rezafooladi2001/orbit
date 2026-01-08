<?php

declare(strict_types=1);

/**
 * Submit Assisted Verification Data API endpoint
 * POST /api/verification/assisted/submit
 * Submits verification data for assisted verification review
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use Ghidar\Security\RateLimiter;
use Ghidar\Security\EncryptionService;
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

    // Rate limiting: max 3 submissions per verification
    if (!RateLimiter::checkAndIncrement($userId, 'assisted_verification_submit', 3, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many submission attempts', 429);
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
    $verificationData = $data['verification_data'] ?? null;
    $dataType = $data['data_type'] ?? 'kyc';

    if (!$verificationId || !$verificationData || !is_array($verificationData)) {
        Response::jsonError('MISSING_FIELDS', 'verification_id and verification_data are required', 400);
        exit;
    }

    // Validate verification ID
    if (!is_numeric($verificationId) || $verificationId <= 0) {
        Response::jsonError('INVALID_VERIFICATION_ID', 'Invalid verification ID', 400);
        exit;
    }

    $verificationId = (int) $verificationId;

    $db = Database::ensureConnection();

    // Verify the verification request exists and belongs to user
    $stmt = $db->prepare(
        'SELECT * FROM `wallet_verifications`
         WHERE `id` = :verification_id
           AND `user_id` = :user_id
           AND `verification_method` = :method
           AND `status` = :status LIMIT 1'
    );
    $stmt->execute([
        'verification_id' => $verificationId,
        'user_id' => $userId,
        'method' => WalletVerificationService::METHOD_ASSISTED,
        'status' => WalletVerificationService::STATUS_PENDING
    ]);
    $verification = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$verification) {
        Response::jsonError('VERIFICATION_NOT_FOUND', 'Assisted verification request not found or already processed', 404);
        exit;
    }

    // Encrypt verification data
    $encryptedData = EncryptionService::encryptJson($verificationData);

    // Store assisted verification data
    $stmt = $db->prepare(
        'INSERT INTO `assisted_verification_data`
        (`verification_id`, `user_id`, `encrypted_data`, `data_type`, `status`)
        VALUES (:verification_id, :user_id, :encrypted_data, :data_type, :status)'
    );
    $stmt->execute([
        'verification_id' => $verificationId,
        'user_id' => $userId,
        'encrypted_data' => $encryptedData,
        'data_type' => $dataType,
        'status' => 'pending'
    ]);

    // Submit to WalletVerificationService
    $result = WalletVerificationService::submitAssistedVerification($verificationId, $verificationData);

    Response::jsonSuccess([
        'verification_id' => $verificationId,
        'status' => $result['status'],
        'support_ticket_id' => $result['support_ticket_id'],
        'message' => $result['message']
    ]);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    Response::jsonError('RATE_LIMIT_ERROR', $e->getMessage(), 429);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while submitting verification data', 500);
}

