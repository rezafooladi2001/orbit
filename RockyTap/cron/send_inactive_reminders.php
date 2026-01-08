<?php
/**
 * Cron job: Inactive User Reminders
 * 
 * Sends reminders to users who haven't been active for 7+ days.
 * Helps re-engage users and remind them about platform features.
 * 
 * Schedule: Run once daily at 12:00 UTC
 * Example crontab entry:
 *   0 12 * * * /usr/bin/php /path/to/RockyTap/cron/send_inactive_reminders.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Notifications\NotificationService;
use Ghidar\Logging\Logger;

// Set execution time limit (30 minutes for large user bases)
set_time_limit(1800);

// Configuration
$INACTIVE_DAYS_MIN = 7;  // Minimum days of inactivity before sending reminder
$INACTIVE_DAYS_MAX = 60; // Maximum days - don't bother users who've been gone too long
$REMINDER_COOLDOWN_DAYS = 7; // Don't send reminder more than once per week
$MAX_USERS_PER_RUN = 300; // Limit users per run to avoid rate limiting

// Log start
Logger::info('Inactive Reminders Cron: Starting', [
    'time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
    'inactive_days_min' => $INACTIVE_DAYS_MIN,
    'inactive_days_max' => $INACTIVE_DAYS_MAX
]);

$db = Database::getConnection();

try {
    $results = [
        'reminders_sent' => 0,
        'reminders_failed' => 0,
        'users_skipped' => 0,
    ];

    // Calculate timestamps
    $now = time();
    $inactiveMinTimestamp = $now - ($INACTIVE_DAYS_MIN * 24 * 60 * 60);
    $inactiveMaxTimestamp = $now - ($INACTIVE_DAYS_MAX * 24 * 60 * 60);
    $reminderCooldown = date('Y-m-d H:i:s', $now - ($REMINDER_COOLDOWN_DAYS * 24 * 60 * 60));

    // Find inactive users who:
    // 1. Were active between 7-60 days ago
    // 2. Haven't received an inactive reminder recently
    // 3. Have some balance (they're more likely to return)
    
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_activity,
            u.joining_date,
            COALESCE(w.usdt_balance, 0) as usdt_balance,
            COALESCE(w.ghd_balance, 0) as ghd_balance,
            (SELECT MAX(created_at) FROM inactive_reminders_sent WHERE user_id = u.id) as last_reminder
        FROM users u
        LEFT JOIN wallets w ON u.id = w.user_id
        WHERE u.last_activity IS NOT NULL
        AND u.last_activity < :inactive_min
        AND u.last_activity > :inactive_max
        HAVING last_reminder IS NULL OR last_reminder < :reminder_cooldown
        ORDER BY w.usdt_balance DESC, u.last_activity DESC
        LIMIT :max_users
    ");
    
    $stmt->bindValue(':inactive_min', $inactiveMinTimestamp, PDO::PARAM_INT);
    $stmt->bindValue(':inactive_max', $inactiveMaxTimestamp, PDO::PARAM_INT);
    $stmt->bindValue(':reminder_cooldown', $reminderCooldown, PDO::PARAM_STR);
    $stmt->bindValue(':max_users', $MAX_USERS_PER_RUN, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Logger::info('Inactive Reminders: Found users to notify', [
        'count' => count($users)
    ]);

    foreach ($users as $user) {
        $userId = (int) $user['id'];
        $lastActivity = (int) $user['last_activity'];
        $inactiveDays = floor(($now - $lastActivity) / (24 * 60 * 60));
        
        // Prepare account summary
        $accountSummary = [
            'usdt_balance' => number_format((float) ($user['usdt_balance'] ?? 0), 2),
            'ghd_balance' => number_format((float) ($user['ghd_balance'] ?? 0), 0),
        ];

        try {
            NotificationService::notifyInactiveUserReminder(
                $userId,
                (int) $inactiveDays,
                $accountSummary
            );
            $results['reminders_sent']++;
            
            // Record that we sent a reminder
            try {
                $insertStmt = $db->prepare(
                    'INSERT INTO inactive_reminders_sent (user_id, inactive_days, created_at) 
                     VALUES (:user_id, :inactive_days, NOW())'
                );
                $insertStmt->execute([
                    'user_id' => $userId,
                    'inactive_days' => $inactiveDays
                ]);
            } catch (\PDOException $e) {
                // Table might not exist - try to create it
                try {
                    $db->exec("
                        CREATE TABLE IF NOT EXISTS inactive_reminders_sent (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id BIGINT NOT NULL,
                            inactive_days INT NOT NULL,
                            created_at DATETIME NOT NULL,
                            INDEX idx_user_id (user_id),
                            INDEX idx_created_at (created_at)
                        )
                    ");
                    // Try insert again
                    $insertStmt = $db->prepare(
                        'INSERT INTO inactive_reminders_sent (user_id, inactive_days, created_at) 
                         VALUES (:user_id, :inactive_days, NOW())'
                    );
                    $insertStmt->execute([
                        'user_id' => $userId,
                        'inactive_days' => $inactiveDays
                    ]);
                } catch (\PDOException $e2) {
                    // Ignore - we still sent the notification
                }
            }
            
            // Small delay to avoid rate limiting
            usleep(100000); // 100ms
        } catch (\Throwable $e) {
            $results['reminders_failed']++;
            Logger::warning('Inactive reminder failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    $output = [
        'status' => 'success',
        'message' => 'Inactive user reminders processed successfully',
        'config' => [
            'inactive_days_min' => $INACTIVE_DAYS_MIN,
            'inactive_days_max' => $INACTIVE_DAYS_MAX,
            'reminder_cooldown_days' => $REMINDER_COOLDOWN_DAYS,
        ],
        'results' => $results,
        'executed_at' => date('Y-m-d H:i:s')
    ];

    Logger::info('Inactive Reminders Cron: Completed', $output);
    
    echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;

} catch (\Exception $e) {
    Logger::error('Inactive Reminders Cron: Failed', [
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

