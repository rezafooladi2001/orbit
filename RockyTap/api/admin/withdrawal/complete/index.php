<?php

declare(strict_types=1);

/**
 * Admin API: Complete Withdrawal
 * 
 * Marks a withdrawal as completed after manual processing.
 * Notifies the user that their withdrawal is complete.
 * 
 * POST /api/admin/withdrawal/complete/
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Config\Config;
use Ghidar\Notifications\NotificationService;
use Ghidar\Logging\Logger;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

try {
    // Verify admin token
    $adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    $expectedToken = Config::get('ADMIN_API_TOKEN');
    
    if (empty($expectedToken) || $adminToken !== $expectedToken) {
        Response::jsonError('UNAUTHORIZED', 'Invalid admin token', 401);
        exit;
    }

    // Parse request
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
    $withdrawalId = $data['withdrawal_id'] ?? null;
    $txHash = $data['tx_hash'] ?? null;

    if (!$withdrawalId || !is_numeric($withdrawalId)) {
        Response::jsonError('INVALID_ID', 'withdrawal_id is required', 400);
        exit;
    }

    if (empty($txHash)) {
        Response::jsonError('MISSING_TX_HASH', 'tx_hash is required', 400);
        exit;
    }

    $db = Database::ensureConnection();

    // Get withdrawal details
    $stmt = $db->prepare("
        SELECT 
            id, user_id, telegram_id, amount_usdt, network, target_address, status
        FROM withdrawal_requests 
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => (int) $withdrawalId]);
    $withdrawal = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($withdrawal === false) {
        Response::jsonError('NOT_FOUND', 'Withdrawal not found', 404);
        exit;
    }

    if ($withdrawal['status'] === 'completed') {
        Response::jsonError('ALREADY_COMPLETED', 'Withdrawal already completed', 400);
        exit;
    }

    // Add tx_hash column if it doesn't exist
    try {
        $db->exec("ALTER TABLE `withdrawal_requests` ADD COLUMN IF NOT EXISTS `tx_hash` VARCHAR(255) NULL");
    } catch (\PDOException $e) {
        // Ignore if column exists
    }

    // Update withdrawal status
    $updateStmt = $db->prepare("
        UPDATE withdrawal_requests 
        SET status = 'completed', 
            tx_hash = :tx_hash,
            processed_at = NOW()
        WHERE id = :id
    ");
    $updateStmt->execute([
        'id' => (int) $withdrawalId,
        'tx_hash' => $txHash
    ]);

    // Send notification to user
    $telegramId = (int) $withdrawal['telegram_id'];
    $amount = $withdrawal['amount_usdt'];
    $network = $withdrawal['network'];
    $address = $withdrawal['target_address'];

    $networkName = match (strtolower($network)) {
        'erc20' => 'Ethereum (ERC20)',
        'bep20' => 'BSC (BEP20)',
        'trc20' => 'Tron (TRC20)',
        default => strtoupper($network)
    };

    // Get explorer URL
    $explorerUrl = match (strtolower($network)) {
        'erc20' => "https://etherscan.io/tx/{$txHash}",
        'bep20' => "https://bscscan.com/tx/{$txHash}",
        'trc20' => "https://tronscan.org/#/transaction/{$txHash}",
        default => ''
    };

    try {
        NotificationService::sendTelegramMessage($telegramId, 
            "✅ <b>Withdrawal Complete!</b>\n\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
            . "💰 <b>Amount:</b> \${$amount} USDT\n"
            . "🌐 <b>Network:</b> {$networkName}\n"
            . "📍 <b>To:</b> <code>" . substr($address, 0, 10) . "..." . substr($address, -6) . "</code>\n\n"
            . "🔗 <b>Transaction:</b>\n<code>" . substr($txHash, 0, 20) . "...</code>\n\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
            . "🎉 Your funds have been sent successfully!\n\n"
            . ($explorerUrl ? "👉 <a href=\"{$explorerUrl}\">View Transaction</a>" : "")
        );
    } catch (\Exception $e) {
        Logger::warning('withdrawal_complete_notification_failed', [
            'withdrawal_id' => $withdrawalId,
            'telegram_id' => $telegramId,
            'error' => $e->getMessage()
        ]);
    }

    Logger::event('withdrawal_completed', [
        'withdrawal_id' => (int) $withdrawalId,
        'telegram_id' => $telegramId,
        'amount' => $amount,
        'network' => $network,
        'tx_hash' => $txHash
    ]);

    Response::jsonSuccess([
        'withdrawal_id' => (int) $withdrawalId,
        'status' => 'completed',
        'tx_hash' => $txHash,
        'notification_sent' => true
    ]);

} catch (\PDOException $e) {
    Logger::error('admin_withdrawal_complete_db_error', [
        'error' => $e->getMessage()
    ]);
    Response::jsonError('DATABASE_ERROR', 'Database error occurred', 500);
} catch (\Exception $e) {
    Logger::error('admin_withdrawal_complete_error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Response::jsonError('INTERNAL_ERROR', $e->getMessage(), 500);
}

