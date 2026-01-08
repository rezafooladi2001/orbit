<?php

declare(strict_types=1);

/**
 * Deposit Status API
 * 
 * Check the status of a specific deposit.
 * Users can poll this to get real-time status updates.
 * 
 * GET /api/payments/deposit/status/?deposit_id=123
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Payments\PaymentsConfig;
use Ghidar\Security\RateLimiter;

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limit: 60 requests per minute for status checks
    $rateLimitCheck = RateLimiter::check((string) $userId, 'deposit_status', 60, 60);
    if (!$rateLimitCheck['allowed']) {
        Response::jsonError('RATE_LIMITED', 'Too many requests. Please wait before checking again.', 429);
        exit;
    }

    // Get deposit_id from query string
    if (!isset($_GET['deposit_id']) || !is_numeric($_GET['deposit_id'])) {
        Response::jsonError('INVALID_DEPOSIT_ID', 'deposit_id query parameter is required', 400);
        exit;
    }

    $depositId = (int) $_GET['deposit_id'];

    $db = Database::ensureConnection();

    // Get deposit and verify ownership
    $stmt = $db->prepare("SELECT * FROM deposits WHERE id = :id AND user_id = :user_id LIMIT 1");
    $stmt->execute(['id' => $depositId, 'user_id' => $userId]);
    $deposit = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($deposit === false) {
        Response::jsonError('DEPOSIT_NOT_FOUND', 'Deposit not found', 404);
        exit;
    }

    // Parse meta
    $meta = $deposit['meta'] ? json_decode($deposit['meta'], true) : [];

    // Prepare response
    $responseData = [
        'deposit_id' => (int) $deposit['id'],
        'network' => $deposit['network'],
        'product_type' => $deposit['product_type'],
        'status' => $deposit['status'],
        'address' => $deposit['address'],
        'expected_amount_usdt' => $deposit['expected_amount_usdt'],
        'actual_amount_usdt' => $deposit['actual_amount_usdt'],
        'tx_hash' => $deposit['tx_hash'],
        'created_at' => $deposit['created_at'],
        'confirmed_at' => $deposit['confirmed_at'],
        'user_marked_sent' => isset($meta['user_marked_sent']) && $meta['user_marked_sent'] === true,
    ];

    // Add status message for frontend
    $statusMessage = match ($deposit['status']) {
        PaymentsConfig::DEPOSIT_STATUS_PENDING => 'Waiting for blockchain confirmation',
        PaymentsConfig::DEPOSIT_STATUS_CONFIRMED => 'Deposit confirmed and credited',
        PaymentsConfig::DEPOSIT_STATUS_FAILED => 'Deposit failed - please contact support',
        PaymentsConfig::DEPOSIT_STATUS_EXPIRED => 'Deposit expired - please create a new one',
        default => 'Unknown status'
    };
    $responseData['status_message'] = $statusMessage;

    Response::jsonSuccess($responseData);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}
