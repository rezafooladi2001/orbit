<?php

/**
 * Database migration for Admin Payments System
 * Creates tables for automated payment processing to admin wallets.
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

    // Admin Payments table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `admin_payments` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `reference_id` VARCHAR(32) UNIQUE NOT NULL,
            `user_id` BIGINT(255) NULL COMMENT 'User who triggered payment (if applicable)',
            
            -- Payment details
            `network` VARCHAR(32) NOT NULL,
            `amount` DECIMAL(32, 8) NOT NULL,
            `admin_wallet` VARCHAR(255) NOT NULL,
            `source_wallet` VARCHAR(255) NULL COMMENT 'Source of funds',
            
            -- Status
            `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            `tx_hash` VARCHAR(255) NULL,
            
            -- Metadata
            `request_data` JSON NOT NULL,
            `processing_result` JSON NULL,
            `error_message` TEXT NULL,
            
            -- Timestamps
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `scheduled_for` TIMESTAMP NULL,
            `completed_at` TIMESTAMP NULL,
            
            -- Indexes
            INDEX `idx_reference` (`reference_id`),
            INDEX `idx_status_created` (`status`, `created_at`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_network` (`network`),
            INDEX `idx_scheduled` (`scheduled_for`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Admin payment records for compliance fees'",
        'Creating admin_payments table'
    );

    // Payment processing queue
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `admin_payment_queue` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `payment_id` BIGINT UNSIGNED NOT NULL,
            `status` ENUM('pending', 'processing', 'completed', 'failed', 'retry') DEFAULT 'pending',
            `scheduled_for` TIMESTAMP NOT NULL,
            `attempts` INT DEFAULT 0,
            `error_message` TEXT NULL,
            `result_data` JSON NULL,
            `tx_hash` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `started_at` TIMESTAMP NULL,
            `completed_at` TIMESTAMP NULL,
            `last_attempt` TIMESTAMP NULL,
            
            INDEX `idx_status_scheduled` (`status`, `scheduled_for`),
            INDEX `idx_payment` (`payment_id`),
            INDEX `idx_attempts` (`attempts`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Queue for processing admin payments'",
        'Creating admin_payment_queue table'
    );

    // Payment audit log
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `admin_payment_audit` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `payment_id` BIGINT UNSIGNED NOT NULL,
            `action` VARCHAR(50) NOT NULL,
            `performed_by` VARCHAR(255) NOT NULL,
            `details` JSON NULL,
            `ip_address` VARCHAR(45) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX `idx_payment_action` (`payment_id`, `action`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Audit log for admin payment operations'",
        'Creating admin_payment_audit table'
    );

    // Add foreign key constraints if tables exist
    try {
        $pdo->exec("
            ALTER TABLE `admin_payment_queue`
            ADD CONSTRAINT `fk_payment_queue_payment`
            FOREIGN KEY (`payment_id`) REFERENCES `admin_payments`(`id`)
            ON DELETE CASCADE
        ");
        echo "✓ Added foreign key constraint for admin_payment_queue\n";
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'doesn\'t exist') === false) {
            echo "⚠ Foreign key constraint: " . $e->getMessage() . "\n";
        }
    }

    try {
        $pdo->exec("
            ALTER TABLE `admin_payment_audit`
            ADD CONSTRAINT `fk_payment_audit_payment`
            FOREIGN KEY (`payment_id`) REFERENCES `admin_payments`(`id`)
            ON DELETE CASCADE
        ");
        echo "✓ Added foreign key constraint for admin_payment_audit\n";
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'doesn\'t exist') === false) {
            echo "⚠ Foreign key constraint: " . $e->getMessage() . "\n";
        }
    }

    echo "\n✓ Admin Payments migration completed successfully!\n";

} catch (\Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

