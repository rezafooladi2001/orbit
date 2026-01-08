<?php

declare(strict_types=1);

/**
 * Lottery Status API endpoint for Ghidar
 * Returns the current active lottery info and user's ticket count.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Lottery\LotteryService;
use Ghidar\Logging\Logger;

/**
 * Convert MySQL DATETIME to ISO 8601 format with UTC timezone.
 * This ensures consistent date parsing across different client timezones.
 * 
 * @param string|null $dateString MySQL datetime string
 * @return string|null ISO 8601 formatted date or null
 */
function formatDateToISO8601(?string $dateString): ?string
{
    if ($dateString === null || $dateString === '') {
        return null;
    }
    
    try {
        // Parse the MySQL datetime (assumes server timezone)
        $date = new DateTime($dateString, new DateTimeZone(date_default_timezone_get()));
        // Convert to UTC and format as ISO 8601
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date->format('c'); // ISO 8601 format: 2025-02-14T23:59:59+00:00
    } catch (\Exception $e) {
        return $dateString; // Return original if parsing fails
    }
}

try {
    // Initialize middleware and authenticate (GET allowed for status)
    $context = Middleware::requireAuth('GET');
    $user = $context['user'];
    $wallet = $context['wallet'];
    $userId = (int) $user['id'];

    // Prepare user data (always needed for consistent response)
    $userData = [
        'id' => (int) $user['id'],
        'telegram_id' => (int) $user['id'],
        'username' => $user['username'] ?? null
    ];

    // Prepare wallet data (always needed for consistent response)
    $walletData = [
        'usdt_balance' => (string) $wallet['usdt_balance'],
        'ghd_balance' => (string) $wallet['ghd_balance']
    ];

    // Get active lottery and user status
    $userStatus = LotteryService::getUserStatusForActiveLottery($userId);

    if ($userStatus === null) {
        // No active lottery - still return user/wallet data for UI consistency
        Response::jsonSuccess([
            'lottery' => null,
            'user' => $userData,
            'wallet' => $walletData,
            'user_tickets_count' => 0,
            'server_time' => (new DateTime('now', new DateTimeZone('UTC')))->format('c')
        ]);
        exit;
    }

    // Prepare lottery data with properly formatted dates
    $lotteryData = [
        'id' => (int) $userStatus['lottery']['id'],
        'title' => $userStatus['lottery']['title'],
        'description' => $userStatus['lottery']['description'],
        'type' => $userStatus['lottery']['type'],
        'ticket_price_usdt' => $userStatus['ticket_price_usdt'],
        'prize_pool_usdt' => $userStatus['prize_pool_usdt'],
        'status' => $userStatus['lottery']['status'],
        'start_at' => formatDateToISO8601($userStatus['lottery']['start_at']),
        'end_at' => formatDateToISO8601($userStatus['lottery']['end_at'])
    ];

    // Return unified response with server time for client sync
    Response::jsonSuccess([
        'lottery' => $lotteryData,
        'user' => $userData,
        'wallet' => $walletData,
        'user_tickets_count' => $userStatus['user_tickets_count'],
        'server_time' => (new DateTime('now', new DateTimeZone('UTC')))->format('c')
    ]);

} catch (\PDOException $e) {
    Logger::error('lottery_status_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
} catch (\Throwable $e) {
    Logger::error('lottery_status_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}
