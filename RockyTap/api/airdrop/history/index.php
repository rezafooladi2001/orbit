<?php

declare(strict_types=1);

/**
 * Airdrop History API endpoint for Ghidar
 * Returns user's airdrop action history.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Airdrop\AirdropService;
use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Logging\Logger;

try {
    // Initialize middleware and authenticate (GET allowed for history)
    $context = Middleware::requireAuth('GET');
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get optional parameters
    $limit = isset($_GET['limit']) ? min((int) $_GET['limit'], 100) : 50;
    $type = isset($_GET['type']) && in_array($_GET['type'], ['tap', 'convert_to_usdt']) 
        ? $_GET['type'] 
        : null;

    // Get history
    $history = AirdropService::getHistory($userId, $limit, $type);

    Response::jsonSuccess([
        'history' => $history
    ]);

} catch (\PDOException $e) {
    Logger::error('airdrop_history_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
} catch (\Throwable $e) {
    Logger::error('airdrop_history_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}
