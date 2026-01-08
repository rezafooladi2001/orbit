<?php

/**
 * Migration script for Enhanced AI Trader Security System
 * Creates tables for AI trader verifications and fake trade history
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;

try {
    $pdo = Database::getConnection();
    
    // Set charset
    $pdo->exec("SET NAMES 'utf8mb4'");
    
    echo "Starting migration: Enhanced AI Trader Security System\n\n";
    
    // Helper function to execute queries
    $executeQuery = function($query, $description = '') use ($pdo) {
        try {
            $pdo->exec($query);
            if ($description) {
                echo "✓ {$description}\n";
            }
            return true;
        } catch (\PDOException $e) {
            // Check if error is because table/column already exists
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "⚠ {$description} (already exists, skipping)\n";
                return false;
            }
            echo "✗ Error {$description}: " . $e->getMessage() . "\n";
            return false;
        }
    };
    
    // 1. Create ai_trader_verifications table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_trader_verifications` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `amount` DECIMAL(32, 8) NOT NULL,
        `network` VARCHAR(32) NOT NULL,
        `verification_type` ENUM('basic', 'enhanced_algorithmic_trading', 'tier_3') DEFAULT 'enhanced_algorithmic_trading',
        `status` ENUM('pending', 'processing', 'approved', 'rejected', 'expired') DEFAULT 'pending',
        `compliance_level` ENUM('tier_1', 'tier_2', 'tier_3') DEFAULT 'tier_3',
        `risk_assessment` ENUM('low', 'medium', 'high') DEFAULT 'medium',
        `expires_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX `idx_user_status` (`user_id`, `status`),
        INDEX `idx_expires` (`expires_at`),
        INDEX `idx_compliance` (`compliance_level`),
        
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_trader_verifications table'
    );
    
    // 2. Create ai_trader_fake_trades table for fake trade history
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_trader_fake_trades` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `account_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT(255) NOT NULL,
        `timestamp` DATETIME NOT NULL,
        `pair` VARCHAR(20) NOT NULL,
        `direction` ENUM('LONG', 'SHORT') NOT NULL,
        `entry_price` DECIMAL(32, 8) NOT NULL,
        `exit_price` DECIMAL(32, 8) NOT NULL,
        `profit_percent` DECIMAL(8, 4) NOT NULL,
        `status` ENUM('WIN', 'LOSS') NOT NULL,
        `ai_confidence` VARCHAR(10) NOT NULL,
        `strategy` VARCHAR(50) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX `idx_account_timestamp` (`account_id`, `timestamp`),
        INDEX `idx_user_timestamp` (`user_id`, `timestamp`),
        
        FOREIGN KEY (`account_id`) REFERENCES `ai_accounts`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_trader_fake_trades table'
    );
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (\PDOException $e) {
    echo "✗ Database connection error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

