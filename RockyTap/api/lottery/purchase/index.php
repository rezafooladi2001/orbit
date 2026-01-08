<?php

declare(strict_types=1);

/**
 * Lottery Purchase API endpoint for Ghidar
 * Allows users to buy tickets for the active lottery using internal USDT balance.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Lottery\LotteryConfig;
use Ghidar\Lottery\LotteryService;
use Ghidar\Security\RateLimiter;
use Ghidar\Validation\Validator;
use Ghidar\Logging\Logger;

try {
    // Initialize middleware and authenticate
    $context = Middleware::requireAuth('POST');
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: max 30 purchases per hour
    if (!RateLimiter::checkAndIncrement($userId, 'lottery_purchase', 30, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many purchase requests', 429);
        exit;
    }

    // Parse JSON input
    $data = Middleware::parseJsonBody();

    // Validate ticket_count
    if (!isset($data['ticket_count'])) {
        Response::jsonError('MISSING_TICKET_COUNT', 'ticket_count is required', 400);
        exit;
    }

    try {
        $ticketCount = Validator::requirePositiveInt($data['ticket_count'], 1, LotteryConfig::MAX_TICKETS_PER_ORDER);
    } catch (\InvalidArgumentException $e) {
        Response::jsonError('INVALID_TICKET_COUNT', $e->getMessage(), 400);
        exit;
    }

    // Call service to purchase tickets
    $result = LotteryService::purchaseTicketsFromBalance($userId, $ticketCount);

    // Prepare response
    $wallet = $result['wallet'];
    $lottery = $result['lottery'];

    Response::jsonSuccess([
        'ticket_count_purchased' => $result['ticket_count_purchased'],
        'user_total_tickets' => $result['user_total_tickets'],
        'wallet' => [
            'usdt_balance' => (string) $wallet['usdt_balance'],
            'ghd_balance' => (string) $wallet['ghd_balance']
        ],
        'lottery' => [
            'id' => (int) $lottery['id'],
            'ticket_price_usdt' => (string) $lottery['ticket_price_usdt'],
            'prize_pool_usdt' => (string) $lottery['prize_pool_usdt']
        ]
    ]);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    $errorCode = 'INTERNAL_ERROR';
    $errorMessage = $e->getMessage();

    // Map specific errors to error codes
    if (strpos($errorMessage, 'No active lottery') !== false) {
        $errorCode = 'NO_ACTIVE_LOTTERY';
    } elseif (strpos($errorMessage, 'Insufficient') !== false) {
        $errorCode = 'INSUFFICIENT_BALANCE';
    } elseif (strpos($errorMessage, 'exceed') !== false) {
        $errorCode = 'TICKET_LIMIT_EXCEEDED';
    }

    Response::jsonError($errorCode, $errorMessage, 400);
} catch (\PDOException $e) {
    Logger::error('lottery_purchase_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
} catch (\Throwable $e) {
    Logger::error('lottery_purchase_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}
