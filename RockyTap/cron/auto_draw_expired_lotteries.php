<?php

declare(strict_types=1);

/**
 * Cron Job: Auto Draw Expired Lotteries
 * 
 * Automatically processes lotteries that have passed their end_at date.
 * Uses the Universal Winner System - every participant receives a reward.
 * 
 * Usage: php auto_draw_expired_lotteries.php
 * Cron:  0 * * * * /usr/bin/php /var/www/html/RockyTap/cron/auto_draw_expired_lotteries.php
 *        (Run every hour)
 * 
 * For Valentine's Day lottery ending Feb 14:
 * 0 0 15 2 * /usr/bin/php /var/www/html/RockyTap/cron/auto_draw_expired_lotteries.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Lottery\LotteryConfig;
use Ghidar\Lottery\UniversalWinnerService;
use Ghidar\Logging\Logger;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  🎰 GHIDAR LOTTERY - AUTO DRAW SYSTEM\n";
echo "  Time: " . date('Y-m-d H:i:s') . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $db = Database::getConnection();

    // Find active lotteries that have passed their end_at date
    $stmt = $db->prepare("
        SELECT id, title, end_at, prize_pool_usdt
        FROM lotteries 
        WHERE status = :status 
        AND end_at < NOW()
        ORDER BY end_at ASC
    ");
    $stmt->execute(['status' => LotteryConfig::STATUS_ACTIVE]);
    $expiredLotteries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($expiredLotteries)) {
        echo "✅ No expired lotteries found.\n";
        echo "\n[" . date('Y-m-d H:i:s') . "] Check complete.\n";
        exit(0);
    }

    echo "📋 Found " . count($expiredLotteries) . " expired lotteries to process.\n\n";

    $successCount = 0;
    $errorCount = 0;

    foreach ($expiredLotteries as $lottery) {
        $lotteryId = (int) $lottery['id'];
        $title = $lottery['title'];
        $prizePool = $lottery['prize_pool_usdt'];

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🎰 Processing: {$title}\n";
        echo "   Lottery ID: #{$lotteryId}\n";
        echo "   Prize Pool: \${$prizePool} USDT\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        try {
            // Use the Universal Winner System
            $result = UniversalWinnerService::processLotteryEnd($lotteryId);

            if ($result['success']) {
                $totalWinners = $result['total_winners'];
                
                echo "\n✅ LOTTERY PROCESSED SUCCESSFULLY!\n";
                echo "   Total Winners: {$totalWinners}\n";
                
                if ($totalWinners > 0 && isset($result['grand_prize_winner'])) {
                    $gpw = $result['grand_prize_winner'];
                    echo "\n   🏆 Grand Prize Winner:\n";
                    echo "      User ID: #{$gpw['user_id']}\n";
                    if (!empty($gpw['username'])) {
                        echo "      Username: @{$gpw['username']}\n";
                    }
                    echo "      Prize: \${$gpw['prize_amount']} USDT\n";
                }
                
                echo "\n   📤 All winners have been:\n";
                echo "      ✓ Credited instantly to their wallets\n";
                echo "      ✓ Notified via Telegram\n";
                echo "      ✓ Notified in-app (popup ready)\n";

                $successCount++;

                Logger::event('lottery_auto_drawn_universal', [
                    'lottery_id' => $lotteryId,
                    'title' => $title,
                    'prize_pool' => $prizePool,
                    'total_winners' => $totalWinners,
                    'grand_prize_winner' => $result['grand_prize_winner'] ?? null
                ]);
            } else {
                echo "\n⚠️ Lottery processed with no participants.\n";
                $successCount++;
            }

        } catch (\Exception $e) {
            echo "\n❌ ERROR: {$e->getMessage()}\n";
            $errorCount++;
            
            Logger::error('lottery_auto_draw_failed', [
                'lottery_id' => $lotteryId,
                'error' => $e->getMessage()
            ]);
        }

        echo "\n";
    }

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  📊 SUMMARY\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  ✅ Successfully processed: {$successCount}\n";
    echo "  ❌ Errors: {$errorCount}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n[" . date('Y-m-d H:i:s') . "] Auto-draw complete.\n";

    exit($errorCount > 0 ? 1 : 0);

} catch (\Exception $e) {
    echo "❌ FATAL ERROR: {$e->getMessage()}\n";
    Logger::error('lottery_auto_draw_fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
