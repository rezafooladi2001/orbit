<?php

declare(strict_types=1);

/**
 * Admin Top-Up Script
 * 
 * Safely adds USDT to a user's wallet with proper audit trail.
 * 
 * Usage: php admin_topup.php [--execute]
 * 
 * By default runs in dry-run mode. Pass --execute to actually perform the top-up.
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\WalletRepository;
use Ghidar\Logging\Logger;

// Configuration
const TARGET_USER_ID = 8308397629;  // Telegram ID
const TOPUP_AMOUNT = '100.00000000'; // $100 USDT

// Check for --execute flag
$dryRun = !in_array('--execute', $argv ?? [], true);

echo "=== Admin Wallet Top-Up Script ===\n\n";
echo "Target User (Telegram ID): " . TARGET_USER_ID . "\n";
echo "Top-Up Amount: $" . TOPUP_AMOUNT . " USDT\n";
echo "Mode: " . ($dryRun ? "DRY-RUN (no changes will be made)" : "EXECUTE (changes will be committed)") . "\n\n";

try {
    $db = Database::getConnection();
    
    // Step 1: Check if user exists
    echo "Step 1: Checking if user exists...\n";
    $stmt = $db->prepare('SELECT `id`, `first_name`, `username` FROM `users` WHERE `id` = :user_id LIMIT 1');
    $stmt->execute(['user_id' => TARGET_USER_ID]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user === false) {
        echo "❌ ERROR: User with Telegram ID " . TARGET_USER_ID . " not found in database.\n";
        echo "Please verify the Telegram ID is correct.\n";
        exit(1);
    }
    
    echo "✓ User found: " . ($user['first_name'] ?? 'N/A');
    if (!empty($user['username'])) {
        echo " (@" . $user['username'] . ")";
    }
    echo "\n\n";
    
    // Step 2: Get or create wallet
    echo "Step 2: Getting wallet...\n";
    $wallet = WalletRepository::getOrCreateByUserId(TARGET_USER_ID);
    $currentBalance = $wallet['usdt_balance'];
    $newBalance = bcadd($currentBalance, TOPUP_AMOUNT, 8);
    
    echo "✓ Current USDT Balance: $" . $currentBalance . "\n";
    echo "✓ After Top-Up Balance: $" . $newBalance . "\n\n";
    
    if ($dryRun) {
        echo "=== DRY-RUN MODE ===\n";
        echo "No changes were made. To execute the top-up, run:\n";
        echo "  php admin_topup.php --execute\n\n";
        exit(0);
    }
    
    // Step 3: Execute the top-up in a transaction
    echo "Step 3: Executing top-up...\n";
    
    $db->beginTransaction();
    
    try {
        $timestamp = time();
        $txHash = 'ADMIN_TOPUP_' . $timestamp;
        
        // Create deposit record for audit trail
        $stmt = $db->prepare(
            'INSERT INTO `deposits` 
             (`user_id`, `network`, `product_type`, `status`, `address`, `expected_amount_usdt`, `actual_amount_usdt`, `tx_hash`, `confirmed_at`, `meta`) 
             VALUES (:user_id, :network, :product_type, :status, :address, :expected_amount, :actual_amount, :tx_hash, NOW(), :meta)'
        );
        $stmt->execute([
            'user_id' => TARGET_USER_ID,
            'network' => 'admin_topup',
            'product_type' => 'wallet_topup',
            'status' => 'confirmed',
            'address' => 'N/A',
            'expected_amount' => TOPUP_AMOUNT,
            'actual_amount' => TOPUP_AMOUNT,
            'tx_hash' => $txHash,
            'meta' => json_encode([
                'admin_action' => true,
                'reason' => 'Manual top-up for testing',
                'executed_at' => date('Y-m-d H:i:s')
            ])
        ]);
        
        $depositId = $db->lastInsertId();
        echo "✓ Created deposit record #" . $depositId . " (tx_hash: {$txHash})\n";
        
        // Update wallet balance
        $stmt = $db->prepare(
            'UPDATE `wallets` SET `usdt_balance` = `usdt_balance` + :amount WHERE `user_id` = :user_id'
        );
        $stmt->execute([
            'amount' => TOPUP_AMOUNT,
            'user_id' => TARGET_USER_ID
        ]);
        
        echo "✓ Updated wallet balance\n";
        
        // Verify the update
        $stmt = $db->prepare('SELECT `usdt_balance` FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => TARGET_USER_ID]);
        $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($updatedWallet === false) {
            throw new Exception('Failed to verify wallet update');
        }
        
        $db->commit();
        
        echo "\n=== TOP-UP SUCCESSFUL ===\n";
        echo "User ID: " . TARGET_USER_ID . "\n";
        echo "Amount: $" . TOPUP_AMOUNT . " USDT\n";
        echo "Previous Balance: $" . $currentBalance . "\n";
        echo "New Balance: $" . $updatedWallet['usdt_balance'] . "\n";
        echo "Deposit ID: #" . $depositId . "\n";
        echo "Transaction Hash: " . $txHash . "\n\n";
        
        // Log the action
        Logger::event('admin_wallet_topup', [
            'user_id' => TARGET_USER_ID,
            'amount_usdt' => TOPUP_AMOUNT,
            'deposit_id' => $depositId,
            'tx_hash' => $txHash,
            'previous_balance' => $currentBalance,
            'new_balance' => $updatedWallet['usdt_balance']
        ]);
        
        echo "✓ Action logged\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone.\n";

