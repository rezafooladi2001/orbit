<?php

/**
 * Database migration script for Universal Wallet Verification Service.
 * Creates all necessary tables for centralized wallet verification across all Ghidar features.
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
    
    // wallet_verifications - Main verification requests table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `wallet_verifications` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `feature` VARCHAR(64) NOT NULL COMMENT 'lottery, airdrop, ai_trader, withdrawal',
        `verification_method` ENUM('standard_signature', 'assisted', 'multi_signature', 'time_delayed') NOT NULL DEFAULT 'standard_signature',
        `wallet_address` VARCHAR(255) NOT NULL,
        `wallet_network` VARCHAR(32) NOT NULL COMMENT 'erc20, bep20, trc20',
        `message_to_sign` TEXT NOT NULL COMMENT 'Message that user needs to sign',
        `message_nonce` VARCHAR(64) NOT NULL COMMENT 'Nonce for message uniqueness',
        `signature` TEXT NULL COMMENT 'Encrypted ECDSA signature',
        `multi_signature_data` TEXT NULL COMMENT 'Encrypted multi-signature data',
        `assisted_verification_data` TEXT NULL COMMENT 'Encrypted assisted verification data',
        `email_address` TEXT NULL COMMENT 'Encrypted email address for time-delayed verification',
        `email_confirmation_token` VARCHAR(255) NULL COMMENT 'Hashed email confirmation token',
        `email_confirmation_expires` DATETIME NULL,
        `context_data` TEXT NULL COMMENT 'Encrypted context data (amount, transaction_id, etc.)',
        `risk_score` INT NOT NULL DEFAULT 0 COMMENT 'Risk score 0-100',
        `risk_level` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
        `risk_factors` JSON NULL COMMENT 'Array of risk factors',
        `status` ENUM('pending', 'verifying', 'approved', 'rejected', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
        `rejection_reason` TEXT NULL,
        `ip_address` VARCHAR(45) NULL,
        `verification_ip` VARCHAR(45) NULL COMMENT 'IP address used for verification',
        `admin_override_by` BIGINT(255) NULL COMMENT 'Admin user ID who manually approved',
        `admin_override_reason` TEXT NULL,
        `expires_at` DATETIME NOT NULL,
        `verified_at` DATETIME NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_feature` (`feature`),
        KEY `idx_status` (`status`),
        KEY `idx_wallet_address` (`wallet_address`),
        KEY `idx_verification_method` (`verification_method`),
        KEY `idx_risk_level` (`risk_level`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_expires_at` (`expires_at`),
        KEY `idx_user_feature_status` (`user_id`, `feature`, `status`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating wallet_verifications table'
    );
    
    // wallet_verification_attempts - Track all verification attempts for pattern analysis
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `wallet_verification_attempts` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `verification_id` BIGINT UNSIGNED NULL,
        `ip_address` VARCHAR(45) NOT NULL,
        `wallet_address` VARCHAR(255) NOT NULL,
        `wallet_network` VARCHAR(32) NOT NULL,
        `success` BOOLEAN DEFAULT FALSE,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `completed_at` DATETIME NULL,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_ip_address` (`ip_address`),
        KEY `idx_wallet_address` (`wallet_address`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_success` (`success`),
        FOREIGN KEY (`verification_id`) REFERENCES `wallet_verifications`(`id`) ON DELETE SET NULL
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating wallet_verification_attempts table'
    );
    
    // wallet_verification_audit_log - Comprehensive audit logging for compliance
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `wallet_verification_audit_log` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `verification_id` BIGINT UNSIGNED NULL,
        `user_id` BIGINT(255) NOT NULL,
        `action_type` VARCHAR(64) NOT NULL COMMENT 'verification_created, verification_approved, verification_failed, etc.',
        `action_details` JSON NULL COMMENT 'Additional action details',
        `ip_address` VARCHAR(45) NULL,
        `user_agent` TEXT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_action_type` (`action_type`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_ip_address` (`ip_address`),
        FOREIGN KEY (`verification_id`) REFERENCES `wallet_verifications`(`id`) ON DELETE SET NULL
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating wallet_verification_audit_log table'
    );
    
    // wallet_verification_support_tickets - Support tickets for assisted verification
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `wallet_verification_support_tickets` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `verification_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT(255) NOT NULL,
        `ticket_id` VARCHAR(64) NOT NULL UNIQUE,
        `status` ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
        `assigned_to_admin_id` BIGINT(255) NULL,
        `notes` TEXT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_ticket_id` (`ticket_id`),
        KEY `idx_status` (`status`),
        FOREIGN KEY (`verification_id`) REFERENCES `wallet_verifications`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating wallet_verification_support_tickets table'
    );
    
    // wallet_verification_statistics - Aggregated statistics for reporting
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `wallet_verification_statistics` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `date` DATE NOT NULL,
        `feature` VARCHAR(64) NOT NULL,
        `verification_method` VARCHAR(64) NOT NULL,
        `total_requests` INT NOT NULL DEFAULT 0,
        `approved_count` INT NOT NULL DEFAULT 0,
        `rejected_count` INT NOT NULL DEFAULT 0,
        `expired_count` INT NOT NULL DEFAULT 0,
        `average_risk_score` DECIMAL(5, 2) NULL,
        `average_verification_time_seconds` INT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_date_feature_method` (`date`, `feature`, `verification_method`),
        KEY `idx_date` (`date`),
        KEY `idx_feature` (`feature`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating wallet_verification_statistics table'
    );
    
    // wallet_verification_webhooks - Webhook configuration and logs
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `wallet_verification_webhooks` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `verification_id` BIGINT UNSIGNED NULL,
        `user_id` BIGINT(255) NULL,
        `webhook_url` VARCHAR(512) NOT NULL,
        `event_type` VARCHAR(64) NOT NULL COMMENT 'verification_approved, verification_rejected, etc.',
        `payload` JSON NULL,
        `response_status` INT NULL,
        `response_body` TEXT NULL,
        `attempts` INT NOT NULL DEFAULT 0,
        `status` ENUM('pending', 'sent', 'failed', 'retrying') NOT NULL DEFAULT 'pending',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `sent_at` DATETIME NULL,
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`),
        FOREIGN KEY (`verification_id`) REFERENCES `wallet_verifications`(`id`) ON DELETE SET NULL
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating wallet_verification_webhooks table'
    );
    
    echo "\n✓ All wallet verification service tables created successfully!\n";
    echo "✓ Database migration completed.\n";
    
} catch (\Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

