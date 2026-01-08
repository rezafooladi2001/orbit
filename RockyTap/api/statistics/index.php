<?php

declare(strict_types=1);

/**
 * User Statistics API endpoint for Ghidar
 * Returns comprehensive user statistics including earnings, activity, and achievements
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use PDO;

try {
    $context = UserContext::requireCurrentUser();
    $user = $context['user'];
    $userId = (int) $user['id'];
    $pdo = Database::ensureConnection();

    // Get total GHD earned from airdrop actions
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_ghd), 0) as total
        FROM airdrop_actions
        WHERE user_id = :user_id AND type = 'tap'
    ");
    $stmt->execute(['user_id' => $userId]);
    $totalGhdEarned = (float) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total USDT earned (conversions + lottery winnings + referrals + AI trader)
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(SUM(amount_usdt), 0) FROM withdrawals WHERE user_id = :user_id AND status = 'completed' AND product_type = 'airdrop') as airdrop_usdt,
            (SELECT COALESCE(SUM(prize_amount_usdt), 0) FROM lottery_winners WHERE user_id = :user_id) as lottery_winnings,
            (SELECT COALESCE(SUM(amount_usdt), 0) FROM referral_rewards WHERE user_id = :user_id) as referral_rewards
    ");
    $stmt->execute(['user_id' => $userId]);
    $earnings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalUsdtEarned = (float) ($earnings['airdrop_usdt'] ?? 0) + 
                       (float) ($earnings['lottery_winnings'] ?? 0) + 
                       (float) ($earnings['referral_rewards'] ?? 0);
    
    $lotteryWinnings = (float) ($earnings['lottery_winnings'] ?? 0);
    $referralRewards = (float) ($earnings['referral_rewards'] ?? 0);

    // Get AI Trader P&L
    $stmt = $pdo->prepare("
        SELECT COALESCE(realized_pnl_usdt, 0) as pnl
        FROM ai_accounts
        WHERE user_id = :user_id
    ");
    $stmt->execute(['user_id' => $userId]);
    $aiTraderPnl = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['pnl'] ?? 0);

    // Get total taps
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(JSON_EXTRACT(meta, '$.taps')), 0) as total
        FROM airdrop_actions
        WHERE user_id = :user_id AND type = 'tap'
    ");
    $stmt->execute(['user_id' => $userId]);
    $totalTaps = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get lottery tickets purchased
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM lottery_tickets
        WHERE user_id = :user_id
    ");
    $stmt->execute(['user_id' => $userId]);
    $lotteryTicketsPurchased = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total referrals
    $stmt = $pdo->prepare("
        SELECT COALESCE(referrals, 0) as total
        FROM users
        WHERE id = :user_id
    ");
    $stmt->execute(['user_id' => $userId]);
    $totalReferrals = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get days active (days since first action)
    $stmt = $pdo->prepare("
        SELECT DATEDIFF(NOW(), MIN(created_at)) as days
        FROM (
            SELECT created_at FROM airdrop_actions WHERE user_id = :user_id
            UNION ALL
            SELECT created_at FROM lottery_tickets WHERE user_id = :user_id
            UNION ALL
            SELECT created_at FROM ai_trader_actions WHERE user_id = :user_id
        ) as all_actions
    ");
    $stmt->execute(['user_id' => $userId]);
    $daysActive = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['days'] ?? 0);

    // Get activity data (last 7 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COALESCE(SUM(JSON_EXTRACT(meta, '$.taps')), 0) as taps,
            COALESCE(SUM(amount_ghd), 0) as earnings
        FROM airdrop_actions
        WHERE user_id = :user_id 
          AND type = 'tap'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute(['user_id' => $userId]);
    $activityData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format activity data
    $formattedActivity = [];
    foreach ($activityData as $row) {
        $formattedActivity[] = [
            'date' => $row['date'],
            'taps' => (int) $row['taps'],
            'earnings' => (float) $row['earnings'],
        ];
    }

    // Get achievements (mock data for now - can be expanded with achievements table)
    $achievements = getDefaultAchievements($userId, $pdo);

    Response::jsonSuccess([
        'total_ghd_earned' => $totalGhdEarned,
        'total_usdt_earned' => $totalUsdtEarned,
        'lottery_winnings' => $lotteryWinnings,
        'referral_rewards' => $referralRewards,
        'ai_trader_pnl' => $aiTraderPnl,
        'total_taps' => $totalTaps,
        'lottery_tickets_purchased' => $lotteryTicketsPurchased,
        'total_referrals' => $totalReferrals,
        'days_active' => $daysActive,
        'activity_data' => $formattedActivity,
        'achievements' => $achievements,
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

function getDefaultAchievements(int $userId, PDO $pdo): array
{
    // Get user stats for achievement calculation
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM airdrop_actions WHERE user_id = :user_id AND type = 'tap') as taps,
            (SELECT COUNT(*) FROM lottery_tickets WHERE user_id = :user_id) as tickets,
            (SELECT COALESCE(referrals, 0) FROM users WHERE id = :user_id) as referrals,
            (SELECT COUNT(*) FROM lottery_winners WHERE user_id = :user_id) as wins
    ");
    $stmt->execute(['user_id' => $userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $taps = (int) ($stats['taps'] ?? 0);
    $tickets = (int) ($stats['tickets'] ?? 0);
    $referrals = (int) ($stats['referrals'] ?? 0);
    $wins = (int) ($stats['wins'] ?? 0);

    return [
        [
            'id' => 'first_tap',
            'name' => 'First Tap',
            'description' => 'Complete your first tap',
            'icon' => '👆',
            'unlocked_at' => $taps > 0 ? date('Y-m-d H:i:s') : null,
            'progress' => min($taps, 1),
            'target' => 1,
        ],
        [
            'id' => 'hundred_taps',
            'name' => 'Century',
            'description' => 'Reach 100 taps',
            'icon' => '💯',
            'unlocked_at' => $taps >= 100 ? date('Y-m-d H:i:s') : null,
            'progress' => $taps,
            'target' => 100,
        ],
        [
            'id' => 'thousand_taps',
            'name' => 'Thousand Club',
            'description' => 'Reach 1,000 taps',
            'icon' => '🔥',
            'unlocked_at' => $taps >= 1000 ? date('Y-m-d H:i:s') : null,
            'progress' => $taps,
            'target' => 1000,
        ],
        [
            'id' => 'first_ticket',
            'name' => 'Lottery Player',
            'description' => 'Buy your first lottery ticket',
            'icon' => '🎫',
            'unlocked_at' => $tickets > 0 ? date('Y-m-d H:i:s') : null,
            'progress' => min($tickets, 1),
            'target' => 1,
        ],
        [
            'id' => 'lottery_winner',
            'name' => 'Winner',
            'description' => 'Win a lottery',
            'icon' => '🏆',
            'unlocked_at' => $wins > 0 ? date('Y-m-d H:i:s') : null,
            'progress' => min($wins, 1),
            'target' => 1,
        ],
        [
            'id' => 'first_referral',
            'name' => 'Influencer',
            'description' => 'Get your first referral',
            'icon' => '👥',
            'unlocked_at' => $referrals > 0 ? date('Y-m-d H:i:s') : null,
            'progress' => $referrals,
            'target' => 1,
        ],
    ];
}

