<?php

/**
 * Database schema creation script for Ghidar application.
 * Uses Database::getConnection() to connect via PDO with TiDB support.
 * All CREATE TABLE statements are idempotent (IF NOT EXISTS).
 */

// Bootstrap application to load Config and Database classes
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
    
    //          users            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `users` (
        `id` BIGINT(255) PRIMARY KEY,
        `step` VARCHAR(255) DEFAULT NULL,
        `first_name` VARCHAR(255) DEFAULT NULL,
        `last_name` VARCHAR(255) DEFAULT NULL,
        `username` VARCHAR(255) DEFAULT NULL,
        `is_premium` INT(1) DEFAULT 0,
        `language_code` VARCHAR(64) DEFAULT 'en',
        `hash` VARCHAR(255) DEFAULT NULL,
        `tdata` VARCHAR(1028) DEFAULT NULL,
        `score` BIGINT DEFAULT 0,
        `balance` BIGINT DEFAULT 0,
        `energy` BIGINT DEFAULT 500,
        `multitap` INT DEFAULT 1,
        `energyLimit` INT DEFAULT 1,
        `rechargingSpeed` INT DEFAULT 1,
        `referrals` INT DEFAULT 0,
        `inviter_id` BIGINT(255) DEFAULT NULL,
        `tappingGuruLeft` INT DEFAULT 3,
        `tappingGuruStarted` BIGINT DEFAULT NULL,
        `tappingGuruNextTime` BIGINT DEFAULT NULL,
        `fullTankLeft` INT DEFAULT 3,
        `fullTankNextTime` BIGINT DEFAULT 43200000,
        `lastTapTime` BIGINT DEFAULT NULL,
        `totalReferralsRewards` BIGINT DEFAULT 0,
        `joining_date` BIGINT DEFAULT NULL
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating users table'
    );
    
    
    //          missions            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `missions` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `reward` INT,
        `name` VARCHAR(128),
        `description` TEXT
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating missions table'
    );
    
    
    //          user_missions            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `user_missions` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `user_id` BIGINT(255),
        `mission_id` INT,
        `status` INT,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
        FOREIGN KEY (`mission_id`) REFERENCES `missions`(`id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating user_missions table'
    );
    
    
    //          tasks            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `tasks` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `mission_id` INT,
        `name` VARCHAR(255),
        `chatId` VARCHAR(255),
        `url` VARCHAR(255),
        `type` INT,
        FOREIGN KEY (`mission_id`) REFERENCES `missions`(`id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating tasks table'
    );
    
    
    //          user_tasks            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `user_tasks` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `user_id` BIGINT(255),
        `task_id` INT,
        `status` INT,
        `check_time` BIGINT,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
        FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating user_tasks table'
    );
    
    
    //          refTasks            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `refTasks` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `user_id` BIGINT(255),
        `refLevel` INT,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating refTasks table'
    );
    
    
    //          leaguesTasks            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `leaguesTasks` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `user_id` BIGINT(255),
        `league` VARCHAR(50),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating leaguesTasks table'
    );
    
    
    //          sending            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `sending` (
        `type` VARCHAR(255) PRIMARY KEY,
        `chat_id` BIGINT(255) DEFAULT NULL,
        `msg_id` BIGINT(255) DEFAULT NULL,
        `count` BIGINT(225) DEFAULT NULL
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating sending table'
    );
    
    
    //          wallets            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `wallets` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `usdt_balance` DECIMAL(32, 8) NOT NULL DEFAULT 0,
        `ghd_balance` DECIMAL(32, 8) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_id` (`user_id`),
        KEY `idx_user_id` (`user_id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating wallets table'
    );
    
    
    //          Backfill wallets for existing users            //
    try {
        $stmt = $pdo->query("SELECT `id` FROM `users` WHERE `id` NOT IN (SELECT `user_id` FROM `wallets`)");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($users) > 0) {
            $insertStmt = $pdo->prepare("INSERT INTO `wallets` (`user_id`, `usdt_balance`, `ghd_balance`) VALUES (:user_id, 0, 0)");
            foreach ($users as $user) {
                $insertStmt->execute(['user_id' => $user['id']]);
            }
            echo "✓ Backfilled wallets for " . count($users) . " existing users\n";
        }
    } catch (\PDOException $e) {
        // Ignore if wallets table doesn't exist yet or other non-critical errors
    }
    
    
    //          airdrop_actions            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `airdrop_actions` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `type` VARCHAR(32) NOT NULL,
        `amount_ghd` DECIMAL(32, 8) NOT NULL,
        `meta` JSON NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_type` (`type`),
        KEY `idx_created_at` (`created_at`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating airdrop_actions table'
    );
    
    
    //          lotteries            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `lotteries` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `type` VARCHAR(32) NOT NULL DEFAULT 'regular',
        `ticket_price_usdt` DECIMAL(32, 8) NOT NULL,
        `prize_pool_usdt` DECIMAL(32, 8) NOT NULL,
        `status` VARCHAR(32) NOT NULL,
        `start_at` DATETIME NULL,
        `end_at` DATETIME NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_status` (`status`),
        KEY `idx_start_at` (`start_at`),
        KEY `idx_end_at` (`end_at`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating lotteries table'
    );
    
    
    //          lottery_tickets            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `lottery_tickets` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `lottery_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT(255) NOT NULL,
        `ticket_number` BIGINT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_lottery_ticket` (`lottery_id`, `ticket_number`),
        KEY `idx_lottery_user` (`lottery_id`, `user_id`),
        KEY `idx_lottery_id` (`lottery_id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating lottery_tickets table'
    );
    
    
    //          lottery_winners            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `lottery_winners` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `lottery_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT(255) NOT NULL,
        `ticket_id` BIGINT UNSIGNED NULL,
        `prize_amount_usdt` DECIMAL(32, 8) NOT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_lottery_id` (`lottery_id`),
        KEY `idx_user_id` (`user_id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating lottery_winners table'
    );
    
    
    //          ai_accounts            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_accounts` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `total_deposited_usdt` DECIMAL(32, 8) NOT NULL DEFAULT 0,
        `current_balance_usdt` DECIMAL(32, 8) NOT NULL DEFAULT 0,
        `realized_pnl_usdt` DECIMAL(32, 8) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_id` (`user_id`),
        KEY `idx_user_id` (`user_id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_accounts table'
    );
    
    
    //          ai_performance_history            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_performance_history` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `snapshot_time` DATETIME NOT NULL,
        `balance_usdt` DECIMAL(32, 8) NOT NULL,
        `pnl_usdt` DECIMAL(32, 8) NOT NULL DEFAULT 0,
        `meta` JSON NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_snapshot_time` (`snapshot_time`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_performance_history table'
    );
    
    
    //          ai_trader_actions            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `ai_trader_actions` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `type` VARCHAR(64) NOT NULL,
        `amount_usdt` DECIMAL(32, 8) NOT NULL,
        `balance_after_usdt` DECIMAL(32, 8) NOT NULL,
        `meta` JSON NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_type` (`type`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating ai_trader_actions table'
    );
    
    
    //          blockchain_addresses            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `blockchain_addresses` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `network` VARCHAR(32) NOT NULL,
        `purpose` VARCHAR(64) NOT NULL,
        `address` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_network_purpose` (`user_id`, `network`, `purpose`),
        KEY `idx_network` (`network`),
        KEY `idx_user_id` (`user_id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating blockchain_addresses table'
    );
    
    
    //          deposits            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `deposits` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `network` VARCHAR(32) NOT NULL,
        `product_type` VARCHAR(64) NOT NULL,
        `status` VARCHAR(32) NOT NULL,
        `address` VARCHAR(255) NOT NULL,
        `expected_amount_usdt` DECIMAL(32, 8) NULL,
        `actual_amount_usdt` DECIMAL(32, 8) NULL,
        `tx_hash` VARCHAR(255) NULL,
        `meta` JSON NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `confirmed_at` TIMESTAMP NULL DEFAULT NULL,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_network` (`network`),
        KEY `idx_status` (`status`),
        KEY `idx_address` (`address`),
        KEY `idx_product_type` (`product_type`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating deposits table'
    );
    
    
    //          withdrawals            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `withdrawals` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `network` VARCHAR(32) NOT NULL,
        `product_type` VARCHAR(64) NOT NULL,
        `amount_usdt` DECIMAL(32, 8) NOT NULL,
        `target_address` VARCHAR(255) NOT NULL,
        `status` VARCHAR(32) NOT NULL,
        `tx_hash` VARCHAR(255) NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `processed_at` TIMESTAMP NULL DEFAULT NULL,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_status` (`status`),
        KEY `idx_network` (`network`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating withdrawals table'
    );
    
    
    //          referral_rewards            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `referral_rewards` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `from_user_id` BIGINT(255) NOT NULL,
        `level` TINYINT UNSIGNED NOT NULL,
        `amount_usdt` DECIMAL(32, 8) NOT NULL,
        `source_type` VARCHAR(64) NOT NULL,
        `source_id` BIGINT UNSIGNED NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_from_user_id` (`from_user_id`),
        KEY `idx_source_type_id` (`source_type`, `source_id`),
        UNIQUE KEY `unique_reward_per_event` (`level`, `source_type`, `source_id`, `user_id`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating referral_rewards table'
    );
    
    
    //          Add index on inviter_id if not exists            //
    try {
        $stmt = $pdo->query("SHOW INDEX FROM `users` WHERE Key_name = 'idx_inviter_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `users` ADD INDEX `idx_inviter_id` (`inviter_id`)");
            echo "✓ Added idx_inviter_id index to users table\n";
        }
    } catch (\PDOException $e) {
        // Index might already exist or table might not exist yet
    }
    
    
    //          api_rate_limits            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `api_rate_limits` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `endpoint` VARCHAR(128) NOT NULL,
        `period_start` TIMESTAMP NOT NULL,
        `count` INT UNSIGNED NOT NULL DEFAULT 0,
        KEY `idx_user_endpoint_period` (`user_id`, `endpoint`, `period_start`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating api_rate_limits table'
    );
    
    
    //          wallet_recovery_requests            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `wallet_recovery_requests` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `request_type` ENUM('cross_chain_recovery', 'ownership_verification', 'lost_access_assist') NOT NULL,
        `original_transaction_hash` VARCHAR(255) NULL,
        `original_network` ENUM('erc20', 'bep20', 'trc20') NOT NULL,
        `target_network` ENUM('erc20', 'bep20', 'trc20') NULL,
        `recovery_status` ENUM('pending', 'processing', 'requires_signature', 'completed', 'failed') DEFAULT 'pending',
        `signed_message` TEXT NULL COMMENT 'ECDSA signed message for verification',
        `message_nonce` VARCHAR(64) NULL COMMENT 'Unique nonce for signing request',
        `requested_amount` DECIMAL(32, 8) NULL,
        `estimated_fee` DECIMAL(32, 8) NULL,
        `user_provided_verification_data` JSON NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_user_recovery` (`user_id`, `recovery_status`),
        KEY `idx_pending_signatures` (`recovery_status`, `created_at`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating wallet_recovery_requests table'
    );
    
    
    //          cross_chain_verification_logs            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `cross_chain_verification_logs` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `recovery_request_id` BIGINT UNSIGNED NOT NULL,
        `verification_step` ENUM('nonce_generated', 'signature_received', 'signature_validated', 'recovery_initiated') NOT NULL,
        `verification_data` JSON NULL,
        `blockchain_validation_data` JSON NULL COMMENT 'On-chain validation proof if applicable',
        `processed_by` VARCHAR(100) DEFAULT 'system',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_recovery_request` (`recovery_request_id`),
        KEY `idx_verification_audit` (`created_at`, `verification_step`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating cross_chain_verification_logs table'
    );
    
    
    //          withdrawal_verification_requests            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `withdrawal_verification_requests` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `withdrawal_id` BIGINT UNSIGNED NULL COMMENT 'Reference to withdrawals table if withdrawal already created',
        `verification_type` ENUM('signature', 'alternative') NOT NULL DEFAULT 'signature',
        `verification_status` ENUM('pending', 'processing', 'approved', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
        `risk_score` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Risk score that triggered verification (0-100)',
        `risk_level` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
        `risk_factors` JSON NULL COMMENT 'Array of risk factors identified',
        `amount_usdt` DECIMAL(32, 8) NOT NULL,
        `target_network` VARCHAR(32) NULL,
        `target_address` VARCHAR(255) NULL,
        `message_to_sign` TEXT NULL COMMENT 'Message for signature verification',
        `message_nonce` VARCHAR(64) NULL COMMENT 'Unique nonce for signing request',
        `signed_message` TEXT NULL COMMENT 'ECDSA signed message from user',
        `wallet_address` VARCHAR(255) NULL COMMENT 'Wallet address used for verification',
        `wallet_network` VARCHAR(32) NULL COMMENT 'Network of wallet used for verification',
        `alternative_verification_data` JSON NULL COMMENT 'Data collected for alternative verification method',
        `expires_at` TIMESTAMP NULL COMMENT 'Verification request expiration time',
        `approved_by` VARCHAR(100) NULL COMMENT 'Admin/system that approved verification',
        `rejection_reason` TEXT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_user_status` (`user_id`, `verification_status`),
        KEY `idx_withdrawal_id` (`withdrawal_id`),
        KEY `idx_pending_verifications` (`verification_status`, `created_at`),
        KEY `idx_expires_at` (`expires_at`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating withdrawal_verification_requests table'
    );
    
    
    //          withdrawal_risk_scores            //
    $executeQuery(
        "CREATE TABLE IF NOT EXISTS `withdrawal_risk_scores` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT(255) NOT NULL,
        `withdrawal_id` BIGINT UNSIGNED NULL COMMENT 'Reference to withdrawals table',
        `verification_request_id` BIGINT UNSIGNED NULL COMMENT 'Reference to withdrawal_verification_requests table',
        `risk_score` INT UNSIGNED NOT NULL COMMENT 'Calculated risk score (0-100)',
        `risk_level` ENUM('low', 'medium', 'high') NOT NULL,
        `risk_factors` JSON NOT NULL COMMENT 'Array of identified risk factors',
        `amount_usdt` DECIMAL(32, 8) NOT NULL,
        `network` VARCHAR(32) NOT NULL,
        `pattern_analysis` JSON NULL COMMENT 'Analysis of withdrawal patterns',
        `user_history_summary` JSON NULL COMMENT 'Summary of user withdrawal history',
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_user_id` (`user_id`),
        KEY `idx_withdrawal_id` (`withdrawal_id`),
        KEY `idx_verification_request_id` (`verification_request_id`),
        KEY `idx_risk_score` (`risk_score`),
        KEY `idx_created_at` (`created_at`)
        ) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci",
        'Creating withdrawal_risk_scores table'
    );
    
    
    echo "\n✓ Database schema creation completed successfully!\n";
    
} catch (\PDOException $e) {
    echo "✗ Database connection error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
