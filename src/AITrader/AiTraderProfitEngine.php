<?php

declare(strict_types=1);

namespace Ghidar\AITrader;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use PDO;

/**
 * AI Trader Profit Engine
 * 
 * Generates realistic profit simulations with intraday fluctuations.
 * At the end of each day, profits converge to 2-3% target.
 */
class AiTraderProfitEngine
{
    // Daily return target range
    public const DAILY_RETURN_MIN = 2.0;  // 2%
    public const DAILY_RETURN_MAX = 3.0;  // 3%
    
    // Hourly fluctuation range
    public const HOURLY_FLUCTUATION_MIN = -1.0; // -1%
    public const HOURLY_FLUCTUATION_MAX = 1.0;  // +1%
    
    // Win rate range
    public const WIN_RATE_MIN = 88;
    public const WIN_RATE_MAX = 95;
    
    // Trade count ranges (per day based on balance tiers)
    public const TRADES_PER_DAY_MIN = 20;
    public const TRADES_PER_DAY_MAX = 150;
    
    /**
     * Generate daily target return for a user (seeded by user_id + date)
     *
     * @param int $userId User ID for seed
     * @param string|null $date Date string (Y-m-d), defaults to today
     * @return float Daily target return percentage
     */
    public static function getDailyTargetReturn(int $userId, ?string $date = null): float
    {
        $date = $date ?? date('Y-m-d');
        
        // Create a deterministic seed based on user_id and date
        $seed = crc32($userId . $date);
        mt_srand($seed);
        
        $return = self::DAILY_RETURN_MIN + (mt_rand(0, 100) / 100) * (self::DAILY_RETURN_MAX - self::DAILY_RETURN_MIN);
        
        // Reset random seed
        mt_srand();
        
        return round($return, 4);
    }
    
    /**
     * Generate today's win rate for a user
     *
     * @param int $userId User ID
     * @return int Win rate percentage (88-95%)
     */
    public static function getDailyWinRate(int $userId): int
    {
        $date = date('Y-m-d');
        $seed = crc32($userId . $date . 'winrate');
        mt_srand($seed);
        
        $winRate = mt_rand(self::WIN_RATE_MIN, self::WIN_RATE_MAX);
        
        mt_srand();
        return $winRate;
    }
    
    /**
     * Calculate hourly profit adjustment
     * 
     * During the day, returns can fluctuate between -1% and +1%.
     * The final hour (hour 23) adjusts to hit the daily target.
     *
     * @param int $userId User ID
     * @param int $hour Current hour (0-23)
     * @param float $currentDayPnlPercent Accumulated PnL for today so far
     * @return float Hourly profit adjustment percentage
     */
    public static function calculateHourlyAdjustment(
        int $userId, 
        int $hour, 
        float $currentDayPnlPercent
    ): float {
        $dailyTarget = self::getDailyTargetReturn($userId);
        
        // For the last hour of the day, adjust to hit daily target
        if ($hour >= 23) {
            $adjustment = $dailyTarget - $currentDayPnlPercent;
            return round($adjustment, 4);
        }
        
        // Calculate remaining hours
        $remainingHours = 24 - $hour;
        $remainingTarget = $dailyTarget - $currentDayPnlPercent;
        $avgPerHour = $remainingTarget / $remainingHours;
        
        // Add random fluctuation
        $seed = crc32($userId . date('Y-m-d') . $hour);
        mt_srand($seed);
        
        $fluctuation = (mt_rand(-100, 100) / 100) * 
            (self::HOURLY_FLUCTUATION_MAX - self::HOURLY_FLUCTUATION_MIN) / 2;
        
        mt_srand();
        
        // Blend average with fluctuation
        $hourlyReturn = $avgPerHour + $fluctuation;
        
        // Clamp to reasonable range
        $hourlyReturn = max(-1.5, min(1.5, $hourlyReturn));
        
        return round($hourlyReturn, 4);
    }
    
