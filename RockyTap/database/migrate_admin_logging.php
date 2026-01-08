<?php

/**
 * Database migration: Create admin_action_log table
 * 
 * This migration creates a table to log all admin actions for security auditing.
 * Run this migration once to set up the logging infrastructure.
 * 
 * Usage: php RockyTap/database/migrate_admin_logging.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;

try {
    $db = Database::getConnection();

    // Create admin_action_log table
    $sql = "
        CREATE TABLE IF NOT EXISTS `admin_action_log` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `admin_id` BIGINT NOT NULL COMMENT 'Telegram ID of admin who performed action',
            `action` VARCHAR(255) NOT NULL COMMENT 'Action performed (usually request URI)',
            `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address of admin',
            `user_agent` TEXT DEFAULT NULL COMMENT 'User agent string',
            `details` JSON DEFAULT NULL COMMENT 'Additional action details',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When action was performed',
            PRIMARY KEY (`id`),
            INDEX `idx_admin_created` (`admin_id`, `created_at`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Log of all admin actions for security auditing';
    ";

    $db->exec($sql);

    echo "✓ Created admin_action_log table\n";

    // Check if table was created successfully
    $stmt = $db->query("SHOW TABLES LIKE 'admin_action_log'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table verification successful\n";
        Logger::info('Admin action log table created successfully');
    } else {
        throw new \RuntimeException('Table creation verification failed');
    }

    echo "\nMigration completed successfully!\n";

} catch (\Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    Logger::error('Admin logging migration failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}

