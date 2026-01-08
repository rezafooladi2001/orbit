<?php

declare(strict_types=1);

/**
 * AI Trader History API endpoint for Ghidar
 * Returns performance history snapshots for the authenticated user.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\AITrader\AiTraderConfig;
use Ghidar\AITrader\AiTraderService;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;

try {
    // Authenticate user and get wallet
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get optional limit from query parameter or use default
    $limit = AiTraderConfig::HISTORY_LIMIT_DEFAULT;
    if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
        $limit = (int) $_GET['limit'];
    }

    // Get history
    $rawSnapshots = AiTraderService::getHistory($userId, $limit);

    // Transform to frontend format
    $snapshots = [];
    foreach ($rawSnapshots as $index => $snapshot) {
        $snapshots[] = [
            'id' => $index + 1,
            'time' => $snapshot['snapshot_time'] ?? date('Y-m-d H:i:s'),
            'balance' => $snapshot['balance_usdt'] ?? '0.00000000',
            'pnl' => $snapshot['pnl_usdt'] ?? '0.00000000',
        ];
    }

    // Prepare response
    $responseData = [
        'snapshots' => $snapshots
    ];

    Response::jsonSuccess($responseData);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', $e->getMessage(), 500);
}

