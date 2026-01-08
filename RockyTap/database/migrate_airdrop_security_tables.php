<?php

/**
 * Migration script for Enhanced Airdrop Security Withdrawal System
 * Creates tables for airdrop withdrawals requiring enhanced verification
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;

try {
    $pdo = Database::getConnection();
    
    // Set charset
    $pdo->exec("SET NAMES 'utf8mb4'");
    
    echo "Starting migration: Enhanced Airdrop Security Withdrawal System\n\n";
    
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
    
    // 1. Create airdrop_pending_withdrawals table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `airdrop_pending_withdrawals` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        
        -- Amount details
        `ghd_amount` DECIMAL(32, 8) NOT NULL,
        `usdt_amount` DECIMAL(32, 8) NOT NULL,
        
        -- Verification requirements
        `verification_type` ENUM('basic', 'enhanced_wallet_verification', 'document_verification') DEFAULT 'enhanced_wallet_verification',
        `verification_id` BIGINT UNSIGNED NULL,
        `compliance_check` BOOLEAN DEFAULT TRUE,
        
        -- Status
        `status` ENUM('pending_verification', 'verification_complete', 'processing', 'completed', 'failed', 'expired') DEFAULT 'pending_verification',
        `risk_level` ENUM('low', 'medium', 'high') DEFAULT 'medium',
        `risk_factors` JSON NULL,
        
        -- Processing
        `admin_wallet_payment_id` BIGINT UNSIGNED NULL,
        `compliance_fee_percent` DECIMAL(5,2) DEFAULT 5.00,
        `compliance_fee_amount` DECIMAL(32, 8) NULL,
        `net_amount` DECIMAL(32, 8) NULL,
        
        -- Timestamps
        `expires_at` DATETIME NOT NULL,
        `verified_at` DATETIME NULL,
        `processed_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Indexes
        INDEX `idx_user_status` (`user_id`, `status`),
        INDEX `idx_expires` (`expires_at`),
        INDEX `idx_verification` (`verification_id`),
        
        -- Foreign keys
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`verification_id`) REFERENCES `wallet_verifications`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`admin_wallet_payment_id`) REFERENCES `admin_payments`(`id`) ON DELETE SET NULL
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating airdrop_pending_withdrawals table'
    );
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (\PDOException $e) {
    echo "✗ Database connection error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

