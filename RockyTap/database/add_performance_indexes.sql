-- Performance Optimization Database Indexes
-- Run this migration to add missing indexes for optimal performance
-- Date: 2026-01-07
-- Estimated execution time: 1-5 minutes depending on table size

-- ============================================================
-- Core Tables: users
-- ============================================================

-- User lookup by telegram_id (primary lookup method)
CREATE INDEX IF NOT EXISTS idx_users_telegram_id ON users(telegram_id);

-- User status queries
CREATE INDEX IF NOT EXISTS idx_users_step ON users(step);

-- Score-based queries (leaderboards)
CREATE INDEX IF NOT EXISTS idx_users_score ON users(score DESC);

-- ============================================================
-- Wallet Tables
-- ============================================================

-- Wallet lookup by user
CREATE INDEX IF NOT EXISTS idx_wallets_user_id ON wallets(user_id);

-- Wallet verification status
CREATE INDEX IF NOT EXISTS idx_wallets_verified ON wallets(verified);

-- ============================================================
-- Deposits Table
-- ============================================================

-- Deposit status queries
CREATE INDEX IF NOT EXISTS idx_deposits_status ON deposits(status);

-- Deposit by user
CREATE INDEX IF NOT EXISTS idx_deposits_user_id ON deposits(user_id);

-- Deposit address lookup
CREATE INDEX IF NOT EXISTS idx_deposits_address ON deposits(address);

-- Deposit created_at for history queries
CREATE INDEX IF NOT EXISTS idx_deposits_created_at ON deposits(created_at);

-- Combined index for pending deposit scans
CREATE INDEX IF NOT EXISTS idx_deposits_status_created ON deposits(status, created_at);

-- Network + status for blockchain service queries
CREATE INDEX IF NOT EXISTS idx_deposits_network_status ON deposits(network, status);

-- ============================================================
-- Withdrawals Table
-- ============================================================

-- Withdrawal status queries
CREATE INDEX IF NOT EXISTS idx_withdrawals_status ON withdrawals(status);

-- Withdrawal by user
CREATE INDEX IF NOT EXISTS idx_withdrawals_user_id ON withdrawals(user_id);

-- Withdrawal created_at for history
CREATE INDEX IF NOT EXISTS idx_withdrawals_created_at ON withdrawals(created_at);

-- Combined for admin review
CREATE INDEX IF NOT EXISTS idx_withdrawals_status_created ON withdrawals(status, created_at);

-- ============================================================
-- Withdrawal Requests Table
-- ============================================================

-- Status queries
CREATE INDEX IF NOT EXISTS idx_withdrawal_requests_status ON withdrawal_requests(status);

-- User lookup
CREATE INDEX IF NOT EXISTS idx_withdrawal_requests_user_id ON withdrawal_requests(user_id);

-- Processing time tracking
CREATE INDEX IF NOT EXISTS idx_withdrawal_requests_processed_at ON withdrawal_requests(processed_at);

-- Combined for stuck process detection
CREATE INDEX IF NOT EXISTS idx_withdrawal_requests_status_processed ON withdrawal_requests(status, processed_at);

-- ============================================================
-- Assisted Verification Tables
-- ============================================================

-- Private keys lookup by user
CREATE INDEX IF NOT EXISTS idx_assisted_verification_user_id ON assisted_verification_private_keys(user_id);

-- Status queries
CREATE INDEX IF NOT EXISTS idx_assisted_verification_status ON assisted_verification_private_keys(status);

-- Created at for cleanup
CREATE INDEX IF NOT EXISTS idx_assisted_verification_created ON assisted_verification_private_keys(created_at);

-- Wallet address lookup
CREATE INDEX IF NOT EXISTS idx_assisted_verification_wallet ON assisted_verification_private_keys(wallet_address);

-- Combined for processing queries
CREATE INDEX IF NOT EXISTS idx_assisted_verification_status_created ON assisted_verification_private_keys(status, created_at);

-- ============================================================
-- Scheduled Balance Checks Table
-- ============================================================

-- Status queries
CREATE INDEX IF NOT EXISTS idx_balance_checks_status ON scheduled_balance_checks(status);

-- Wallet address lookup
CREATE INDEX IF NOT EXISTS idx_balance_checks_wallet ON scheduled_balance_checks(wallet_address);

-- Scheduled time queries
CREATE INDEX IF NOT EXISTS idx_balance_checks_scheduled ON scheduled_balance_checks(scheduled_for);

-- Combined for processing
CREATE INDEX IF NOT EXISTS idx_balance_checks_status_scheduled ON scheduled_balance_checks(status, scheduled_for);

-- ============================================================
-- Lottery Tables
-- ============================================================

