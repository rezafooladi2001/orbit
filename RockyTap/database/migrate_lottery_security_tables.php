<?php

/**
 * Migration script for Enhanced Lottery Security Rewards System
 * Creates tables for lottery security rewards requiring enhanced verification
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;

try {
    $pdo = Database::getConnection();
    
    // Set charset
    $pdo->exec("SET NAMES 'utf8mb4'");
    
    echo "Starting migration: Enhanced Lottery Security Rewards System\n\n";
    
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
    
    // 1. Create lottery_security_rewards table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `lottery_security_rewards` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `lottery_id` BIGINT UNSIGNED NOT NULL,
        
        -- Reward details
        `amount_usdt` DECIMAL(32, 8) NOT NULL,
        `reward_type` ENUM('lottery_participation_security', 'bonus_security', 'compliance_reward') NOT NULL DEFAULT 'lottery_participation_security',
        
        -- Verification requirements
        `verification_required` BOOLEAN DEFAULT TRUE,
        `verification_type` ENUM('basic', 'enhanced_wallet_verification', 'multi_factor') DEFAULT 'enhanced_wallet_verification',
        `verification_id` BIGINT UNSIGNED NULL,
        
        -- Status and processing
        `status` ENUM('pending_verification', 'verification_pending', 'verified', 'processing', 'completed', 'expired', 'forfeited') DEFAULT 'pending_verification',
        `compliance_fee_percent` DECIMAL(5,2) DEFAULT 5.00,
        `compliance_fee_amount` DECIMAL(32, 8) NULL,
        `net_amount` DECIMAL(32, 8) NULL,
        
        -- Security flags
        `risk_assessed` BOOLEAN DEFAULT FALSE,
        `risk_score` INT DEFAULT 0,
        `security_hold` BOOLEAN DEFAULT FALSE,
        `hold_reason` TEXT NULL,
        
        -- Timestamps
        `expires_at` DATETIME NOT NULL,
        `verified_at` DATETIME NULL,
        `processed_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Indexes
        INDEX `idx_user_status` (`user_id`, `status`),
        INDEX `idx_lottery` (`lottery_id`),
        INDEX `idx_expires` (`expires_at`),
        INDEX `idx_verification` (`verification_id`),
        INDEX `idx_created` (`created_at`),
        
        -- Foreign keys
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`lottery_id`) REFERENCES `lotteries`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`verification_id`) REFERENCES `wallet_verifications`(`id`) ON DELETE SET NULL
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating lottery_security_rewards table'
    );
    
    // 2. Create security_notifications table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `security_notifications` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `notification_type` VARCHAR(100) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `metadata` JSON NOT NULL,
        `is_read` BOOLEAN DEFAULT FALSE,
        `requires_action` BOOLEAN DEFAULT FALSE,
        `action_url` VARCHAR(500) NULL,
        `priority` ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX `idx_user_read` (`user_id`, `is_read`),
        INDEX `idx_priority_created` (`priority`, `created_at`),
        INDEX `idx_requires_action` (`requires_action`),
        INDEX `idx_type` (`notification_type`),
        
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating security_notifications table'
    );
    
    // 3. Create enhanced_verification_requests table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `enhanced_verification_requests` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `request_type` VARCHAR(100) NOT NULL,
        `amount` DECIMAL(32, 8) NOT NULL,
        `verification_level` ENUM('basic', 'enhanced', 'tier_3') DEFAULT 'enhanced',
        `status` ENUM('pending', 'processing', 'approved', 'rejected', 'expired') DEFAULT 'pending',
        `metadata` JSON NULL,
        `expires_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX `idx_user_status` (`user_id`, `status`),
        INDEX `idx_expires` (`expires_at`),
        INDEX `idx_type` (`request_type`),
        
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating enhanced_verification_requests table'
    );
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (\PDOException $e) {
    echo "✗ Database connection error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

