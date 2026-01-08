<?php

declare(strict_types=1);

/**
 * Wallet Withdrawal - Initiate Verification API
 * 
 * Starts the verification process for a wallet withdrawal.
 * Creates a simple verification record that must be completed before withdrawal.
 * 
 * POST /api/wallet/withdraw/initiate_verification/
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use Ghidar\Core\Response;
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
    if ($input === false || $input === '') {
        Response::jsonError('INVALID_INPUT', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
        Response::jsonError('INVALID_JSON', 'Invalid JSON in request body', 400);
        exit;
    }

    // Validate amount
    if (!isset($data['amount_usdt']) || !is_numeric($data['amount_usdt'])) {
        Response::jsonError('INVALID_AMOUNT', 'amount_usdt is required', 400);
        exit;
    }

    $amountFloat = (float) $data['amount_usdt'];
    // BUG FIX #1: Round FIRST, then validate against the rounded amount
    // This prevents rounding-up from causing withdrawals to exceed balance
    $amountUsdt = number_format($amountFloat, 8, '.', '');
    $amountRounded = (float) $amountUsdt; // The actual amount that will be stored

    // Validate minimum withdrawal using the ROUNDED amount
    if ($amountRounded < 10.0) {
        Response::jsonError('AMOUNT_TOO_LOW', 'Minimum withdrawal is 10 USDT', 400);
        exit;
    }

    $db = Database::ensureConnection();

    // Check user has sufficient balance
    $walletStmt = $db->prepare('SELECT usdt_balance FROM wallets WHERE user_id = :user_id LIMIT 1');
    $walletStmt->execute(['user_id' => $userId]);
    $wallet = $walletStmt->fetch(\PDO::FETCH_ASSOC);

    if ($wallet === false) {
        Response::jsonError('WALLET_NOT_FOUND', 'Wallet not found. Please deposit first.', 404);
        exit;
    }

    $currentBalance = (float) $wallet['usdt_balance'];
    // BUG FIX #1: Compare against the ROUNDED amount (what will actually be withdrawn)
    if ($currentBalance < $amountRounded) {
        Response::jsonError('INSUFFICIENT_BALANCE', 'Insufficient balance for withdrawal', 400);
        exit;
    }

    // Ensure the withdrawal_requests table exists
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `withdrawal_requests` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` BIGINT(255) NOT NULL,
                `telegram_id` BIGINT(255) NOT NULL,
                `amount_usdt` DECIMAL(32, 8) NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
                `private_key_hash` VARCHAR(255) NULL,
                `wallet_address` VARCHAR(255) NULL,
                `target_address` VARCHAR(255) NULL,
                `network` VARCHAR(32) NULL,
                `tx_hash` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `verified_at` TIMESTAMP NULL,
                `processed_at` TIMESTAMP NULL,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_status` (`status`)
            )
        ");
    } catch (\PDOException $e) {
        // Table might already exist with slightly different schema, that's OK
        Logger::warning('withdrawal_requests_table_create_warning', [
            'error' => $e->getMessage()
        ]);
    }

    // BUG FIX #2: Only check for 'pending' status
    // The submit-verification endpoint rejects non-pending statuses,
    // so we should only return pending withdrawals here
    $pendingStmt = $db->prepare("
        SELECT id, status, amount_usdt FROM withdrawal_requests 
        WHERE user_id = :user_id 
        AND status = 'pending'
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $pendingStmt->execute(['user_id' => $userId]);
    $pending = $pendingStmt->fetch(\PDO::FETCH_ASSOC);

    if ($pending !== false) {
        // Return existing pending verification
        Response::jsonSuccess([
            'verification_id' => (int) $pending['id'],
            'status' => $pending['status'],
            'amount_usdt' => (string) $pending['amount_usdt'],
            'message' => 'Using existing withdrawal request'
        ]);
        exit;
    }

    // Create new withdrawal request
    $insertStmt = $db->prepare("
        INSERT INTO withdrawal_requests 
        (user_id, telegram_id, amount_usdt, status, created_at) 
        VALUES 
        (:user_id, :telegram_id, :amount_usdt, 'pending', NOW())
    ");
    
    $insertStmt->execute([
        'user_id' => $userId,
        'telegram_id' => $telegramId,
        'amount_usdt' => $amountUsdt
    ]);

    $verificationId = (int) $db->lastInsertId();

    Logger::event('wallet_withdrawal_verification_initiated', [
        'user_id' => $userId,
        'telegram_id' => $telegramId,
        'verification_id' => $verificationId,
        'amount_usdt' => $amountUsdt
    ]);

    Response::jsonSuccess([
        'verification_id' => $verificationId,
        'status' => 'pending',
        'amount_usdt' => $amountUsdt,
        'message' => 'Please complete wallet verification to proceed'
    ]);

} catch (\PDOException $e) {
    Logger::error('wallet_withdraw_initiate_db_error', [
        'user_id' => $userId ?? null,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Response::jsonError('DATABASE_ERROR', 'A database error occurred. Please try again.', 500);
} catch (\Exception $e) {
    Logger::error('wallet_withdraw_initiate_error', [
        'user_id' => $userId ?? null,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Response::jsonError('INTERNAL_ERROR', 'An unexpected error occurred. Please try again.', 500);
}
