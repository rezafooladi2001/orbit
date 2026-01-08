<?php

/**
 * Database migration script for AI Trader Withdrawal Verification Framework.
 * Creates all necessary tables for withdrawal verification, source of funds verification,
 * audit logging, and admin monitoring.
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
    
    // ai_withdrawal_verifications - Main withdrawal verification requests
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_withdrawal_verifications` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `withdrawal_amount_usdt` DECIMAL(32, 8) NOT NULL,
        `verification_tier` ENUM('small', 'medium', 'large') NOT NULL DEFAULT 'small',
        `status` ENUM('pending', 'verifying', 'approved', 'rejected', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
        `verification_step` INT NOT NULL DEFAULT 1,
        `wallet_address` VARCHAR(255) NULL,
        `wallet_network` VARCHAR(32) NULL,
        `wallet_ownership_proof` TEXT NULL COMMENT 'Encrypted wallet ownership proof',
        `source_of_funds_verified` BOOLEAN DEFAULT FALSE,
        `requires_assisted_verification` BOOLEAN DEFAULT FALSE,
        `assisted_verification_reason` TEXT NULL,
        `estimated_completion_time` DATETIME NULL,
        `completed_at` DATETIME NULL,
        `rejection_reason` TEXT NULL,
        `metadata` JSON NULL COMMENT 'Additional verification data (encrypted)',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_status` (`status`),
        KEY `idx_tier` (`verification_tier`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_verification_step` (`verification_step`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_withdrawal_verifications table'
    );
    
    // ai_withdrawal_verification_steps - Track each step of verification
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_withdrawal_verification_steps` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `verification_id` BIGINT UNSIGNED NOT NULL,
        `step_number` INT NOT NULL,
        `step_type` VARCHAR(64) NOT NULL COMMENT 'e.g., confirm_details, wallet_ownership, security_confirm, processing',
        `status` ENUM('pending', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
        `verification_data` TEXT NULL COMMENT 'Encrypted step-specific data',
        `completed_at` DATETIME NULL,
        `metadata` JSON NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_step_number` (`step_number`),
        KEY `idx_status` (`status`),
        FOREIGN KEY (`verification_id`) REFERENCES `ai_withdrawal_verifications`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_withdrawal_verification_steps table'
    );
    
    // ai_source_of_funds_verifications - Source of funds verification records
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_source_of_funds_verifications` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `verification_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT(255) NOT NULL,
        `profit_amount_usdt` DECIMAL(32, 8) NOT NULL,
        `wallet_address` VARCHAR(255) NOT NULL,
        `wallet_network` VARCHAR(32) NOT NULL,
        `verification_method` ENUM('wallet_signature', 'transaction_proof', 'assisted', 'manual_review') NOT NULL,
        `wallet_signature` TEXT NULL COMMENT 'Encrypted wallet signature',
        `transaction_proof` TEXT NULL COMMENT 'Encrypted transaction proof',
        `verification_hash` VARCHAR(255) NULL COMMENT 'Hash of verification data for integrity',
        `verified_by_admin_id` BIGINT(255) NULL COMMENT 'Admin who manually verified',
        `verification_status` ENUM('pending', 'verified', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
        `verified_at` DATETIME NULL,
        `expires_at` DATETIME NULL,
        `metadata` JSON NULL COMMENT 'Additional verification data (encrypted)',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_verification_status` (`verification_status`),
        KEY `idx_wallet_address` (`wallet_address`),
        FOREIGN KEY (`verification_id`) REFERENCES `ai_withdrawal_verifications`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_source_of_funds_verifications table'
    );
    
    // ai_withdrawal_audit_log - Comprehensive audit logging for compliance
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_withdrawal_audit_log` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `verification_id` BIGINT UNSIGNED NULL,
        `user_id` BIGINT(255) NOT NULL,
        `action_type` VARCHAR(64) NOT NULL COMMENT 'e.g., verification_initiated, step_completed, approval, rejection',
        `action_details` JSON NULL COMMENT 'Encrypted action details',
        `ip_address` VARCHAR(45) NULL,
        `user_agent` TEXT NULL,
        `risk_score` DECIMAL(5, 2) NULL COMMENT 'Calculated risk score 0-100',
        `flags` JSON NULL COMMENT 'Security flags and alerts',
        `admin_user_id` BIGINT(255) NULL COMMENT 'Admin who performed action if applicable',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_action_type` (`action_type`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_risk_score` (`risk_score`),
        FOREIGN KEY (`verification_id`) REFERENCES `ai_withdrawal_verifications`(`id`) ON DELETE SET NULL
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_withdrawal_audit_log table'
    );
    
    // ai_withdrawal_security_alerts - Automated security alerts
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_withdrawal_security_alerts` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `verification_id` BIGINT UNSIGNED NULL,
        `user_id` BIGINT(255) NOT NULL,
        `alert_type` VARCHAR(64) NOT NULL COMMENT 'e.g., suspicious_pattern, high_risk, multiple_failed_attempts',
        `alert_severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
        `alert_details` JSON NOT NULL,
        `status` ENUM('new', 'reviewing', 'resolved', 'false_positive') NOT NULL DEFAULT 'new',
        `reviewed_by_admin_id` BIGINT(255) NULL,
        `reviewed_at` DATETIME NULL,
        `resolution_notes` TEXT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_alert_type` (`alert_type`),
        KEY `idx_alert_severity` (`alert_severity`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`),
        FOREIGN KEY (`verification_id`) REFERENCES `ai_withdrawal_verifications`(`id`) ON DELETE SET NULL
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_withdrawal_security_alerts table'
    );
    
    // ai_withdrawal_compliance_reports - Compliance reports for each verification
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_withdrawal_compliance_reports` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `verification_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT(255) NOT NULL,
        `report_type` VARCHAR(64) NOT NULL DEFAULT 'standard' COMMENT 'standard, enhanced, full_audit',
        `report_data` JSON NOT NULL COMMENT 'Complete compliance report data (encrypted)',
        `report_hash` VARCHAR(255) NOT NULL COMMENT 'SHA256 hash of report for integrity',
        `generated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `retention_until` DATE NOT NULL COMMENT 'Report retention until date (7+ years)',
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_generated_at` (`generated_at`),
        KEY `idx_retention_until` (`retention_until`),
        FOREIGN KEY (`verification_id`) REFERENCES `ai_withdrawal_verifications`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_withdrawal_compliance_reports table'
    );
    
    // ai_assisted_verifications - Assisted verification pathway records
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_assisted_verifications` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `verification_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT(255) NOT NULL,
        `support_ticket_id` VARCHAR(64) NULL,
        `reason` TEXT NOT NULL COMMENT 'Reason for assisted verification',
        `support_notes` TEXT NULL COMMENT 'Internal support notes',
        `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
        `assigned_to_admin_id` BIGINT(255) NULL,
        `user_provided_info` TEXT NULL COMMENT 'Encrypted user-provided information',
        `verification_result` TEXT NULL COMMENT 'Encrypted verification result',
        `completed_at` DATETIME NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_status` (`status`),
        KEY `idx_support_ticket_id` (`support_ticket_id`),
        FOREIGN KEY (`verification_id`) REFERENCES `ai_withdrawal_verifications`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_assisted_verifications table'
    );
    
    echo "\n✓ All withdrawal verification tables created successfully!\n";
    echo "✓ Database migration completed.\n";
    
} catch (\Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

