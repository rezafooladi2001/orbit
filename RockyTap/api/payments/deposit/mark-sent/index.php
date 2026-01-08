<?php

declare(strict_types=1);

/**
 * Mark Deposit as Sent API
 * 
 * Called when user clicks "I've Sent the Funds" button.
 * Sends a Telegram notification to the user and updates deposit metadata.
 * 
 * POST /api/payments/deposit/mark-sent/
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Payments\PaymentsConfig;
use Ghidar\Notifications\NotificationService;
use Ghidar\Logging\Logger;

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

    // Validate deposit_id
    if (!isset($data['deposit_id']) || !is_numeric($data['deposit_id'])) {
        Response::jsonError('INVALID_DEPOSIT_ID', 'deposit_id is required', 400);
        exit;
    }

    $depositId = (int) $data['deposit_id'];

    $db = Database::ensureConnection();

    // Get deposit and verify ownership
    $stmt = $db->prepare("SELECT * FROM deposits WHERE id = :id AND user_id = :user_id LIMIT 1");
    $stmt->execute(['id' => $depositId, 'user_id' => $userId]);
    $deposit = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($deposit === false) {
        Response::jsonError('DEPOSIT_NOT_FOUND', 'Deposit not found', 404);
        exit;
    }

    if ($deposit['status'] !== PaymentsConfig::DEPOSIT_STATUS_PENDING) {
        Response::jsonError('DEPOSIT_ALREADY_PROCESSED', 'Deposit is already processed', 400);
        exit;
    }

    // Update deposit meta to mark as "user_notified_sent"
    $meta = $deposit['meta'] ? json_decode($deposit['meta'], true) : [];
    $meta['user_marked_sent_at'] = date('Y-m-d H:i:s');
    $meta['user_marked_sent'] = true;

    $stmt = $db->prepare("UPDATE deposits SET meta = :meta WHERE id = :id");
    $stmt->execute([
        'meta' => json_encode($meta),
        'id' => $depositId
    ]);

    // Send beautiful Telegram notification
    NotificationService::notifyDepositPending(
        $telegramId,
        $deposit['network'],
        $deposit['expected_amount_usdt'],
        $deposit['address'],
        $depositId
    );

    // Log event
    Logger::event('deposit_marked_sent', [
        'deposit_id' => $depositId,
        'user_id' => $userId,
        'network' => $deposit['network'],
        'amount' => $deposit['expected_amount_usdt']
    ]);

    Response::jsonSuccess([
        'message' => 'Deposit marked as sent. You will be notified when confirmed.',
        'deposit_id' => $depositId,
        'status' => 'pending'
    ]);

} catch (\Exception $e) {
    Logger::error('mark_sent_error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}

