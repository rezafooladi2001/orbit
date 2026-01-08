-- AI Trader Hourly Snapshots Table
-- Stores hourly profit/loss snapshots for realistic intraday fluctuations

CREATE TABLE IF NOT EXISTS `ai_trader_hourly_snapshots` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `snapshot_time` DATETIME NOT NULL,
    `hour` TINYINT UNSIGNED NOT NULL COMMENT 'Hour of day (0-23)',
    `balance_usdt` DECIMAL(20, 8) NOT NULL DEFAULT 0,
    `pnl_amount` DECIMAL(20, 8) NOT NULL DEFAULT 0 COMMENT 'PnL amount for this hour',
    `pnl_percent` DECIMAL(10, 4) NOT NULL DEFAULT 0 COMMENT 'PnL percentage for this hour',
    `cumulative_day_pnl` DECIMAL(10, 4) NOT NULL DEFAULT 0 COMMENT 'Cumulative day PnL percentage',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_user_date` (`user_id`, `snapshot_time`),
    INDEX `idx_account_date` (`account_id`, `snapshot_time`),
    INDEX `idx_snapshot_time` (`snapshot_time`),
    
    UNIQUE KEY `unique_user_hour` (`user_id`, `account_id`, `snapshot_time`, `hour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add duration_minutes column to ai_trader_fake_trades if not exists
ALTER TABLE `ai_trader_fake_trades` 
ADD COLUMN IF NOT EXISTS `duration_minutes` INT UNSIGNED DEFAULT NULL COMMENT 'Trade duration in minutes';

