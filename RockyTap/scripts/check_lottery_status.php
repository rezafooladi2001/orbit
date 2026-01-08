<?php
/**
 * Diagnostic script to check lottery status in database
 * 
 * Usage: php check_lottery_status.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;

echo "==============================================\n";
echo "  LOTTERY STATUS DIAGNOSTIC\n";
echo "  Time: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n\n";

try {
    $db = Database::getConnection();
    
    // Check all lotteries
    $stmt = $db->prepare("SELECT id, title, status, start_at, end_at, prize_pool_usdt, created_at FROM lotteries ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $lotteries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== ALL LOTTERIES (Last 10) ===\n";
    if (empty($lotteries)) {
        echo "⚠️  NO LOTTERIES FOUND IN DATABASE!\n";
    } else {
        foreach ($lotteries as $lottery) {
            $statusIcon = $lottery['status'] === 'active' ? '🟢' : ($lottery['status'] === 'finished' ? '🔴' : '🟡');
            echo sprintf(
                "%s ID: %d | Status: %-10s | Title: %s | End: %s | Pool: $%s\n",
                $statusIcon,
                $lottery['id'],
                $lottery['status'],
                substr($lottery['title'], 0, 30),
                $lottery['end_at'],
                $lottery['prize_pool_usdt']
            );
        }
    }
    
    // Check for active lottery specifically
    echo "\n=== ACTIVE LOTTERY CHECK ===\n";
    $stmt = $db->prepare("SELECT * FROM lotteries WHERE status = :status LIMIT 1");
    $stmt->execute(['status' => 'active']);
    $active = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($active) {
        echo "✅ Active lottery found:\n";
        echo json_encode($active, JSON_PRETTY_PRINT) . "\n";
        
        // Check if it's expired
        $endTime = strtotime($active['end_at']);
        $now = time();
        if ($endTime < $now) {
            echo "\n⚠️  WARNING: This lottery has EXPIRED but still marked as 'active'!\n";
            echo "   End time: " . $active['end_at'] . "\n";
            echo "   Current time: " . date('Y-m-d H:i:s') . "\n";
            echo "   You may need to run the auto_draw_expired_lotteries.php cron job.\n";
        } else {
            $remaining = $endTime - $now;
            $hours = floor($remaining / 3600);
            $minutes = floor(($remaining % 3600) / 60);
            echo "\n✅ Lottery is still active. Time remaining: {$hours}h {$minutes}m\n";
        }
    } else {
        echo "❌ NO ACTIVE LOTTERY FOUND!\n";
        echo "\n   This is likely why the lottery screen is stuck loading.\n";
        echo "   The frontend expects an active lottery to display.\n";
        echo "\n   To fix: Create a new lottery using:\n";
        echo "   php RockyTap/scripts/create_valentines_lottery.php\n";
        echo "   OR use the admin panel to create a new lottery.\n";
    }
    
    // Check for upcoming lotteries
    echo "\n=== UPCOMING LOTTERIES ===\n";
    $stmt = $db->prepare("SELECT id, title, start_at, end_at FROM lotteries WHERE status = 'upcoming' ORDER BY start_at ASC LIMIT 5");
    $stmt->execute();
    $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($upcoming)) {
        echo "No upcoming lotteries scheduled.\n";
    } else {
        foreach ($upcoming as $lottery) {
            echo sprintf("ID: %d | %s | Starts: %s\n", $lottery['id'], $lottery['title'], $lottery['start_at']);
        }
    }
    
    echo "\n==============================================\n";
    echo "  DIAGNOSTIC COMPLETE\n";
    echo "==============================================\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

