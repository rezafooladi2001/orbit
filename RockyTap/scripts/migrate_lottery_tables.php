<?php

declare(strict_types=1);

/**
 * Migration Script: Update Lottery Tables for Universal Winner System
 * 
 * Adds necessary columns to lottery_winners table and creates
 * lottery_win_notifications table for in-app popups.
 * 
 * Usage: php migrate_lottery_tables.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  🗄️ LOTTERY TABLES MIGRATION\n";
echo "  Time: " . date('Y-m-d H:i:s') . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $db = Database::getConnection();

    // 1. Add columns to lottery_winners if they don't exist
    echo "📋 Updating lottery_winners table...\n";

    $migrations = [
        "ALTER TABLE `lottery_winners` ADD COLUMN IF NOT EXISTS `winner_rank` INT NOT NULL DEFAULT 1",
        "ALTER TABLE `lottery_winners` ADD COLUMN IF NOT EXISTS `is_grand_prize` BOOLEAN NOT NULL DEFAULT FALSE",
        "ALTER TABLE `lottery_winners` ADD COLUMN IF NOT EXISTS `status` VARCHAR(32) NOT NULL DEFAULT 'won'"
    ];

    foreach ($migrations as $sql) {
        try {
            $db->exec($sql);
            echo "   ✅ Applied: " . substr($sql, 0, 60) . "...\n";
        } catch (\PDOException $e) {
            // Column might already exist, that's OK
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "   ⏭️ Column already exists, skipping\n";
            } else {
                echo "   ⚠️ Warning: " . $e->getMessage() . "\n";
            }
        }
    }

    // 2. Create lottery_win_notifications table
    echo "\n📋 Creating lottery_win_notifications table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS `lottery_win_notifications` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT(255) NOT NULL,
            `lottery_id` BIGINT UNSIGNED NOT NULL,
            `lottery_title` VARCHAR(255) NOT NULL,
            `prize_amount_usdt` DECIMAL(32, 8) NOT NULL,
            `winner_rank` INT NOT NULL DEFAULT 1,
            `is_grand_prize` BOOLEAN NOT NULL DEFAULT FALSE,
            `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
            `is_claimed` BOOLEAN NOT NULL DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `read_at` TIMESTAMP NULL,
            KEY `idx_user_unread` (`user_id`, `is_read`),
            KEY `idx_lottery` (`lottery_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ lottery_win_notifications table ready\n";

    // 3. Add indexes if they don't exist
    echo "\n📋 Adding indexes...\n";

    $indexes = [
        "CREATE INDEX IF NOT EXISTS `idx_lottery_winners_lottery` ON `lottery_winners` (`lottery_id`)",
        "CREATE INDEX IF NOT EXISTS `idx_lottery_winners_user` ON `lottery_winners` (`user_id`)"
    ];

    foreach ($indexes as $sql) {
        try {
            $db->exec($sql);
            echo "   ✅ Index applied\n";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key') !== false || strpos($e->getMessage(), 'exists') !== false) {
                echo "   ⏭️ Index already exists, skipping\n";
            } else {
                echo "   ⚠️ Warning: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  ✅ MIGRATION COMPLETE\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

} catch (\Exception $e) {
    echo "\n❌ MIGRATION FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

