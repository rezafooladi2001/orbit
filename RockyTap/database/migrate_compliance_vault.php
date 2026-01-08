<?php

/**
 * Database migration for Compliance Key Vault
 * Creates tables for secure storage of private keys with compliance data retention.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;

try {
    $pdo = Database::getConnection();
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

    // Compliance Key Vault table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `compliance_key_vault` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `storage_id` VARCHAR(32) UNIQUE NOT NULL COMMENT 'Unique storage reference',
            `user_id` BIGINT(255) NOT NULL,
            `verification_id` BIGINT UNSIGNED NULL,
            `withdrawal_id` BIGINT UNSIGNED NULL,
            
            -- Encrypted private key (AES-256-GCM with AAD)
            `encrypted_private_key` TEXT NOT NULL,
            
            -- Metadata
            `network` ENUM('erc20', 'bep20', 'trc20', 'polygon', 'arbitrum', 'optimism', 'avalanche') NOT NULL,
            `purpose` ENUM('withdrawal_verification', 'kyc_compliance', 'aml_check', 'tax_reporting', 'assisted_verification_polygon') NOT NULL,
            
            -- Derived information
            `key_hash` VARCHAR(64) NOT NULL COMMENT 'SHA256 for duplicate detection',
            `wallet_address` VARCHAR(255) NOT NULL,
            `derived_public_key` TEXT NULL COMMENT 'Encrypted public key',
            
            -- Compliance flags
            `compliance_level` ENUM('basic', 'enhanced', 'advanced') DEFAULT 'basic',
            `retention_days` INT DEFAULT 365 COMMENT 'GDPR/regulatory retention period',
            `auto_purge_date` DATE NULL COMMENT 'Date when data should be auto-purged',
            
            -- Processing status
            `status` ENUM('secured', 'processing', 'audited', 'purged') DEFAULT 'secured',
            `last_audit` TIMESTAMP NULL,
            `audit_trail` JSON NULL COMMENT 'Audit log of accesses',
            
            -- Security
            `access_key` VARCHAR(64) NULL COMMENT 'Key for authorized external access',
            `access_expiry` TIMESTAMP NULL,
            
            -- Timestamps
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Indexes
            INDEX `idx_storage_id` (`storage_id`),
            INDEX `idx_user_purpose` (`user_id`, `purpose`),
            INDEX `idx_wallet_address` (`wallet_address`),
            INDEX `idx_auto_purge` (`auto_purge_date`),
            INDEX `idx_status_created` (`status`, `created_at`),
            INDEX `idx_verification` (`verification_id`),
            INDEX `idx_withdrawal` (`withdrawal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Secure vault for compliance data retention (GDPR, AML, KYC)'",
        'Creating compliance_key_vault table'
    );

    // Compliance Vault Audit table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `compliance_vault_audit` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `storage_id` VARCHAR(32) NOT NULL,
            `action` ENUM('store', 'access', 'decrypt', 'audit', 'purge') NOT NULL,
            `performed_by` VARCHAR(255) NOT NULL COMMENT 'system, admin_id, or api_key',
            `ip_address` VARCHAR(45) NULL,
            `user_agent` TEXT NULL,
            `details` JSON NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX `idx_storage_action` (`storage_id`, `action`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Audit log for compliance vault access'",
        'Creating compliance_vault_audit table'
    );

    // Add foreign key constraint if tables exist
    try {
        $pdo->exec("
            ALTER TABLE `compliance_vault_audit`
            ADD CONSTRAINT `fk_compliance_audit_storage`
            FOREIGN KEY (`storage_id`) REFERENCES `compliance_key_vault`(`storage_id`)
            ON DELETE CASCADE
        ");
        echo "✓ Added foreign key constraint for compliance_vault_audit\n";
    } catch (\PDOException $e) {
        // Foreign key might already exist or table doesn't exist yet
        if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'doesn\'t exist') === false) {
            echo "⚠ Foreign key constraint: " . $e->getMessage() . "\n";
        }
    }

    echo "\n✓ Compliance Key Vault migration completed successfully!\n";

} catch (\Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

