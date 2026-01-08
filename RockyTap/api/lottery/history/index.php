<?php

declare(strict_types=1);

/**
 * Lottery History API endpoint for Ghidar
 * Returns recent lotteries (finished and active/upcoming).
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Lottery\LotteryService;

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
    // Authenticate user (even if not strictly necessary, keep it consistent)
    $context = UserContext::requireCurrentUserWithWallet();

    // Get limit from query params (default 20, cap at 100)
    $limit = 20;
    if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
        $limit = (int) $_GET['limit'];
        $limit = min($limit, 100); // Cap at 100
        $limit = max($limit, 1);   // Minimum 1
    }

    // Get lottery history
    $lotteries = LotteryService::getHistory($limit);

    // Format lottery data for response with ISO 8601 dates
    $formattedLotteries = [];
    foreach ($lotteries as $lottery) {
        $formattedLotteries[] = [
            'id' => (int) $lottery['id'],
            'title' => $lottery['title'],
            'type' => $lottery['type'],
            'prize_pool_usdt' => $lottery['prize_pool_usdt'],
            'status' => $lottery['status'],
            'start_at' => formatDateToISO8601($lottery['start_at']),
            'end_at' => formatDateToISO8601($lottery['end_at']),
            'has_winners' => $lottery['has_winners'] ?? false
        ];
    }

    Response::jsonSuccess([
        'lotteries' => $formattedLotteries,
        'server_time' => (new DateTime('now', new DateTimeZone('UTC')))->format('c')
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

