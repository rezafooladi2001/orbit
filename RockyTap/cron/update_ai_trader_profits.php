<?php
/**
 * Cron job: Update AI Trader fake profits
 * Generates fake 2-3% daily profits for all active AI trader accounts
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;

try {
    Logger::info('Starting AI Trader fake profit update');
    
    $db = Database::getConnection();
    
    // Get all active AI trader accounts
    $stmt = $db->query("
        SELECT id, user_id, total_deposited_usdt, current_balance_usdt, 
               realized_pnl_usdt, created_at
        FROM ai_accounts 
        WHERE current_balance_usdt > 0 OR total_deposited_usdt > 0
    ");
    $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $updatedCount = 0;
    
    foreach ($accounts as $account) {
        // Calculate days since account creation
        $createdAt = $account['created_at'] ?? date('Y-m-d H:i:s');
        $daysActive = max(1, floor((time() - strtotime($createdAt)) / 86400));
        
        // Generate daily return between 2-3%
        $dailyReturnPercent = 2.0 + (rand(0, 10) / 10); // 2.0% to 3.0%
        $dailyReturn = $dailyReturnPercent / 100;
        
        // Calculate compounded return
        $totalReturn = pow(1 + $dailyReturn, $daysActive) - 1;
        
        // Update balance with fake profits
        $deposited = (float) $account['total_deposited_usdt'];
        if ($deposited > 0) {
            $newBalance = $deposited * (1 + $totalReturn);
            $realizedPnl = $newBalance - $deposited;
            
            $updateStmt = $db->prepare("
                UPDATE ai_accounts 
                SET current_balance_usdt = :balance,
                    realized_pnl_usdt = :pnl,
                    updated_at = NOW()
                WHERE id = :id
            ");
            
            $updateStmt->execute([
                ':balance' => number_format($newBalance, 8, '.', ''),
                ':pnl' => number_format($realizedPnl, 8, '.', ''),
                ':id' => $account['id']
            ]);
            
            // Create fake trade history entry
            createFakeTradeHistory($db, (int) $account['id'], (int) $account['user_id'], $dailyReturnPercent);
            
            $updatedCount++;
        }
    }
    
    Logger::info('AI Trader fake profits updated', [
        'accounts_updated' => $updatedCount,
        'daily_return_range' => '2.0-3.0%'
    ]);
    
    echo json_encode([
        'status' => 'success',
        'accounts_updated' => $updatedCount,
        'daily_return' => '2.0-3.0%',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    Logger::error('Failed to update AI Trader profits', [
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

/**
 * Create fake trade history entry
 *
 * @param \PDO $db Database connection
 * @param int $accountId Account ID
 * @param int $userId User ID
 * @param float $dailyReturnPercent Daily return percentage
 */
function createFakeTradeHistory(\PDO $db, int $accountId, int $userId, float $dailyReturnPercent): void
{
    $pairs = ['BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'ADA/USDT'];
    $strategies = ['Momentum', 'Mean Reversion', 'Arbitrage', 'Scalping', 'Trend Following'];
    
    $isProfit = rand(1, 100) <= 92; // 92% win rate
    $profitPercent = $isProfit ? (rand(5, 30) / 10) : (rand(-30, -5) / 10);
    
    $stmt = $db->prepare("
        INSERT INTO ai_trader_fake_trades
        (account_id, user_id, timestamp, pair, direction, entry_price, exit_price,
         profit_percent, status, ai_confidence, strategy, created_at)
        VALUES (:account_id, :user_id, NOW(), :pair, :direction, :entry, :exit,
                :profit, :status, :confidence, :strategy, NOW())
    ");
    
    $stmt->execute([
        ':account_id' => $accountId,
        ':user_id' => $userId,
        ':pair' => $pairs[array_rand($pairs)],
        ':direction' => $isProfit ? 'LONG' : 'SHORT',
        ':entry' => rand(45000, 50000),
        ':exit' => rand(45000, 50000),
        ':profit' => number_format($profitPercent, 4, '.', ''),
        ':status' => $isProfit ? 'WIN' : 'LOSS',
        ':confidence' => rand(85, 99) . '%',
        ':strategy' => $strategies[array_rand($strategies)]
    ]);
}

