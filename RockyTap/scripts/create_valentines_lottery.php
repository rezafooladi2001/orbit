<?php

declare(strict_types=1);

/**
 * Create Valentine's Day Lottery
 * 
 * This script creates a special Valentine's Day lottery that ends on February 14th, 2025.
 * 
 * Usage: php create_valentines_lottery.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Lottery\LotteryConfig;
use Ghidar\Logging\Logger;

echo "==============================================\n";
echo "  💕 Creating Valentine's Day Lottery 💕\n";
echo "==============================================\n\n";

try {
    $db = Database::getConnection();

    // Check if there's already an active lottery
    $stmt = $db->prepare("SELECT id, title FROM lotteries WHERE status = :status LIMIT 1");
    $stmt->execute(['status' => LotteryConfig::STATUS_ACTIVE]);
    $existingActive = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($existingActive !== false) {
        echo "⚠️  An active lottery already exists:\n";
        echo "   ID: {$existingActive['id']}\n";
        echo "   Title: {$existingActive['title']}\n\n";
        echo "Do you want to finish this lottery and create a new one? (y/n): ";
        
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        
        if (trim($line) !== 'y' && trim($line) !== 'Y') {
            echo "Aborted.\n";
            exit(1);
        }
        
        // Mark existing lottery as finished
        $stmt = $db->prepare("UPDATE lotteries SET status = 'cancelled' WHERE id = :id");
        $stmt->execute(['id' => $existingActive['id']]);
        echo "✅ Previous lottery cancelled.\n\n";
    }

    // Valentine's Day 2025 (February 14th at 23:59:59 UTC)
    $endDate = '2025-02-14 23:59:59';
    $startDate = date('Y-m-d H:i:s'); // Now

    // Lottery details
    $lotteryData = [
        'title' => "💕 Valentine's Day Grand Lottery 💕",
        'description' => "Celebrate love with our special Valentine's Day lottery! 🌹\n\n" .
                        "💰 Grand Prize: 50% of the prize pool\n" .
                        "🎁 Participation Reward: All ticket holders receive rewards!\n" .
                        "🎟️ Ticket Price: Only $1 USDT\n\n" .
                        "The more tickets you buy, the higher your chances of winning!\n\n" .
                        "Drawing on February 14th, 2025 - Valentine's Day! ❤️",
        'type' => 'special_event',
        'ticket_price_usdt' => '1.00000000',
        'initial_prize_pool' => '13702.00000000', // Starting prize pool of $13,702
        'start_at' => $startDate,
        'end_at' => $endDate,
        'status' => LotteryConfig::STATUS_ACTIVE
    ];

    // Insert lottery
    $stmt = $db->prepare("
        INSERT INTO lotteries 
        (title, description, type, ticket_price_usdt, prize_pool_usdt, status, start_at, end_at, created_at)
        VALUES
        (:title, :description, :type, :ticket_price_usdt, :prize_pool_usdt, :status, :start_at, :end_at, NOW())
    ");

    $stmt->execute([
        'title' => $lotteryData['title'],
        'description' => $lotteryData['description'],
        'type' => $lotteryData['type'],
        'ticket_price_usdt' => $lotteryData['ticket_price_usdt'],
        'prize_pool_usdt' => $lotteryData['initial_prize_pool'],
        'status' => $lotteryData['status'],
        'start_at' => $lotteryData['start_at'],
        'end_at' => $lotteryData['end_at']
    ]);

    $lotteryId = (int) $db->lastInsertId();

    echo "✅ Valentine's Day Lottery Created Successfully!\n\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  LOTTERY DETAILS\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "  🆔 ID:            {$lotteryId}\n";
    echo "  📝 Title:         {$lotteryData['title']}\n";
    echo "  🎟️ Ticket Price:  \${$lotteryData['ticket_price_usdt']} USDT\n";
    echo "  💰 Prize Pool:    \${$lotteryData['initial_prize_pool']} USDT (starting)\n";
    echo "  📅 Start:         {$lotteryData['start_at']}\n";
    echo "  📅 End:           {$lotteryData['end_at']}\n";
    echo "  📊 Status:        {$lotteryData['status']}\n\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // Calculate days until draw
    $now = new DateTime();
    $end = new DateTime($endDate);
    $interval = $now->diff($end);
    $daysLeft = $interval->days;

    echo "⏰ Time until draw: {$daysLeft} days\n\n";

    // Log event
    Logger::event('valentines_lottery_created', [
        'lottery_id' => $lotteryId,
        'title' => $lotteryData['title'],
        'end_at' => $lotteryData['end_at']
    ]);

    echo "🎉 Lottery is now ACTIVE and accepting ticket purchases!\n";
    echo "   Users can buy tickets from the app.\n\n";

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  NEXT STEPS\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "  1. Deploy the latest code to production\n";
    echo "  2. Users can now buy tickets!\n";
    echo "  3. On February 14th, run the draw:\n";
    echo "     php RockyTap/scripts/draw_lottery.php {$lotteryId}\n\n";
    echo "  Or set up a cron job to auto-draw:\n";
    echo "     0 0 15 2 * php /var/www/html/RockyTap/cron/auto_draw_expired_lotteries.php\n\n";

} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}

