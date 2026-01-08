<?php

declare(strict_types=1);

/**
 * Admin API: Reject Withdrawal
 * 
 * Rejects a withdrawal and returns funds to user's wallet.
 * Notifies the user about the rejection with reason.
 * 
 * POST /api/admin/withdrawal/reject/
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
    $reason = $data['reason'] ?? 'Withdrawal rejected by administrator';

    if (!$withdrawalId || !is_numeric($withdrawalId)) {
        Response::jsonError('INVALID_ID', 'withdrawal_id is required', 400);
        exit;
    }

    $db = Database::ensureConnection();
    $db->beginTransaction();

    try {
        // Get withdrawal details
        $stmt = $db->prepare("
            SELECT 
                wr.id, 
                wr.user_id, 
                wr.telegram_id, 
                wr.amount_usdt, 
                wr.network, 
                wr.target_address, 
                wr.status,
                wr.product_type
            FROM withdrawal_requests wr
            WHERE wr.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => (int) $withdrawalId]);
        $withdrawal = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($withdrawal === false) {
            $db->rollBack();
            Response::jsonError('NOT_FOUND', 'Withdrawal not found', 404);
            exit;
        }

        if ($withdrawal['status'] === 'completed') {
            $db->rollBack();
            Response::jsonError('ALREADY_COMPLETED', 'Cannot reject a completed withdrawal', 400);
            exit;
        }

        if ($withdrawal['status'] === 'rejected') {
            $db->rollBack();
            Response::jsonError('ALREADY_REJECTED', 'Withdrawal already rejected', 400);
            exit;
        }

        $userId = (int) $withdrawal['user_id'];
        $telegramId = (int) $withdrawal['telegram_id'];
        $amount = $withdrawal['amount_usdt'];
        $network = $withdrawal['network'];
        $productType = $withdrawal['product_type'] ?? 'wallet';

        // Return funds to user's wallet
        $returnStmt = $db->prepare("
            UPDATE wallets 
            SET usdt_balance = usdt_balance + :amount 
            WHERE user_id = :user_id
        ");
        $returnStmt->execute([
            'amount' => $amount,
            'user_id' => $userId
        ]);

        // Update withdrawal status to rejected
        $updateStmt = $db->prepare("
            UPDATE withdrawal_requests 
            SET status = 'rejected', 
                rejection_reason = :reason,
                processed_at = NOW()
            WHERE id = :id
        ");
        $updateStmt->execute([
            'id' => (int) $withdrawalId,
            'reason' => $reason
        ]);

        $db->commit();

        // Send rejection notification to user
        try {
            NotificationService::notifyWithdrawalRejected(
                $telegramId,
                $amount,
                $network ?? 'unknown',
                $reason,
                (int) $withdrawalId
            );
        } catch (\Exception $e) {
            Logger::warning('withdrawal_reject_notification_failed', [
                'withdrawal_id' => $withdrawalId,
                'telegram_id' => $telegramId,
                'error' => $e->getMessage()
            ]);
        }

        Logger::event('withdrawal_rejected', [
            'withdrawal_id' => (int) $withdrawalId,
            'telegram_id' => $telegramId,
            'amount' => $amount,
            'reason' => $reason,
            'funds_returned' => true
        ]);

        Response::jsonSuccess([
            'withdrawal_id' => (int) $withdrawalId,
            'status' => 'rejected',
            'reason' => $reason,
            'funds_returned' => true,
            'amount_returned' => $amount,
            'notification_sent' => true
        ]);

    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (\PDOException $e) {
    Logger::error('admin_withdrawal_reject_db_error', [
        'error' => $e->getMessage()
    ]);
    Response::jsonError('DATABASE_ERROR', 'Database error occurred', 500);
} catch (\Exception $e) {
    Logger::error('admin_withdrawal_reject_error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Response::jsonError('INTERNAL_ERROR', $e->getMessage(), 500);
}