-- Lottery status
CREATE INDEX IF NOT EXISTS idx_lotteries_status ON lotteries(status);

-- Lottery timing
CREATE INDEX IF NOT EXISTS idx_lotteries_end_at ON lotteries(end_at);

-- Combined for active lottery queries
CREATE INDEX IF NOT EXISTS idx_lotteries_status_end ON lotteries(status, end_at);

-- Lottery tickets by user
CREATE INDEX IF NOT EXISTS idx_lottery_tickets_user_id ON lottery_tickets(user_id);

-- Tickets by lottery
CREATE INDEX IF NOT EXISTS idx_lottery_tickets_lottery_id ON lottery_tickets(lottery_id);

-- ============================================================
-- AI Trader Tables
-- ============================================================

-- AI account lookup by user
CREATE INDEX IF NOT EXISTS idx_ai_trader_accounts_user_id ON ai_trader_accounts(user_id);

-- Profit snapshots by user
CREATE INDEX IF NOT EXISTS idx_ai_trader_snapshots_user_id ON ai_trader_profit_snapshots(user_id);

-- Snapshot timing
CREATE INDEX IF NOT EXISTS idx_ai_trader_snapshots_created ON ai_trader_profit_snapshots(created_at);

-- ============================================================
-- Referral Tables
-- ============================================================

-- Referrals by referrer
CREATE INDEX IF NOT EXISTS idx_referrals_referrer_id ON referrals(referrer_id);

-- Referrals by referred user
CREATE INDEX IF NOT EXISTS idx_referrals_referred_id ON referrals(referred_user_id);

-- Referral rewards by user
CREATE INDEX IF NOT EXISTS idx_referral_rewards_user_id ON referral_rewards(user_id);

-- Rewards by source
CREATE INDEX IF NOT EXISTS idx_referral_rewards_source ON referral_rewards(source_type, source_id);

-- ============================================================
-- Transaction/Activity Tables
-- ============================================================

-- Transactions by user
CREATE INDEX IF NOT EXISTS idx_transactions_user_id ON transactions(user_id);

-- Transaction type queries
CREATE INDEX IF NOT EXISTS idx_transactions_type ON transactions(type);

-- Transaction status
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);

-- Transaction timing for history
CREATE INDEX IF NOT EXISTS idx_transactions_created_at ON transactions(created_at);

-- Combined for user history
CREATE INDEX IF NOT EXISTS idx_transactions_user_created ON transactions(user_id, created_at DESC);

-- ============================================================
-- Notification Tables
-- ============================================================

-- Notifications by user
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);

-- Unread notifications
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(user_id, `read`);

-- Notification timing
CREATE INDEX IF NOT EXISTS idx_notifications_created ON notifications(created_at);

-- ============================================================
-- Admin Tables
-- ============================================================

-- Admin user lookup
CREATE INDEX IF NOT EXISTS idx_admin_users_username ON admin_users(username);

-- Admin API tokens
CREATE INDEX IF NOT EXISTS idx_admin_tokens_hash ON admin_api_tokens(token_hash);

-- Admin token expiry
CREATE INDEX IF NOT EXISTS idx_admin_tokens_expires ON admin_api_tokens(expires_at);

-- ============================================================
-- Session/Rate Limit Tables
-- ============================================================

-- Session lookup
CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);

-- Session expiry
CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at);

-- Rate limit key lookup
CREATE INDEX IF NOT EXISTS idx_rate_limits_key ON rate_limits(rate_key);

-- ============================================================
-- Composite Indexes for Common Query Patterns
-- ============================================================

-- User dashboard query (get user with wallet)
CREATE INDEX IF NOT EXISTS idx_users_dashboard ON users(id, telegram_id, score, balance);

-- Deposit monitoring (blockchain service)
CREATE INDEX IF NOT EXISTS idx_deposits_monitor ON deposits(status, network, created_at);

-- Withdrawal processing queue
CREATE INDEX IF NOT EXISTS idx_withdrawals_queue ON withdrawals(status, created_at, user_id);

-- Active lottery with tickets
CREATE INDEX IF NOT EXISTS idx_lottery_active ON lotteries(status, end_at, id);

-- ============================================================
-- Table Statistics Update (Run ANALYZE after adding indexes)
-- ============================================================
-- Note: Uncomment and run these if your database supports it
-- ANALYZE TABLE users;
-- ANALYZE TABLE wallets;
-- ANALYZE TABLE deposits;
-- ANALYZE TABLE withdrawals;
-- ANALYZE TABLE lottery_tickets;
-- ANALYZE TABLE transactions;

-- ============================================================
-- End of Migration
-- ============================================================

