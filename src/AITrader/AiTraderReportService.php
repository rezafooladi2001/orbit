<?php

declare(strict_types=1);

namespace Ghidar\AITrader;

use Ghidar\Core\Database;
use Ghidar\Telegram\BotClient;
use Ghidar\Logging\Logger;
use PDO;

/**
 * AI Trader Report Service
 * 
 * Generates and sends daily trading reports via Telegram.
 * Handles both investor reports and FOMO messages for non-investors.
 */
class AiTraderReportService
{
    private BotClient $bot;
    
    public function __construct()
    {
        $this->bot = new BotClient();
    }
    
    /**
     * Generate and send daily reports to all users
     *
     * @return array Summary of sent reports
     */
    public function sendDailyReports(): array
    {
        $db = Database::ensureConnection();
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        Logger::info('AiTraderReportService: Starting daily report generation', [
            'date' => $yesterday
        ]);
        
        // Get platform stats
        $platformStats = AiTraderProfitEngine::getPlatformStats();
        $topPerformers = AiTraderProfitEngine::getTopPerformers(1, $yesterday);
        $conservativeTrader = AiTraderProfitEngine::getMostConservativeTrader($yesterday);
        
        // Get all users
        $stmt = $db->query("SELECT id, first_name, username FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sentInvestor = 0;
        $sentNonInvestor = 0;
        $failed = 0;
        
        foreach ($users as $user) {
            $userId = (int)$user['id'];
            
            try {
                // Check if user has AI Trader deposits
                $stmt = $db->prepare("
                    SELECT * FROM ai_accounts 
                    WHERE user_id = :user_id 
                    AND total_deposited_usdt > 0
                ");
                $stmt->execute([':user_id' => $userId]);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($account) {
                    // Send investor report
                    $this->sendInvestorReport($userId, $account, $yesterday, $platformStats);
                    $sentInvestor++;
                } else {
                    // Send FOMO message to non-investors
                    $this->sendFomoMessage($userId, $platformStats);
                    $sentNonInvestor++;
                }
                
                // Rate limit: 30 messages per second max
                usleep(35000); // ~28 msgs/sec
                
            } catch (\Exception $e) {
                Logger::warning('AiTraderReportService: Failed to send report', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                $failed++;
            }
        }
        
        Logger::info('AiTraderReportService: Daily reports completed', [
            'investor_reports' => $sentInvestor,
            'fomo_messages' => $sentNonInvestor,
            'failed' => $failed
        ]);
        
        return [
            'investor_reports_sent' => $sentInvestor,
            'fomo_messages_sent' => $sentNonInvestor,
            'failed' => $failed,
            'total' => $sentInvestor + $sentNonInvestor,
            'date' => $yesterday
        ];
    }
    
    /**
     * Send investor daily report
     *
     * @param int $userId User ID
     * @param array $account AI Trader account data
     * @param string $date Report date
     * @param array $platformStats Platform-wide statistics
     */
    private function sendInvestorReport(
        int $userId, 
        array $account, 
        string $date,
        array $platformStats
    ): void {
        $db = Database::ensureConnection();
        
        // Get user's yesterday stats
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(pnl_amount), 0) as day_profit,
                COALESCE(SUM(pnl_percent), 0) as day_pnl_percent
            FROM ai_trader_hourly_snapshots 
            WHERE user_id = :user_id 
            AND DATE(snapshot_time) = :date
        ");
        $stmt->execute([':user_id' => $userId, ':date' => $date]);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get trade count
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_trades,
                SUM(CASE WHEN status = 'WIN' THEN 1 ELSE 0 END) as winning_trades
            FROM ai_trader_fake_trades 
            WHERE user_id = :user_id 
            AND DATE(created_at) = :date
        ");
        $stmt->execute([':user_id' => $userId, ':date' => $date]);
        $tradeStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $dayProfit = (float)$userStats['day_profit'];
        $dayPnlPercent = (float)$userStats['day_pnl_percent'];
        $currentBalance = (float)$account['current_balance_usdt'];
        $totalDeposited = (float)$account['total_deposited_usdt'];
        $totalPnl = (float)$account['realized_pnl_usdt'];
        $trades = (int)$tradeStats['total_trades'];
        $winningTrades = (int)$tradeStats['winning_trades'];
        $winRate = $trades > 0 ? round(($winningTrades / $trades) * 100, 1) : 0;
        
        $formattedDate = date('F j, Y', strtotime($date));
        
        $message = "📊 <b>Your Daily AI Trading Report</b>\n\n";
        $message .= "📅 <b>Date:</b> {$formattedDate}\n\n";
        
        $message .= "💰 <b>Your Performance:</b>\n";
        $message .= "├ Today's Profit: <b>+\${$this->formatMoney($dayProfit)} USDT</b>\n";
        $message .= "├ ROI Today: <b>+{$dayPnlPercent}%</b>\n";
        $message .= "├ Trades Executed: <b>{$trades}</b>\n";
        $message .= "└ Win Rate: <b>{$winRate}%</b>\n\n";
        
        $message .= "💼 <b>Account Summary:</b>\n";
        $message .= "├ Current Balance: <b>\${$this->formatMoney($currentBalance)} USDT</b>\n";
        $message .= "├ Total Invested: <b>\${$this->formatMoney($totalDeposited)} USDT</b>\n";
        $message .= "└ Total Profit: <b>+\${$this->formatMoney($totalPnl)} USDT</b>\n\n";
        
        $message .= "🌐 <b>Platform Stats:</b>\n";
        $message .= "├ Active Bots: <b>{$platformStats['active_accounts']}</b>\n";
        $message .= "├ Today's Trades: <b>{$platformStats['yesterday_trades']}</b>\n";
        $message .= "└ Total Paid: <b>\${$this->formatMoney($platformStats['yesterday_profit'])} USDT</b>\n\n";
        
        $message .= "💡 <i>Your AI bot is working 24/7 to maximize your returns!</i>\n\n";
        $message .= "🔄 <i>Report generated automatically at 08:00 UTC</i>";
        
        $this->bot->sendMessage($userId, $message, [
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Send FOMO message to non-investors
     *
     * @param int $userId User ID
     * @param array $platformStats Platform-wide statistics
     */
    private function sendFomoMessage(int $userId, array $platformStats): void
    {
        $activeAccounts = $platformStats['active_accounts'];
        $yesterdayProfit = $platformStats['yesterday_profit'];
        
        // Random variation to make it look more real
        $profitVariation = $yesterdayProfit * (1 + (mt_rand(-5, 5) / 100));
        $accountsVariation = $activeAccounts + mt_rand(-10, 10);
        
        $message = "⚠️ <b>You're Missing Out!</b>\n\n";
        
        $message .= "💸 While you wait, other traders earned <b>\${$this->formatMoney($profitVariation)} USDT</b> yesterday\n";
        $message .= "📊 <b>{$accountsVariation}</b> profitable bots are already making money\n\n";
        
        $message .= "⏰ <b>Time is Money!</b>\n";
        $message .= "Every day you delay is potential profit lost. The crypto market doesn't wait!\n\n";
        
        $message .= "🎯 <b>Start with just \$50 USDT</b>\n";
        $message .= "Join the profitable traders today and start earning daily returns!\n\n";
        
        $message .= "💡 <i>\"The best time to start was yesterday. The second best time is NOW!\"</i>";
        
        $this->bot->sendMessage($userId, $message, [
            'parse_mode' => 'HTML'
        ]);
    }
    
    /**
     * Generate and send admin daily summary report
     *
     * @param int $adminUserId Admin user ID to send report to
     * @return bool Success status
     */
    public function sendAdminDailySummary(int $adminUserId): bool
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $formattedDate = date('F j, Y', strtotime($yesterday));
        
        try {
            $platformStats = AiTraderProfitEngine::getPlatformStats();
            $topPerformers = AiTraderProfitEngine::getTopPerformers(1, $yesterday);
            $conservativeTrader = AiTraderProfitEngine::getMostConservativeTrader($yesterday);
            
            $message = "📊 <b>Daily Trading Report</b>\n\n";
            $message .= "📅 <b>Daily Trading Report - {$formattedDate}</b>\n\n";
            
            $message .= "💰 Total System Profit: <b>\${$this->formatMoney($platformStats['yesterday_profit'])} USDT</b>\n";
            $message .= "🤖 Active Bots: <b>{$platformStats['active_accounts']}</b>\n";
            $message .= "📈 Total Trades: <b>{$platformStats['yesterday_trades']}</b>\n\n";
            
            // Calculate average profit per bot
            $avgProfit = $platformStats['active_accounts'] > 0 
                ? $platformStats['yesterday_profit'] / $platformStats['active_accounts'] 
                : 0;
            $message .= "📊 Average Bot Profit: <b>\${$this->formatMoney($avgProfit)} USDT</b>\n\n";
            
            // Top performer
            if (!empty($topPerformers)) {
                $top = $topPerformers[0];
                $maskedName = $this->maskName($top['first_name'] ?? $top['username'] ?? 'User');
                $investment = (float)$top['investment'];
                $profit = (float)$top['day_profit'];
                $roi = $investment > 0 ? ($profit / $investment) * 100 : 0;
                $trades = (int)$top['trade_count'];
                
                $message .= "🏆 <b>Top Performer:</b>\n";
                $message .= "👤 User: {$maskedName}\n";
                $message .= "💵 Investment: \${$this->formatMoney($investment)} USDT\n";
                $message .= "💰 Profit: \${$this->formatMoney($profit)} USDT\n";
                $message .= "📊 Trades: {$trades}\n";
                $message .= "📈 ROI: " . number_format($roi, 2) . "%\n\n";
            }
            
            // Most conservative profitable trader
            if ($conservativeTrader) {
                $maskedName = $this->maskName($conservativeTrader['first_name'] ?? $conservativeTrader['username'] ?? 'User');
                $investment = (float)$conservativeTrader['investment'];
                $profit = (float)$conservativeTrader['day_profit'];
                $roi = $investment > 0 ? ($profit / $investment) * 100 : 0;
                $trades = (int)$conservativeTrader['trade_count'];
                
                $message .= "🥉 <b>Most Conservative (Profitable):</b>\n";
                $message .= "👤 User: {$maskedName}\n";
                $message .= "💵 Investment: \${$this->formatMoney($investment)} USDT\n";
                $message .= "💰 Profit: \${$this->formatMoney($profit)} USDT\n";
                $message .= "📊 Trades: {$trades}\n";
                $message .= "📈 ROI: " . number_format($roi, 2) . "%\n\n";
            }
            
            $message .= "🔄 <i>Report generated automatically</i>\n";
            $message .= "⏰ <i>Generated at 08:00 UTC</i>";
            
            $this->bot->sendMessage($adminUserId, $message, [
                'parse_mode' => 'HTML'
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Logger::error('AiTraderReportService: Failed to send admin summary', [
                'admin_id' => $adminUserId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Format money value
     *
     * @param float $amount Amount to format
     * @return string Formatted amount
     */
    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
    
    /**
     * Mask user name for privacy (show first 3 chars + ***)
     *
     * @param string $name Name to mask
     * @return string Masked name
     */
    private function maskName(string $name): string
    {
        if (strlen($name) <= 3) {
            return $name . '***';
        }
        return substr($name, 0, 3) . '***';
    }
    
    /**
     * Get users who haven't invested yet
     *
     * @return array Non-investor users
     */
    public function getNonInvestors(): array
    {
        $db = Database::ensureConnection();
        
        $stmt = $db->query("
            SELECT u.id, u.first_name, u.username
            FROM users u
            LEFT JOIN ai_accounts aa ON u.id = aa.user_id
            WHERE aa.id IS NULL OR aa.total_deposited_usdt = 0
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get active investors
     *
     * @return array Active investor users with their stats
     */
    public function getActiveInvestors(): array
    {
        $db = Database::ensureConnection();
        
        $stmt = $db->query("
            SELECT 
                u.id, u.first_name, u.username,
                aa.total_deposited_usdt,
                aa.current_balance_usdt,
                aa.realized_pnl_usdt
            FROM users u
            JOIN ai_accounts aa ON u.id = aa.user_id
            WHERE aa.total_deposited_usdt > 0
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

