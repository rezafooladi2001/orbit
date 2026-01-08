<?php

/**
 * Database migration script for new features tables
 * Creates tables for notifications, achievements, help_articles, and support_tickets
 * 
 * Run this script once to set up the new tables:
 * php RockyTap/database/migrate_new_features_tables.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use PDO;

try {
    $pdo = Database::getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting database migration for new features tables...\n\n";
    
    // ==================== Notifications Table ====================
    echo "Creating notifications table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notifications` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `type` VARCHAR(64) NOT NULL DEFAULT 'info',
            `read` TINYINT(1) DEFAULT 0,
            `metadata` JSON NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_user_id` (`user_id`),
            KEY `idx_read` (`read`),
            KEY `idx_created_at` (`created_at`),
            KEY `idx_user_read` (`user_id`, `read`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ notifications table created\n\n";
    
    // ==================== Achievements Table ====================
    echo "Creating achievements table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `achievements` (
            `id` VARCHAR(64) PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT NOT NULL,
            `icon` VARCHAR(32) NOT NULL,
            `category` VARCHAR(64) NOT NULL DEFAULT 'general',
            `target_value` DECIMAL(32, 8) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_category` (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ achievements table created\n\n";
    
    // ==================== User Achievements Table ====================
    echo "Creating user_achievements table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_achievements` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `achievement_id` VARCHAR(64) NOT NULL,
            `progress` DECIMAL(32, 8) DEFAULT 0,
            `unlocked_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_achievement` (`user_id`, `achievement_id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_achievement_id` (`achievement_id`),
            KEY `idx_unlocked_at` (`unlocked_at`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`achievement_id`) REFERENCES `achievements`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ user_achievements table created\n\n";
    
    // ==================== Help Articles Table ====================
    echo "Creating help_articles table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `help_articles` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `content` TEXT NOT NULL,
            `excerpt` TEXT NULL,
            `category` VARCHAR(64) NOT NULL,
            `tags` JSON NULL,
            `related_articles` JSON NULL,
            `published` TINYINT(1) DEFAULT 1,
            `views` INT UNSIGNED DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY `idx_category` (`category`),
            KEY `idx_published` (`published`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ help_articles table created\n\n";
    
    // ==================== Support Tickets Table ====================
    echo "Creating support_tickets table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `support_tickets` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `status` VARCHAR(32) DEFAULT 'open',
            `priority` VARCHAR(32) DEFAULT 'normal',
            `admin_response` TEXT NULL,
            `admin_user_id` BIGINT UNSIGNED NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `resolved_at` TIMESTAMP NULL,
            KEY `idx_user_id` (`user_id`),
            KEY `idx_status` (`status`),
            KEY `idx_priority` (`priority`),
            KEY `idx_created_at` (`created_at`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ support_tickets table created\n\n";
    
    // ==================== Insert Default Achievements ====================
    echo "Inserting default achievements...\n";
    $defaultAchievements = [
        ['id' => 'first_tap', 'name' => 'First Tap', 'description' => 'Complete your first tap', 'icon' => '👆', 'category' => 'airdrop', 'target_value' => 1],
        ['id' => 'hundred_taps', 'name' => 'Century', 'description' => 'Reach 100 taps', 'icon' => '💯', 'category' => 'airdrop', 'target_value' => 100],
        ['id' => 'thousand_taps', 'name' => 'Thousand Club', 'description' => 'Reach 1,000 taps', 'icon' => '🔥', 'category' => 'airdrop', 'target_value' => 1000],
        ['id' => 'first_ticket', 'name' => 'Lottery Player', 'description' => 'Buy your first lottery ticket', 'icon' => '🎫', 'category' => 'lottery', 'target_value' => 1],
        ['id' => 'lottery_winner', 'name' => 'Winner', 'description' => 'Win a lottery', 'icon' => '🏆', 'category' => 'lottery', 'target_value' => 1],
        ['id' => 'first_referral', 'name' => 'Influencer', 'description' => 'Get your first referral', 'icon' => '👥', 'category' => 'referral', 'target_value' => 1],
        ['id' => 'ten_referrals', 'name' => 'Network Builder', 'description' => 'Get 10 referrals', 'icon' => '🌟', 'category' => 'referral', 'target_value' => 10],
        ['id' => 'first_deposit', 'name' => 'Investor', 'description' => 'Make your first deposit', 'icon' => '💰', 'category' => 'ai_trader', 'target_value' => 1],
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO `achievements` (`id`, `name`, `description`, `icon`, `category`, `target_value`)
        VALUES (:id, :name, :description, :icon, :category, :target_value)
    ");
    
    foreach ($defaultAchievements as $achievement) {
        $stmt->execute($achievement);
    }
    echo "✓ Default achievements inserted\n\n";
    
    // ==================== Insert Default Help Articles ====================
    echo "Inserting default help articles...\n";
    $defaultArticles = [
        [
            'title' => 'Getting Started with Ghidar',
            'content' => '<h2>Welcome to Ghidar!</h2><p>Ghidar is your gateway to crypto opportunities. This guide will help you get started.</p><h3>Features</h3><ul><li><strong>Airdrop:</strong> Tap to mine GHD tokens and convert them to USDT</li><li><strong>Lottery:</strong> Buy tickets and participate in weekly draws</li><li><strong>AI Trader:</strong> Deposit USDT and let AI trade for you</li><li><strong>Referrals:</strong> Invite friends and earn commissions</li></ul>',
            'excerpt' => 'Learn the basics of using Ghidar and all its features.',
            'category' => 'getting-started',
            'tags' => json_encode(['beginner', 'tutorial']),
        ],
        [
            'title' => 'How to Mine GHD Tokens',
            'content' => '<h2>Mining GHD Tokens</h2><p>GHD tokens can be earned by tapping in the Airdrop section.</p><h3>Steps:</h3><ol><li>Go to the Airdrop tab</li><li>Tap the coin to earn GHD</li><li>Convert GHD to USDT when ready</li></ol><p><strong>Note:</strong> GHD is an internal token. Conversion rates may vary.</p>',
            'excerpt' => 'Learn how to earn GHD tokens by tapping and convert them to USDT.',
            'category' => 'airdrop',
            'tags' => json_encode(['airdrop', 'ghd', 'mining']),
        ],
        [
            'title' => 'Participating in Lotteries',
            'content' => '<h2>Lottery Participation</h2><p>Buy tickets to participate in our weekly lottery draws.</p><h3>How it works:</h3><ul><li>Each lottery has a ticket price in USDT</li><li>Buy as many tickets as you want</li><li>Winners are drawn automatically when the lottery ends</li><li>Prizes are paid to your wallet balance</li></ul>',
            'excerpt' => 'Everything you need to know about participating in Ghidar lotteries.',
            'category' => 'lottery',
            'tags' => json_encode(['lottery', 'tickets', 'prizes']),
        ],
        [
            'title' => 'Wallet Verification',
            'content' => '<h2>Wallet Verification</h2><p>For security and compliance, wallet verification is required for withdrawals and high-value transactions.</p><h3>Verification Methods:</h3><ul><li><strong>Message Signing:</strong> Sign a message with your wallet (recommended)</li><li><strong>Assisted Verification:</strong> Our support team will help you verify</li></ul><p><strong>Security Note:</strong> We never ask for your private keys. Only sign messages from official Ghidar sources.</p>',
            'excerpt' => 'Learn about wallet verification requirements and how to complete verification.',
            'category' => 'wallet',
            'tags' => json_encode(['security', 'verification', 'wallet']),
        ],
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO `help_articles` (`title`, `content`, `excerpt`, `category`, `tags`)
        VALUES (:title, :content, :excerpt, :category, :tags)
    ");
    
    foreach ($defaultArticles as $article) {
        $stmt->execute($article);
    }
    echo "✓ Default help articles inserted\n\n";
    
    echo "✅ Migration completed successfully!\n";
    echo "\nAll new feature tables have been created and populated with default data.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

