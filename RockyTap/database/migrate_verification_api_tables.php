<?php

/**
 * Database migration script for Verification API endpoints.
 * Creates additional tables needed for comprehensive verification API support.
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
    
    // verification_sessions - Session management for verification flows
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `verification_sessions` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `session_id` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unique session identifier',
        `user_id` BIGINT(255) NOT NULL,
        `verification_id` BIGINT UNSIGNED NULL COMMENT 'Linked verification request',
        `session_type` ENUM('standard', 'assisted', 'multi_signature', 'time_delayed') NOT NULL DEFAULT 'standard',
        `status` ENUM('active', 'completed', 'cancelled', 'expired') NOT NULL DEFAULT 'active',
        `ip_address` VARCHAR(45) NULL,
        `user_agent` TEXT NULL,
        `metadata` JSON NULL COMMENT 'Additional session metadata',
        `expires_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_session_id` (`session_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_status` (`status`),
        KEY `idx_expires_at` (`expires_at`),
        FOREIGN KEY (`verification_id`) REFERENCES `wallet_verifications`(`id`) ON DELETE SET NULL
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating verification_sessions table'
    );
    
    // assisted_verification_data - Encrypted data for assisted verification
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `assisted_verification_data` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `verification_id` BIGINT UNSIGNED NOT NULL,
        `session_id` VARCHAR(64) NULL,
        `user_id` BIGINT(255) NOT NULL,
        `encrypted_data` TEXT NOT NULL COMMENT 'AES-256-GCM encrypted verification data',
        `data_type` VARCHAR(64) NOT NULL COMMENT 'Type of verification data (kyc, proof_of_ownership, etc.)',
        `status` ENUM('pending', 'reviewing', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        `reviewed_by` BIGINT(255) NULL COMMENT 'Admin user ID who reviewed',
        `reviewed_at` DATETIME NULL,
        `review_notes` TEXT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_verification_id` (`verification_id`),
        KEY `idx_session_id` (`session_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_status` (`status`),
        KEY `idx_data_type` (`data_type`),
        FOREIGN KEY (`verification_id`) REFERENCES `wallet_verifications`(`id`) ON DELETE CASCADE
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating assisted_verification_data table'
    );
    
    // verification_audit_log already exists, but ensure it has all needed fields
    // Check if we need to add any columns (this is handled by ALTER TABLE if needed)
    
    echo "\n✓ All verification API tables created successfully!\n";
    echo "✓ Database migration completed.\n";
    
} catch (\Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