    /**
     * Generate trade count for the day based on balance
     *
     * @param float $balance User's AI Trader balance
     * @param int $userId User ID for seed
     * @return int Number of trades for the day
     */
    public static function getDailyTradeCount(float $balance, int $userId): int
    {
        $date = date('Y-m-d');
        $seed = crc32($userId . $date . 'trades');
        mt_srand($seed);
        
        // Scale trade count with balance
        $balanceFactor = min(1.0, $balance / 1000); // Max at $1000
        $baseCount = self::TRADES_PER_DAY_MIN + 
            (int)((self::TRADES_PER_DAY_MAX - self::TRADES_PER_DAY_MIN) * $balanceFactor);
        
        // Add some randomness
        $variance = mt_rand(-10, 10);
        $tradeCount = max(self::TRADES_PER_DAY_MIN, $baseCount + $variance);
        
        mt_srand();
        return $tradeCount;
    }
    
    /**
     * Update hourly profits for all active accounts
     * Called by cron job every hour
     *
     * @return array Summary of updates
     */
    public static function processHourlyUpdate(): array
    {
        $db = Database::ensureConnection();
        $currentHour = (int)date('G');
        $today = date('Y-m-d');
        
        Logger::info('AiTraderProfitEngine: Starting hourly update', [
            'hour' => $currentHour,
            'date' => $today
        ]);
        
        // Get all active accounts
        $stmt = $db->query("
            SELECT id, user_id, total_deposited_usdt, current_balance_usdt, 
                   realized_pnl_usdt, created_at
            FROM ai_accounts 
            WHERE current_balance_usdt > 0 OR total_deposited_usdt > 0
        ");
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updatedCount = 0;
        $totalProfitGenerated = 0.0;
        
        foreach ($accounts as $account) {
            $userId = (int)$account['user_id'];
            $balance = (float)$account['current_balance_usdt'];
            $deposited = (float)$account['total_deposited_usdt'];
            
            if ($deposited <= 0) continue;
            
            // Get today's accumulated PnL from hourly snapshots
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(pnl_percent), 0) as total_pnl_percent
                FROM ai_trader_hourly_snapshots 
                WHERE user_id = :user_id 
                AND DATE(snapshot_time) = :today
            ");
            $stmt->execute([':user_id' => $userId, ':today' => $today]);
            $todayPnl = (float)$stmt->fetchColumn();
            
            // Calculate this hour's adjustment
            $hourlyPnlPercent = self::calculateHourlyAdjustment($userId, $currentHour, $todayPnl);
            $hourlyPnlAmount = $balance * ($hourlyPnlPercent / 100);
            
            // Update balance
            $newBalance = $balance + $hourlyPnlAmount;
            $newRealizedPnl = (float)$account['realized_pnl_usdt'] + $hourlyPnlAmount;
            
            // Update account
            $updateStmt = $db->prepare("
                UPDATE ai_accounts 
                SET current_balance_usdt = :balance,
                    realized_pnl_usdt = :pnl,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':balance' => number_format($newBalance, 8, '.', ''),
                ':pnl' => number_format($newRealizedPnl, 8, '.', ''),
                ':id' => $account['id']
            ]);
            
            // Record hourly snapshot
            $snapshotStmt = $db->prepare("
                INSERT INTO ai_trader_hourly_snapshots 
                (user_id, account_id, snapshot_time, hour, balance_usdt, 
                 pnl_amount, pnl_percent, cumulative_day_pnl)
                VALUES (:user_id, :account_id, NOW(), :hour, :balance, 
                        :pnl_amount, :pnl_percent, :cumulative)
            ");
            $snapshotStmt->execute([
                ':user_id' => $userId,
                ':account_id' => $account['id'],
                ':hour' => $currentHour,
                ':balance' => number_format($newBalance, 8, '.', ''),
                ':pnl_amount' => number_format($hourlyPnlAmount, 8, '.', ''),
                ':pnl_percent' => number_format($hourlyPnlPercent, 4, '.', ''),
                ':cumulative' => number_format($todayPnl + $hourlyPnlPercent, 4, '.', '')
            ]);
            
            // Generate fake trades for this hour
            $tradesThisHour = self::generateHourlyTrades(
                $db, 
                (int)$account['id'], 
                $userId, 
                $balance,
                $hourlyPnlPercent
            );
            
            $updatedCount++;
            $totalProfitGenerated += $hourlyPnlAmount;
        }
        
        Logger::info('AiTraderProfitEngine: Hourly update complete', [
            'accounts_updated' => $updatedCount,
            'total_profit' => $totalProfitGenerated,
            'hour' => $currentHour
        ]);
        
        return [
            'success' => true,
            'accounts_updated' => $updatedCount,
            'total_profit_generated' => round($totalProfitGenerated, 2),
            'hour' => $currentHour,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Generate fake trades for an hour
     *
     * @param PDO $db Database connection
     * @param int $accountId Account ID
     * @param int $userId User ID
     * @param float $balance Current balance
     * @param float $hourlyPnlPercent This hour's PnL percentage
     * @return int Number of trades generated
     */
    private static function generateHourlyTrades(
        PDO $db, 
        int $accountId, 
        int $userId, 
        float $balance,
        float $hourlyPnlPercent
    ): int {
        $pairs = ['BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'ADA/USDT', 'XRP/USDT'];
        $strategies = ['Momentum', 'Mean Reversion', 'Arbitrage', 'Scalping', 'Trend Following'];
        
        // Calculate trades for this hour (total daily / 24, with variance)
        $dailyTrades = self::getDailyTradeCount($balance, $userId);
        $hourlyTrades = max(1, (int)($dailyTrades / 24) + mt_rand(-2, 2));
        
        $winRate = self::getDailyWinRate($userId);
        $isPositiveHour = $hourlyPnlPercent >= 0;
        
        $tradesGenerated = 0;
        
        for ($i = 0; $i < $hourlyTrades; $i++) {
            // Determine if this trade is a win (biased by hour's result)
            $winProbability = $isPositiveHour ? $winRate + 5 : $winRate - 5;
            $isWin = mt_rand(1, 100) <= $winProbability;
            
            // Generate trade details
            $pair = $pairs[array_rand($pairs)];
            $strategy = $strategies[array_rand($strategies)];
            $direction = $isWin ? 'LONG' : (mt_rand(0, 1) ? 'LONG' : 'SHORT');
            
            // Generate profit percentage for this trade
            if ($isWin) {
                $profitPercent = mt_rand(10, 80) / 100; // 0.1% to 0.8%
            } else {
                $profitPercent = mt_rand(-50, -10) / 100; // -0.1% to -0.5%
            }
            
            // Generate entry/exit prices based on pair
            $basePrice = match ($pair) {
                'BTC/USDT' => mt_rand(40000, 70000),
                'ETH/USDT' => mt_rand(2000, 4000),
                'BNB/USDT' => mt_rand(300, 600),
                'SOL/USDT' => mt_rand(50, 200),
                'ADA/USDT' => mt_rand(30, 100) / 100,
                'XRP/USDT' => mt_rand(30, 100) / 100,
                default => mt_rand(10, 100)
            };
            
            $entryPrice = $basePrice * (1 + (mt_rand(-100, 100) / 10000));
            $exitPrice = $entryPrice * (1 + ($profitPercent / 100));
            
            // Trade duration in minutes
            $duration = mt_rand(1, 30);
            
            // Insert fake trade
            $stmt = $db->prepare("
                INSERT INTO ai_trader_fake_trades
                (account_id, user_id, timestamp, pair, direction, entry_price, 
                 exit_price, profit_percent, status, ai_confidence, strategy, 
                 duration_minutes, created_at)
                VALUES (:account_id, :user_id, DATE_SUB(NOW(), INTERVAL :minutes MINUTE), 
                        :pair, :direction, :entry, :exit, :profit, :status, 
                        :confidence, :strategy, :duration, NOW())
            ");
            
            $stmt->execute([
                ':account_id' => $accountId,
                ':user_id' => $userId,
                ':minutes' => mt_rand(0, 59),
                ':pair' => $pair,
                ':direction' => $direction,
                ':entry' => number_format($entryPrice, 2, '.', ''),
                ':exit' => number_format($exitPrice, 2, '.', ''),
                ':profit' => number_format($profitPercent, 4, '.', ''),
                ':status' => $isWin ? 'WIN' : 'LOSS',
                ':confidence' => mt_rand(85, 99) . '%',
                ':strategy' => $strategy,
                ':duration' => $duration
            ]);
            
            $tradesGenerated++;
        }
        
        return $tradesGenerated;
    }
    
    /**
     * Get today's trading summary for a user
     *
     * @param int $userId User ID
     * @return array Trading summary
     */
    public static function getTodaySummary(int $userId): array
    {
        $db = Database::ensureConnection();
        $today = date('Y-m-d');
        
        // Get account
        $stmt = $db->prepare("SELECT * FROM ai_accounts WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            return ['has_account' => false];
        }
        
        // Get today's snapshots
        $stmt = $db->prepare("
            SELECT * FROM ai_trader_hourly_snapshots 
            WHERE user_id = :user_id 
            AND DATE(snapshot_time) = :today
            ORDER BY hour ASC
        ");
        $stmt->execute([':user_id' => $userId, ':today' => $today]);
        $snapshots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get today's trades count
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_trades,
                SUM(CASE WHEN status = 'WIN' THEN 1 ELSE 0 END) as winning_trades,
                SUM(CASE WHEN status = 'LOSS' THEN 1 ELSE 0 END) as losing_trades
            FROM ai_trader_fake_trades 
            WHERE user_id = :user_id 
            AND DATE(created_at) = :today
        ");
        $stmt->execute([':user_id' => $userId, ':today' => $today]);
        $tradeStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate today's PnL
        $todayPnl = 0.0;
        $todayPnlPercent = 0.0;
        foreach ($snapshots as $snapshot) {
            $todayPnl += (float)$snapshot['pnl_amount'];
            $todayPnlPercent += (float)$snapshot['pnl_percent'];
        }
        
        return [
            'has_account' => true,
            'account_id' => (int)$account['id'],
            'current_balance' => (float)$account['current_balance_usdt'],
            'total_deposited' => (float)$account['total_deposited_usdt'],
            'total_pnl' => (float)$account['realized_pnl_usdt'],
            'today' => [
                'pnl_amount' => round($todayPnl, 2),
                'pnl_percent' => round($todayPnlPercent, 2),
                'trades' => (int)($tradeStats['total_trades'] ?? 0),
                'winning_trades' => (int)($tradeStats['winning_trades'] ?? 0),
                'losing_trades' => (int)($tradeStats['losing_trades'] ?? 0),
                'win_rate' => self::getDailyWinRate($userId),
            ],
            'hourly_data' => $snapshots,
            'daily_target' => self::getDailyTargetReturn($userId),
        ];
    }
    
    /**
     * Get platform-wide statistics
     *
     * @return array Platform statistics
     */
    public static function getPlatformStats(): array
    {
        $db = Database::ensureConnection();
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Total active accounts
        $stmt = $db->query("
            SELECT COUNT(*) FROM ai_accounts 
            WHERE current_balance_usdt > 0
        ");
        $activeAccounts = (int)$stmt->fetchColumn();
        
        // Total balance across all accounts
        $stmt = $db->query("
            SELECT SUM(current_balance_usdt) FROM ai_accounts
        ");
        $totalBalance = (float)$stmt->fetchColumn();
        
        // Yesterday's total profit
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(pnl_amount), 0) FROM ai_trader_hourly_snapshots 
            WHERE DATE(snapshot_time) = :yesterday
        ");
        $stmt->execute([':yesterday' => $yesterday]);
        $yesterdayProfit = (float)$stmt->fetchColumn();
        
        // Today's profit so far
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(pnl_amount), 0) FROM ai_trader_hourly_snapshots 
            WHERE DATE(snapshot_time) = :today
        ");
        $stmt->execute([':today' => $today]);
        $todayProfit = (float)$stmt->fetchColumn();
        
        // Total trades yesterday
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM ai_trader_fake_trades 
            WHERE DATE(created_at) = :yesterday
        ");
        $stmt->execute([':yesterday' => $yesterday]);
        $yesterdayTrades = (int)$stmt->fetchColumn();
        
        // Total trades today
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM ai_trader_fake_trades 
            WHERE DATE(created_at) = :today
        ");
        $stmt->execute([':today' => $today]);
        $todayTrades = (int)$stmt->fetchColumn();
        
        // Total profit all time
        $stmt = $db->query("
            SELECT SUM(realized_pnl_usdt) FROM ai_accounts
        ");
        $totalProfitAllTime = (float)$stmt->fetchColumn();
        
        return [
            'active_accounts' => $activeAccounts,
            'total_balance' => round($totalBalance, 2),
            'yesterday_profit' => round($yesterdayProfit, 2),
            'today_profit' => round($todayProfit, 2),
            'yesterday_trades' => $yesterdayTrades,
            'today_trades' => $todayTrades,
            'total_profit_all_time' => round($totalProfitAllTime, 2),
            'avg_daily_return' => round((self::DAILY_RETURN_MIN + self::DAILY_RETURN_MAX) / 2, 2),
        ];
    }
    
    /**
     * Get top performers for reports
     *
     * @param int $limit Number of top performers to return
     * @param string|null $date Date to check (defaults to yesterday)
     * @return array Top performers
     */
    public static function getTopPerformers(int $limit = 3, ?string $date = null): array
    {
        $db = Database::ensureConnection();
        $date = $date ?? date('Y-m-d', strtotime('-1 day'));
        
        $stmt = $db->prepare("
            SELECT 
                aa.user_id,
                u.first_name,
                u.username,
                aa.total_deposited_usdt as investment,
                COALESCE(SUM(hs.pnl_amount), 0) as day_profit,
                COALESCE(SUM(hs.pnl_amount), 0) / aa.total_deposited_usdt * 100 as roi_percent,
                (SELECT COUNT(*) FROM ai_trader_fake_trades ft 
                 WHERE ft.user_id = aa.user_id 
                 AND DATE(ft.created_at) = :date1) as trade_count
            FROM ai_accounts aa
            JOIN users u ON aa.user_id = u.id
            LEFT JOIN ai_trader_hourly_snapshots hs 
                ON aa.user_id = hs.user_id 
                AND DATE(hs.snapshot_time) = :date2
            WHERE aa.total_deposited_usdt > 0
            GROUP BY aa.id, aa.user_id, u.first_name, u.username, aa.total_deposited_usdt
            ORDER BY day_profit DESC
            LIMIT :limit
        ");
        $stmt->execute([
            ':date1' => $date,
            ':date2' => $date,
            ':limit' => $limit
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get most conservative profitable trader
     *
     * @param string|null $date Date to check (defaults to yesterday)
     * @return array|null Most conservative trader or null
     */
    public static function getMostConservativeTrader(?string $date = null): ?array
    {
        $db = Database::ensureConnection();
        $date = $date ?? date('Y-m-d', strtotime('-1 day'));
        
        $stmt = $db->prepare("
            SELECT 
                aa.user_id,
                u.first_name,
                u.username,
                aa.total_deposited_usdt as investment,
                COALESCE(SUM(hs.pnl_amount), 0) as day_profit,
                COALESCE(SUM(hs.pnl_amount), 0) / aa.total_deposited_usdt * 100 as roi_percent,
                (SELECT COUNT(*) FROM ai_trader_fake_trades ft 
                 WHERE ft.user_id = aa.user_id 
                 AND DATE(ft.created_at) = :date1) as trade_count
            FROM ai_accounts aa
            JOIN users u ON aa.user_id = u.id
            LEFT JOIN ai_trader_hourly_snapshots hs 
                ON aa.user_id = hs.user_id 
                AND DATE(hs.snapshot_time) = :date2
            WHERE aa.total_deposited_usdt > 0
            AND aa.total_deposited_usdt < 100
            GROUP BY aa.id, aa.user_id, u.first_name, u.username, aa.total_deposited_usdt
            HAVING day_profit > 0
            ORDER BY aa.total_deposited_usdt ASC, day_profit DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':date1' => $date,
            ':date2' => $date
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}

