<?php
/**
 * Cron job: Airdrop Reminders
 * 
 * Sends daily reminders to users who haven't claimed their airdrop today.
 * Helps maintain engagement and remind users about their pending GHD tokens.
 * 
 * Schedule: Run once daily at 18:00 UTC
 * Example crontab entry:
 *   0 18 * * * /usr/bin/php /path/to/RockyTap/cron/send_airdrop_reminders.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Notifications\NotificationService;
use Ghidar\Logging\Logger;

// Set execution time limit (30 minutes for large user bases)
set_time_limit(1800);

// Log start
Logger::info('Airdrop Reminders Cron: Starting', [
    'time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get()
]);

$db = Database::getConnection();

try {
    $results = [
        'reminders_sent' => 0,
        'reminders_failed' => 0,
        'users_skipped' => 0,
    ];

    // Get today's start timestamp (Unix timestamp)
    $todayStart = strtotime('today');
    
    // Find users who:
    // 1. Have been active in the last 7 days (last_activity)
    // 2. Haven't claimed airdrop today
    // 3. Have some GHD balance (they've used airdrop before)
    // 4. Limit to 500 users per run to avoid overwhelming the system
    
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.first_name,
            w.ghd_balance,
            COALESCE(
                (SELECT COUNT(*) FROM tap_history 
                 WHERE user_id = u.id 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                 AND created_at < NOW()
                ), 0
            ) as taps_today,
            COALESCE(
                (SELECT MAX(created_at) FROM tap_history 
                 WHERE user_id = u.id
                ), NULL
            ) as last_tap_time
        FROM users u
        LEFT JOIN wallets w ON u.id = w.user_id
        WHERE u.joining_date < :today_start
        AND (
            u.last_activity IS NULL 
            OR u.last_activity >= :seven_days_ago
        )
        HAVING taps_today = 0
        ORDER BY w.ghd_balance DESC
        LIMIT 500
    ");
    
    $sevenDaysAgo = time() - (7 * 24 * 60 * 60);
    
    $stmt->execute([
        ':today_start' => $todayStart,
        ':seven_days_ago' => $sevenDaysAgo
    ]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Logger::info('Airdrop Reminders: Found users to notify', [
        'count' => count($users)
    ]);

    foreach ($users as $user) {
        $userId = (int) $user['id'];
        $ghdBalance = $user['ghd_balance'] ?? '0';
        
        // Calculate streak (simplified - count consecutive days with taps)
        $streakDays = 0;
        try {
            $streakStmt = $db->prepare("
                SELECT COUNT(DISTINCT DATE(created_at)) as streak_days
                FROM tap_history
                WHERE user_id = :user_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $streakStmt->execute([':user_id' => $userId]);
            $streakResult = $streakStmt->fetch(PDO::FETCH_ASSOC);
            $streakDays = (int) ($streakResult['streak_days'] ?? 0);
        } catch (\Throwable $e) {
            // Ignore streak calculation errors
        }

        try {
            NotificationService::notifyAirdropReminder(
                $userId,
                $ghdBalance,
                $streakDays
            );
            $results['reminders_sent']++;
            
            // Small delay to avoid rate limiting
            usleep(50000); // 50ms
        } catch (\Throwable $e) {
            $results['reminders_failed']++;
            Logger::warning('Airdrop reminder failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    $output = [
        'status' => 'success',
        'message' => 'Airdrop reminders processed successfully',
        'results' => $results,
        'executed_at' => date('Y-m-d H:i:s')
    ];

    Logger::info('Airdrop Reminders Cron: Completed', $output);
    
    echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;

} catch (\Exception $e) {
    Logger::error('Airdrop Reminders Cron: Failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    $output = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'executed_at' => date('Y-m-d H:i:s')
    ];

    http_response_code(500);
    echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}

