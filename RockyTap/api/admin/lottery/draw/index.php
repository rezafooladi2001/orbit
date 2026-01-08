<?php

declare(strict_types=1);

/**
 * Admin API - Draw Lottery Winners
 * 
 * Draws winners for a lottery. Protected by ADMIN_MONITOR_KEY.
 * 
 * POST /api/admin/lottery/draw/
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Config\Config;
use Ghidar\Core\Response;
use Ghidar\Lottery\LotteryService;
use Ghidar\Logging\Logger;

try {
    // Verify admin token
    $headers = getallheaders();
    if ($headers === false) {
        Response::jsonError('UNAUTHORIZED', 'Invalid request headers', 401);
        exit;
    }

    $adminKey = null;
    foreach (['X-Admin-Key', 'x-admin-key', 'X-ADMIN-KEY'] as $header) {
        if (isset($headers[$header])) {
            $adminKey = $headers[$header];
            break;
        }
    }

    if ($adminKey === null) {
        Response::jsonError('UNAUTHORIZED', 'Missing X-Admin-Key header', 401);
        exit;
    }

    $expectedKey = Config::get('ADMIN_MONITOR_KEY');
    if ($expectedKey === null || $expectedKey === '') {
        Response::jsonError('INTERNAL_ERROR', 'Admin key not configured', 500);
        exit;
    }

    if (!hash_equals($expectedKey, $adminKey)) {
        Response::jsonError('UNAUTHORIZED', 'Invalid admin key', 401);
        exit;
    }

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

    // Validate lottery_id
    if (!isset($data['lottery_id']) || !is_numeric($data['lottery_id'])) {
        Response::jsonError('INVALID_LOTTERY_ID', 'lottery_id is required', 400);
        exit;
    }

    $lotteryId = (int) $data['lottery_id'];

    // Draw winners
    $result = LotteryService::drawWinners($lotteryId);

    Logger::event('lottery_draw_admin', [
        'lottery_id' => $lotteryId,
        'winner' => $result['winner'] ?? null
    ]);

    Response::jsonSuccess([
        'message' => 'Lottery draw completed',
        'lottery' => [
            'id' => (int) $result['lottery']['id'],
            'title' => $result['lottery']['title'],
            'status' => $result['lottery']['status'],
            'prize_pool_usdt' => (string) $result['lottery']['prize_pool_usdt']
        ],
        'winner' => $result['winner'] ?? null
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('DRAW_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Logger::error('lottery_draw_error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Response::jsonError('INTERNAL_ERROR', $e->getMessage(), 500);
}

