<?php
/**
 * Cron job: AI Trader Daily Reports
 * 
 * Sends daily trading reports to all users via Telegram:
 * - Investors receive personalized profit reports
 * - Non-investors receive FOMO/encouragement messages
 * - Admins receive platform summary reports
 * 
 * Schedule: Run daily at 08:00 UTC (0 8 * * *)
 * Example crontab entry:
 *   0 8 * * * /usr/bin/php /path/to/RockyTap/cron/send_ai_trader_daily_reports.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\AITrader\AiTraderReportService;
use Ghidar\AITrader\AiTraderProfitEngine;
use Ghidar\Config\Config;
use Ghidar\Logging\Logger;

// Set execution time limit (30 minutes for large user bases)
set_time_limit(1800);

// Log start
Logger::info('AI Trader Daily Reports Cron: Starting', [
    'time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get()
]);

try {
    $reportService = new AiTraderReportService();
    
    // Step 1: Send admin summary reports to all admins
    $adminIds = Config::get('ADMIN_USER_IDS', '');
    $adminIdsArray = array_filter(array_map('trim', explode(',', $adminIds)));
    
    $adminReportsSent = 0;
    foreach ($adminIdsArray as $adminId) {
        if (is_numeric($adminId)) {
            $success = $reportService->sendAdminDailySummary((int)$adminId);
            if ($success) {
                $adminReportsSent++;
            }
        }
    }
    
    Logger::info('AI Trader Daily Reports: Admin reports sent', [
        'count' => $adminReportsSent
    ]);
    
    // Step 2: Send user reports (investors and non-investors)
    $result = $reportService->sendDailyReports();
    
    // Step 3: Log platform stats for the day
    $platformStats = AiTraderProfitEngine::getPlatformStats();
    
    $output = [
        'status' => 'success',
        'message' => 'Daily AI Trader reports sent successfully',
        'admin_reports' => $adminReportsSent,
        'user_reports' => $result,
        'platform_stats' => $platformStats,
        'executed_at' => date('Y-m-d H:i:s')
    ];
    
    Logger::info('AI Trader Daily Reports Cron: Completed', $output);
    
    echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
    
} catch (\Exception $e) {
    Logger::error('AI Trader Daily Reports Cron: Failed', [
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

