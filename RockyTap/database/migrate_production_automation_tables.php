<?php

/**
 * Database migration for Production Automation & Monitoring System
 * Creates tables for pipeline execution, integration logs, archives, and compliance
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
    
    // pipeline_execution_log table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `pipeline_execution_log` (
            `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `pipeline_id` VARCHAR(64) NOT NULL UNIQUE,
            `started_at` TIMESTAMP NOT NULL,
            `completed_at` TIMESTAMP NULL,
            `execution_time` DECIMAL(10, 2) DEFAULT NULL,
            `status` ENUM('running', 'completed', 'failed', 'cancelled') DEFAULT 'running',
            `steps_data` JSON,
            `error_message` TEXT,
            `error_trace` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_pipeline_id` (`pipeline_id`),
            INDEX `idx_status_created` (`status`, `created_at`),
            INDEX `idx_started` (`started_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'Creating pipeline_execution_log table'
    );
    
    // integration_execution_log table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `integration_execution_log` (
            `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `verification_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT NOT NULL,
            `service_type` ENUM('lottery', 'airdrop', 'ai_trader', 'general') NOT NULL,
            `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            `amount` DECIMAL(32, 8) DEFAULT NULL,
            `execution_time` DECIMAL(10, 4) DEFAULT NULL,
            `execution_result` JSON,
            `error_message` TEXT,
            `retry_count` TINYINT DEFAULT 0,
            `executed_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_verification` (`verification_id`),
            INDEX `idx_user_service` (`user_id`, `service_type`),
            INDEX `idx_status_created` (`status`, `created_at`),
            INDEX `idx_service_type` (`service_type`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'Creating integration_execution_log table'
    );
    
    // verification_archive table (cold storage)
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `verification_archive` (
            `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `original_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT NOT NULL,
            `verification_type` VARCHAR(64) NOT NULL,
            `archived_data` LONGBLOB NOT NULL COMMENT 'Compressed/encrypted verification data',
            `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `compression_method` VARCHAR(32) DEFAULT 'gzip',
            INDEX `idx_original_id` (`original_id`),
            INDEX `idx_user_archived` (`user_id`, `archived_at`),
            INDEX `idx_archived_at` (`archived_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'Creating verification_archive table'
    );
    
    // compliance_reports table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `compliance_reports` (
            `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `report_type` ENUM('daily', 'weekly', 'monthly') NOT NULL,
            `report_period` VARCHAR(32) NOT NULL,
            `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `report_data` JSON NOT NULL,
            `status` ENUM('generated', 'sent', 'archived') DEFAULT 'generated',
            `sent_at` TIMESTAMP NULL,
            `recipients` JSON,
            INDEX `idx_type_period` (`report_type`, `report_period`),
            INDEX `idx_generated` (`generated_at`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'Creating compliance_reports table'
    );
    
    // cleanup_audit_log table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `cleanup_audit_log` (
            `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `cleanup_type` VARCHAR(64) NOT NULL,
            `operation` VARCHAR(64) NOT NULL,
            `record_id` BIGINT UNSIGNED DEFAULT NULL,
            `record_type` VARCHAR(64) DEFAULT NULL,
            `record_data` JSON,
            `performed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `performed_by` VARCHAR(64) DEFAULT 'automated_cleanup',
            INDEX `idx_cleanup_type` (`cleanup_type`, `performed_at`),
            INDEX `idx_record` (`record_type`, `record_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'Creating cleanup_audit_log table'
    );
    
    // system_health_monitor table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `system_health_monitor` (
            `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `component` VARCHAR(64) NOT NULL,
            `status` ENUM('healthy', 'warning', 'critical', 'unknown') DEFAULT 'unknown',
            `metrics` JSON,
            `checked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_component_status` (`component`, `status`),
            INDEX `idx_checked` (`checked_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'Creating system_health_monitor table'
    );
    
    // alert_log table
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `alert_log` (
            `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `alert_type` VARCHAR(64) NOT NULL,
            `priority` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            `alert_data` JSON NOT NULL,
            `status` ENUM('pending', 'sent', 'acknowledged', 'resolved') DEFAULT 'pending',
            `sent_at` TIMESTAMP NULL,
            `acknowledged_at` TIMESTAMP NULL,
            `resolved_at` TIMESTAMP NULL,
            `channels_used` JSON,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_type_status` (`alert_type`, `status`),
            INDEX `idx_priority_created` (`priority`, `created_at`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'Creating alert_log table'
    );
    
    echo "\nMigration completed successfully!\n";
    
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
