<?php
/**
 * Test script to verify LotteryService API logic
 * 
 * Usage: php test_lottery_api.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Lottery\LotteryService;

echo "==============================================\n";
echo "  LOTTERY API LOGIC TEST\n";
echo "  Time: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n\n";

try {
    // Test 1: Get active lottery directly
    echo "=== TEST 1: LotteryService::getActiveLottery() ===\n";
    $activeLottery = LotteryService::getActiveLottery();
    
    if ($activeLottery === null) {
        echo "❌ No active lottery found\n";
    } else {
        echo "✅ Active lottery found:\n";
        echo "   ID: " . $activeLottery['id'] . "\n";
        echo "   Title: " . $activeLottery['title'] . "\n";
        echo "   Status: " . $activeLottery['status'] . "\n";
        echo "   Prize Pool: $" . $activeLottery['prize_pool_usdt'] . "\n";
    }
    
    // Test 2: Get user status for active lottery
    echo "\n=== TEST 2: LotteryService::getUserStatusForActiveLottery() ===\n";
    
    // Try to get a real user ID from database
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT id FROM users LIMIT 1");
    $stmt->execute();
    $testUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $testUserId = $testUser ? (int)$testUser['id'] : 1;
    echo "Testing with user ID: $testUserId\n";
    
    $userStatus = LotteryService::getUserStatusForActiveLottery($testUserId);
    
    if ($userStatus === null) {
        echo "❌ getUserStatusForActiveLottery returned null\n";
        echo "   This is what the API endpoint will return to the frontend.\n";
    } else {
        echo "✅ User status retrieved:\n";
        echo json_encode($userStatus, JSON_PRETTY_PRINT) . "\n";
    }
    
    // Test 3: Get lottery history
    echo "\n=== TEST 3: LotteryService::getHistory() ===\n";
    $history = LotteryService::getHistory(5);
    
    if (empty($history)) {
        echo "❌ No lottery history found\n";
    } else {
        echo "✅ Found " . count($history) . " lotteries in history:\n";
        foreach ($history as $lottery) {
            echo sprintf("   - ID %d: %s (%s)\n", $lottery['id'], $lottery['title'], $lottery['status']);
        }
    }
    
    echo "\n==============================================\n";
    echo "  TEST COMPLETE\n";
    echo "==============================================\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

