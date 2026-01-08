<?php
/**
 * Cron job: AI Trader Hourly Profit Update
 * 
 * Runs every hour to update AI Trader balances with realistic fluctuations.
 * Uses the AiTraderProfitEngine to generate intraday profit variations that
 * converge to 2-3% daily returns.
 * 
 * Schedule: Run every hour (0 * * * *)
 * Example crontab entry:
 *   0 * * * * /usr/bin/php /path/to/RockyTap/cron/update_ai_trader_hourly.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\AITrader\AiTraderProfitEngine;
use Ghidar\Logging\Logger;

// Set execution time limit (5 minutes max)
set_time_limit(300);

// Log start
Logger::info('AI Trader Hourly Cron: Starting', [
    'time' => date('Y-m-d H:i:s'),
    'hour' => date('G')
]);

try {
    // Process hourly update using the profit engine
    $result = AiTraderProfitEngine::processHourlyUpdate();
    
    // Output result
    $output = [
        'status' => 'success',
        'message' => 'Hourly AI Trader update completed',
        'data' => $result,
        'executed_at' => date('Y-m-d H:i:s')
    ];
    
    Logger::info('AI Trader Hourly Cron: Completed', $result);
    
    echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
    
} catch (\Exception $e) {
    Logger::error('AI Trader Hourly Cron: Failed', [
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

