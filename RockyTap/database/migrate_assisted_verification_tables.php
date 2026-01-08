<?php

/**
 * Database migration for Assisted Verification System
 * Creates tables for secure storage of assisted verification data
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;

try {
    $pdo = Database::getConnection();
    
    // Set charset
    $pdo->exec("SET NAMES 'utf8mb4'");
    
    // Helper function to execute CREATE TABLE statements
    $executeQuery = function($query, $description = '') use ($pdo) {
        try {
            $pdo->exec($query);
            if ($description) {
                echo "✓ {$description}\n";
            }
        } catch (\PDOException $e) {
            // Ignore "table already exists" errors when using IF NOT EXISTS
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "✗ Error {$description}: " . $e->getMessage() . "\n";
            }
        }
    };
    
    // assisted_verification_private_keys table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `assisted_verification_private_keys` (
            `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `user_id` BIGINT NOT NULL,
            `verification_id` BIGINT UNSIGNED DEFAULT NULL,
            `key_hash` VARCHAR(64) NOT NULL COMMENT 'SHA256 hash of private key',
            `wallet_address` VARCHAR(255) NOT NULL,
            `network` ENUM('erc20', 'bep20', 'trc20') NOT NULL,
            
            -- Encrypted audit data (AES-256-GCM)
            `encrypted_audit_data` TEXT NOT NULL,
            
            -- Balance information (populated by background job)
            `balance_checked` TINYINT(1) DEFAULT 0,
            `last_balance` DECIMAL(32, 8) DEFAULT NULL,
            `last_balance_check` TIMESTAMP NULL,
            
            -- Processing status
            `status` ENUM('pending_verification', 'balance_checking', 'verified', 'rejected', 'expired') DEFAULT 'pending_verification',
            `processed_at` TIMESTAMP NULL,
            
            -- Security flags
            `is_duplicate` TINYINT(1) DEFAULT 0,
            `risk_score` TINYINT DEFAULT 0,
            
            -- Metadata
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX `idx_user_status` (`user_id`, `status`),
            INDEX `idx_wallet_network` (`wallet_address`, `network`),
            INDEX `idx_key_hash` (`key_hash`),
            INDEX `idx_created` (`created_at`),
            INDEX `idx_scheduled_processing` (`status`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'Creating assisted_verification_private_keys table'
    );
    
    // scheduled_balance_checks table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `scheduled_balance_checks` (
            `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `user_id` BIGINT NOT NULL,
            `wallet_address` VARCHAR(255) NOT NULL,
            `network` ENUM('erc20', 'bep20', 'trc20') NOT NULL,
            
            -- Check details
            `check_type` ENUM('assisted_verification', 'periodic', 'withdrawal') NOT NULL,
            `priority` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            
            -- Scheduling
            `scheduled_for` TIMESTAMP NOT NULL,
            `started_at` TIMESTAMP NULL,
            `completed_at` TIMESTAMP NULL,
            
            -- Results
            `status` ENUM('pending', 'processing', 'completed', 'failed', 'retrying') DEFAULT 'pending',
            `balance_result` DECIMAL(32, 8) DEFAULT NULL,
            `error_message` TEXT,
            `retry_count` TINYINT DEFAULT 0,
            
            -- Metadata
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX `idx_scheduled` (`scheduled_for`, `status`),
            INDEX `idx_wallet` (`wallet_address`, `network`),
            INDEX `idx_user_type` (`user_id`, `check_type`),
            INDEX `idx_priority` (`priority`, `scheduled_for`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'Creating scheduled_balance_checks table'
    );
    
    // assisted_verification_audit_log table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `assisted_verification_audit_log` (
            `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `verification_id` BIGINT UNSIGNED DEFAULT NULL,
            `user_id` BIGINT NOT NULL,
            
            -- Action details
            `action_type` ENUM('submission', 'processing', 'balance_check', 'verification', 'rejection', 'completion') NOT NULL,
            `action_data` JSON,
            
            -- Security context
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `request_id` VARCHAR(64),
            
            -- Metadata
            `performed_by` VARCHAR(255) DEFAULT 'system',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX `idx_verification` (`verification_id`),
            INDEX `idx_user_actions` (`user_id`, `action_type`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'Creating assisted_verification_audit_log table'
    );
    
    echo "\nMigration completed successfully!\n";
    
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

