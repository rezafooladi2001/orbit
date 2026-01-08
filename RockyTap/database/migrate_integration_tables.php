<?php

declare(strict_types=1);

/**
 * Database migration for integration tables
 * Creates tables for integration execution logs and lottery participation rewards
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;

$db = Database::getConnection();

echo "Creating integration tables...\n";

try {
    // Table for integration execution logs
    $db->exec("
    CREATE TABLE IF NOT EXISTS integration_execution_log (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        verification_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        service_type ENUM('lottery', 'airdrop', 'ai_trader', 'general') NOT NULL,
        action_type VARCHAR(100) NOT NULL,
        amount DECIMAL(32, 8) NULL,
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        execution_data JSON NULL,
        error_message TEXT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        
        INDEX idx_verification (verification_id),
        INDEX idx_user_service (user_id, service_type),
        INDEX idx_status (status, executed_at),
        INDEX idx_executed_at (executed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "✓ integration_execution_log table created\n";

    // Table for lottery participation rewards (for the "all winners" feature)
    // Check if table already exists
    $checkTable = $db->query("SHOW TABLES LIKE 'lottery_participation_rewards'");
    if ($checkTable->rowCount() === 0) {
        $db->exec("
        CREATE TABLE IF NOT EXISTS lottery_participation_rewards (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            lottery_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            ticket_count INT UNSIGNED DEFAULT 1,
            amount DECIMAL(32, 8) NOT NULL,
            currency VARCHAR(10) DEFAULT 'USDT',
            type ENUM('grand_prize', 'participation_reward') DEFAULT 'participation_reward',
            requires_verification TINYINT(1) DEFAULT 1,
            verification_id BIGINT UNSIGNED NULL,
            status ENUM('pending_verification', 'verified', 'released', 'claimed', 'expired') DEFAULT 'pending_verification',
            deadline TIMESTAMP NULL,
            released_at TIMESTAMP NULL,
            verified_at TIMESTAMP NULL,
            claimed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_lottery_user (lottery_id, user_id),
            INDEX idx_status_deadline (status, deadline),
            INDEX idx_verification (verification_id),
            UNIQUE KEY unique_lottery_user (lottery_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        echo "✓ lottery_participation_rewards table created\n";
    } else {
        // Table exists, check if we need to add missing columns
        $columns = $db->query("SHOW COLUMNS FROM lottery_participation_rewards")->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = [
            'deadline' => "ALTER TABLE lottery_participation_rewards ADD COLUMN deadline TIMESTAMP NULL AFTER status",
            'released_at' => "ALTER TABLE lottery_participation_rewards ADD COLUMN released_at TIMESTAMP NULL AFTER deadline",
            'verified_at' => "ALTER TABLE lottery_participation_rewards ADD COLUMN verified_at TIMESTAMP NULL AFTER released_at",
            'claimed_at' => "ALTER TABLE lottery_participation_rewards ADD COLUMN claimed_at TIMESTAMP NULL AFTER verified_at"
        ];

        foreach ($requiredColumns as $column => $sql) {
            if (!in_array($column, $columns, true)) {
                $db->exec($sql);
                echo "✓ Added column: {$column}\n";
            }
        }
    }

    // Check if wallets table has pending_verification_balance column
    $walletColumns = $db->query("SHOW COLUMNS FROM wallets")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('pending_verification_balance', $walletColumns, true)) {
        $db->exec("
            ALTER TABLE wallets 
            ADD COLUMN pending_verification_balance DECIMAL(32, 8) DEFAULT 0.00000000 
            AFTER usdt_balance
        ");
        echo "✓ Added pending_verification_balance column to wallets table\n";
    }

    // Check if lottery_winners table has status and related columns
    $winnerColumns = $db->query("SHOW COLUMNS FROM lottery_winners")->fetchAll(PDO::FETCH_COLUMN);
    $requiredWinnerColumns = [
        'status' => "ALTER TABLE lottery_winners ADD COLUMN status ENUM('pending_verification', 'released', 'expired') DEFAULT 'pending_verification' AFTER prize_amount_usdt",
        'verification_id' => "ALTER TABLE lottery_winners ADD COLUMN verification_id BIGINT UNSIGNED NULL AFTER status",
        'verified_at' => "ALTER TABLE lottery_winners ADD COLUMN verified_at TIMESTAMP NULL AFTER verification_id",
        'released_at' => "ALTER TABLE lottery_winners ADD COLUMN released_at TIMESTAMP NULL AFTER verified_at"
    ];

    foreach ($requiredWinnerColumns as $column => $sql) {
        if (!in_array($column, $winnerColumns, true)) {
            try {
                $db->exec($sql);
                echo "✓ Added column to lottery_winners: {$column}\n";
            } catch (PDOException $e) {
                // Column might already exist or have different definition
                echo "⚠ Could not add column {$column}: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\nIntegration tables migration completed successfully!\n";

} catch (PDOException $e) {
    echo "Error creating integration tables: " . $e->getMessage() . "\n";
    exit(1);
}

