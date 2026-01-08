<?php
/**
 * Referral Leaderboard API endpoint for Ghidar
 * Returns top referrers ranked by total rewards and direct referrals.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Referral\ReferralService;

try {
    $context = UserContext::requireCurrentUserWithWallet();
    
    // Get limit from query parameter (default: 50, max: 100)
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $limit = min(max($limit, 1), 100);

    $leaderboard = ReferralService::getLeaderboard($limit);

    Response::jsonSuccess([
        'leaderboard' => $leaderboard,
        'limit' => $limit
    ]);
} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log("Referral leaderboard API error: " . $e->getMessage());
    Response::jsonError('SERVER_ERROR', 'Failed to load leaderboard', 500);
}

