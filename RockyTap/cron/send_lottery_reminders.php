<?php
/**
 * Cron job: Lottery Reminders
 * 
 * Sends reminders to users about lotteries:
 * - Lottery ending soon (1 hour before draw)
 * - New lottery available
 * 
 * Schedule: Run every 15 minutes
 * Example crontab entry: Every 15 minutes
 *   0,15,30,45 * * * * /usr/bin/php /path/to/RockyTap/cron/send_lottery_reminders.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Notifications\NotificationService;
use Ghidar\Logging\Logger;

// Set execution time limit (15 minutes max)
set_time_limit(900);

// Log start
Logger::info('Lottery Reminders Cron: Starting', [
    'time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get()
]);

$db = Database::getConnection();

try {
    $results = [
        'ending_soon_sent' => 0,
        'ending_soon_failed' => 0,
        'new_lottery_sent' => 0,
        'new_lottery_failed' => 0,
    ];

    // ==========================================
    // 1. LOTTERY ENDING SOON NOTIFICATIONS
    // ==========================================
    // Find lotteries ending within the next hour that haven't sent reminders yet
    
    $oneHourFromNow = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $now = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("
        SELECT id, title, prize_pool_usdt, end_at, ticket_price_usdt
        FROM lotteries 
        WHERE status = 'active'
        AND end_at > :now
        AND end_at <= :one_hour_from_now
        AND (ending_reminder_sent IS NULL OR ending_reminder_sent = 0)
    ");
    $stmt->execute([
        ':now' => $now,
        ':one_hour_from_now' => $oneHourFromNow
    ]);
    $endingLotteries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($endingLotteries as $lottery) {
        $lotteryId = (int) $lottery['id'];
        $lotteryTitle = $lottery['title'];
        $prizePool = $lottery['prize_pool_usdt'];
        $endAt = $lottery['end_at'];
        
        // Calculate time remaining
        $endTimestamp = strtotime($endAt);
        $minutesRemaining = max(0, floor(($endTimestamp - time()) / 60));
        $timeRemaining = $minutesRemaining <= 60 
            ? "{$minutesRemaining} minutes" 
            : floor($minutesRemaining / 60) . " hour" . ($minutesRemaining >= 120 ? "s" : "");

        // Get all users with tickets for this lottery
        $stmt = $db->prepare("
            SELECT DISTINCT user_id, COUNT(*) as ticket_count
            FROM lottery_tickets 
            WHERE lottery_id = :lottery_id
            GROUP BY user_id
        ");
        $stmt->execute([':lottery_id' => $lotteryId]);
        $ticketHolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ticketHolders as $holder) {
            try {
                $userId = (int) $holder['user_id'];
                $ticketCount = (int) $holder['ticket_count'];
                
                NotificationService::notifyLotteryEndingSoon(
                    $userId,
                    $lotteryTitle,
                    $prizePool,
                    $ticketCount,
                    $timeRemaining
                );
                $results['ending_soon_sent']++;
                
                // Small delay to avoid rate limiting
                usleep(50000); // 50ms
            } catch (\Throwable $e) {
                $results['ending_soon_failed']++;
                Logger::warning('Failed to send lottery ending reminder', [
                    'user_id' => $holder['user_id'],
                    'lottery_id' => $lotteryId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Mark lottery as reminder sent
        $stmt = $db->prepare("
            UPDATE lotteries 
            SET ending_reminder_sent = 1 
            WHERE id = :lottery_id
        ");
        $stmt->execute([':lottery_id' => $lotteryId]);
        
        Logger::info('Lottery ending soon reminders sent', [
            'lottery_id' => $lotteryId,
            'ticket_holders' => count($ticketHolders)
        ]);
    }

    // ==========================================
    // 2. NEW LOTTERY AVAILABLE NOTIFICATIONS
    // ==========================================
    // Find new lotteries that haven't announced yet (created in last 30 mins)
    
    $thirtyMinsAgo = date('Y-m-d H:i:s', strtotime('-30 minutes'));
    
    $stmt = $db->prepare("
        SELECT id, title, prize_pool_usdt, ticket_price_usdt, end_at
        FROM lotteries 
        WHERE status = 'active'
        AND created_at >= :thirty_mins_ago
        AND (new_lottery_announced IS NULL OR new_lottery_announced = 0)
    ");
    $stmt->execute([':thirty_mins_ago' => $thirtyMinsAgo]);
    $newLotteries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($newLotteries as $lottery) {
        $lotteryId = (int) $lottery['id'];
        $lotteryTitle = $lottery['title'];
        $prizePool = $lottery['prize_pool_usdt'];
        $ticketPrice = $lottery['ticket_price_usdt'];
        $endAt = date('M j, Y \a\t H:i', strtotime($lottery['end_at']));

        // Get users who have participated in previous lotteries
        // (They are most likely to be interested in new lotteries)
        $stmt = $db->prepare("
            SELECT DISTINCT lt.user_id
            FROM lottery_tickets lt
            INNER JOIN lotteries l ON lt.lottery_id = l.id
            WHERE l.id != :current_lottery_id
            LIMIT 500
        ");
        $stmt->execute([':current_lottery_id' => $lotteryId]);
        $interestedUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($interestedUsers as $userId) {
            try {
                NotificationService::notifyNewLotteryAvailable(
                    (int) $userId,
                    $lotteryTitle,
                    $ticketPrice,
                    $prizePool,
                    $endAt
                );
                $results['new_lottery_sent']++;
                
                // Small delay to avoid rate limiting
                usleep(50000); // 50ms
            } catch (\Throwable $e) {
                $results['new_lottery_failed']++;
                Logger::warning('Failed to send new lottery notification', [
                    'user_id' => $userId,
                    'lottery_id' => $lotteryId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Mark lottery as announced
        $stmt = $db->prepare("
            UPDATE lotteries 
            SET new_lottery_announced = 1 
            WHERE id = :lottery_id
        ");
        $stmt->execute([':lottery_id' => $lotteryId]);
        
        Logger::info('New lottery announcements sent', [
            'lottery_id' => $lotteryId,
            'users_notified' => count($interestedUsers)
        ]);
    }

    $output = [
        'status' => 'success',
        'message' => 'Lottery reminders processed successfully',
        'results' => $results,
        'executed_at' => date('Y-m-d H:i:s')
    ];

    Logger::info('Lottery Reminders Cron: Completed', $output);
    
    echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;

} catch (\Exception $e) {
    Logger::error('Lottery Reminders Cron: Failed', [
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

