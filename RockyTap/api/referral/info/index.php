<?php

declare(strict_types=1);

/**
 * Referral Info API endpoint for Ghidar
 * Returns user's referral code, link, statistics, rank, and recent referrals.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Referral\ReferralService;
use Ghidar\Logging\Logger;

try {
    // Initialize middleware and authenticate (GET allowed for info)
    $context = Middleware::requireAuth('GET');
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get basic referral info
    $referralInfo = ReferralService::getReferralInfo($userId);
    
    // Add user's leaderboard rank
    $referralInfo['user_rank'] = ReferralService::getUserRank($userId);
    
    // Add recent referrals (users who joined via this user's link)
    $referralInfo['recent_referrals'] = ReferralService::getRecentReferrals($userId, 5);

    Response::jsonSuccess($referralInfo);

} catch (\PDOException $e) {
    Logger::error('referral_info_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
} catch (\Throwable $e) {
    Logger::error('referral_info_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}
