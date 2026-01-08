<?php

/**
 * Migration script for Enhanced Lottery Winner Notification and Prize Distribution System
 * Adds tables and columns for participation rewards, pending verification balance, and wallet verification
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;

try {
    $pdo = Database::getConnection();
    
    // Set charset
    $pdo->exec("SET NAMES 'utf8mb4'");
    
    echo "Starting migration: Enhanced Lottery Rewards & Verification System\n\n";
    
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
    
    // 1. Add pending_verification_balance column to wallets table
    $executeQuery(
        "ALTER TABLE `wallets` 
         ADD COLUMN `pending_verification_balance` DECIMAL(32, 8) NOT NULL DEFAULT 0 COMMENT 'Balance awaiting wallet ownership verification before withdrawal'",
        'Adding pending_verification_balance column to wallets table'
    );
    
    // 2. Create lottery_participation_rewards table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `lottery_participation_rewards` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `lottery_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT(255) NOT NULL,
        `reward_type` ENUM('grand_prize', 'participation') NOT NULL DEFAULT 'participation',
        `reward_amount_usdt` DECIMAL(32, 8) NOT NULL,
        `ticket_count` INT UNSIGNED NOT NULL DEFAULT 1,
        `status` ENUM('pending_verification', 'verified', 'claimed') NOT NULL DEFAULT 'pending_verification',
        `verified_at` TIMESTAMP NULL DEFAULT NULL,
        `claimed_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_lottery_user` (`lottery_id`, `user_id`),
        KEY `idx_user_status` (`user_id`, `status`),
        KEY `idx_lottery_id` (`lottery_id`),
        FOREIGN KEY (`lottery_id`) REFERENCES `lotteries`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating lottery_participation_rewards table'
    );
    
    // 3. Create wallet_verification_requests table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `wallet_verification_requests` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `reward_id` BIGINT UNSIGNED NULL COMMENT 'Reference to lottery_participation_rewards.id if applicable',
        `verification_method` ENUM('signature', 'manual') NOT NULL DEFAULT 'signature',
        `verification_status` ENUM('pending', 'processing', 'approved', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
        `message_to_sign` TEXT NULL COMMENT 'Message that user needs to sign with wallet',
        `message_nonce` VARCHAR(64) NULL COMMENT 'Unique nonce for message signing',
        `signature` TEXT NULL COMMENT 'ECDSA signature from user wallet',
        `wallet_address` VARCHAR(255) NULL COMMENT 'Wallet address for verification',
        `wallet_network` VARCHAR(32) NULL COMMENT 'Network (ERC20, BEP20, TRC20)',
        `manual_verification_data` JSON NULL COMMENT 'Data for manual verification process',
        `rejection_reason` TEXT NULL COMMENT 'Reason if verification is rejected',
        `verified_by` VARCHAR(100) NULL COMMENT 'Admin/system who verified (for manual verification)',
        `expires_at` TIMESTAMP NULL COMMENT 'When verification request expires',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_user_status` (`user_id`, `verification_status`),
        KEY `idx_reward_id` (`reward_id`),
        KEY `idx_expires_at` (`expires_at`),
        FOREIGN KEY (`reward_id`) REFERENCES `lottery_participation_rewards`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating wallet_verification_requests table'
    );
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (\PDOException $e) {
    echo "✗ Database connection error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

