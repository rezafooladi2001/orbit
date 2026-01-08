<?php

declare(strict_types=1);

/**
 * Airdrop Status API endpoint for Ghidar
 * Returns user's airdrop/wallet status including GHD balance and estimated USDT value.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Airdrop\AirdropService;
use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Logging\Logger;

try {
    // Initialize middleware and authenticate (GET allowed for status)
    $context = Middleware::requireAuth('GET');
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get airdrop status
    $status = AirdropService::getStatus($userId);

    Response::jsonSuccess($status);

} catch (\PDOException $e) {
    Logger::error('airdrop_status_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
} catch (\Throwable $e) {
    Logger::error('airdrop_status_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}
