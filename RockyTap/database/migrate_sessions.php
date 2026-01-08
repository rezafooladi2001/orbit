<?php

/**
 * Database migration: Create user_sessions table
 * 
 * This migration creates a table for database-backed session management.
 * Run this migration once to set up the session infrastructure.
 * 
 * Usage: php RockyTap/database/migrate_sessions.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;

try {
    $db = Database::getConnection();

    // Create user_sessions table
    $sql = "
        CREATE TABLE IF NOT EXISTS `user_sessions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `session_id` VARCHAR(64) NOT NULL COMMENT 'Session identifier',
            `user_id` BIGINT DEFAULT NULL COMMENT 'Associated user ID',
            `session_data` TEXT NOT NULL COMMENT 'Serialized session data (JSON)',
            `expires_at` TIMESTAMP NOT NULL COMMENT 'Session expiration time',
            `revoked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether session is revoked',
            `revoked_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When session was revoked',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Session creation time',
            `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last activity time',
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_session_id` (`session_id`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_expires_at` (`expires_at`),
            INDEX `idx_revoked` (`revoked`, `revoked_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Database-backed user sessions';
    ";

    $db->exec($sql);

    echo "✓ Created user_sessions table\n";

    // Check if table was created successfully
    $stmt = $db->query("SHOW TABLES LIKE 'user_sessions'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table verification successful\n";
        Logger::info('User sessions table created successfully');
    } else {
        throw new \RuntimeException('Table creation verification failed');
    }

    echo "\nMigration completed successfully!\n";

} catch (\Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    Logger::error('Session migration failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}

