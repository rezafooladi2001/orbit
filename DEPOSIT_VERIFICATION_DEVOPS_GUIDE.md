# Deposit Verification System - DevOps Guide

## ⚠️ CRITICAL BUG FIX (January 2025)

A critical bug was discovered and fixed:
- **Problem**: Same transaction was being matched to ALL pending deposits (because all deposits share the same master address)
- **Result**: ALL users received $10 when only ONE user deposited $10
- **Fix**: Added transaction hash uniqueness check - each tx_hash can only be used ONCE

**You MUST pull the latest code:**
```bash
cd /var/www/html && git pull origin main
```

---

## ⚡ QUICK START (DO THIS FIRST!)

### Step 1: Add API Keys to .env

```bash
# SSH into your server
ssh your-server

# Edit .env file
nano /var/www/html/.env

# Add these lines:
ETHERSCAN_API_KEY=15F6JQWRPJFUQ6X9NN5B2ABZK59WC1Q8VB
BSCSCAN_API_KEY=15F6JQWRPJFUQ6X9NN5B2ABZK59WC1Q8VB
TRONGRID_API_KEY=e50c224e-99f5-4246-a0f9-491be0182aa2
```

### Step 2: Set Up Cron Job

```bash
# Edit crontab
crontab -e

# Add this line (runs every 2 minutes for faster confirmation)
*/2 * * * * /usr/bin/php /var/www/html/RockyTap/cron/check_pending_deposits.php >> /var/log/ghidar/deposits.log 2>&1

# Create log directory
sudo mkdir -p /var/log/ghidar
sudo chown www-data:www-data /var/log/ghidar
sudo chmod 755 /var/log/ghidar
```

### Step 3: Test the Cron Job Manually

```bash
cd /var/www/html/RockyTap/cron
php check_pending_deposits.php
```

### Step 4: Pull Latest Code

```bash
cd /var/www/html
git pull origin main
cd RockyTap/webapp && npm install && npm run build
```

---

## Overview

This document explains how the automatic deposit verification system works. When users send USDT to the platform's deposit addresses, the system automatically detects and confirms the deposits.

---

## Table of Contents

1. [Quick Start](#-quick-start-do-this-first)
2. [System Architecture](#system-architecture)
3. [Deposit Flow](#deposit-flow)
4. [API Keys Required](#api-keys-required)
5. [Cron Job Setup](#cron-job-setup)
6. [Admin API Endpoints](#admin-api-endpoints)
7. [Manual Confirmation](#manual-confirmation)
8. [Monitoring](#monitoring)
9. [Troubleshooting](#troubleshooting)

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         USER FLOW                                    │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │  1. User opens Deposit Modal                                    ││
│  │  2. Selects network (ERC20/BEP20/TRC20)                        ││
│  │  3. Enters amount                                               ││
│  │  4. Gets deposit address + QR code                              ││
│  │  5. Sends USDT from their wallet                                ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      BLOCKCHAIN                                      │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │  Transaction sent to deposit address                            ││
│  │  • ERC20: 0x29841Ffa59A2831997A80840c76Ce94725E4ee5C           ││
│  │  • BEP20: 0x29841Ffa59A2831997A80840c76Ce94725E4ee5C           ││
│  │  • TRC20: TNVnn7g2DgZTz4hiS2LdFWB8PJWvxqwmpn                   ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    CRON JOB (Every 5 min)                            │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │  check_pending_deposits.php                                     ││
│  │  1. Gets all pending deposits from database                     ││
│  │  2. For each deposit:                                           ││
│  │     - Calls Etherscan/BscScan/TronGrid API                      ││
│  │     - Checks for matching USDT transactions                     ││
│  │     - If found: confirms deposit & credits wallet               ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      DEPOSIT CONFIRMED                               │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │  • Deposit status → 'confirmed'                                 ││
│  │  • User wallet USDT balance increased                           ││
│  │  • Transaction hash recorded                                    ││
│  │  • User notified via Telegram                                   ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
```

---

## Deposit Flow

### Step 1: User Initiates Deposit

**Frontend:** User clicks "Deposit" → Selects network → Enters amount

**API Call:** `POST /RockyTap/api/payments/deposit/init/`

```json
{
  "network": "trc20",
  "product_type": "wallet_topup",
  "amount_usdt": "100"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "deposit_id": 12345,
    "network": "trc20",
    "product_type": "wallet_topup",
    "address": "TNVnn7g2DgZTz4hiS2LdFWB8PJWvxqwmpn",
    "expected_amount_usdt": "100.00000000"
  }
}
```

### Step 2: User Sends USDT

User sends the exact amount of USDT to the displayed address using their wallet (Trust Wallet, MetaMask, etc.)

### Step 3: Automatic Detection

The cron job runs every 5 minutes:
1. Queries pending deposits from database
2. Calls blockchain explorer APIs to check for transactions
3. When a matching transaction is found, confirms the deposit

### Step 4: Wallet Credited

- Deposit status changes to "confirmed"
- User's wallet balance increases
- User receives notification

---

## API Keys Required

### 1. Etherscan API Key (for ERC20)

**Get it from:** https://etherscan.io/apis

1. Create account at etherscan.io
2. Go to API-Keys section
3. Create new API key
4. Free tier: 5 calls/second, 100,000 calls/day

### 2. BscScan API Key (for BEP20)

**Get it from:** https://bscscan.com/apis

1. Create account at bscscan.com
2. Go to API-Keys section
3. Create new API key
4. Free tier: 5 calls/second, 100,000 calls/day

### 3. TronGrid API Key (for TRC20)

**Get it from:** https://www.trongrid.io/

1. Create account at trongrid.io
2. Create new project
3. Get API key
4. Free tier: 100,000 calls/day (also works without key but rate limited)

### Add to `.env`:

```bash
ETHERSCAN_API_KEY=your_etherscan_key_here
BSCSCAN_API_KEY=your_bscscan_key_here
TRONGRID_API_KEY=your_trongrid_key_here
```

---

## Cron Job Setup

### Add to Server Crontab

```bash
# Edit crontab
crontab -e

# Add this line (runs every 5 minutes)
*/5 * * * * /usr/bin/php /var/www/html/RockyTap/cron/check_pending_deposits.php >> /var/log/ghidar/deposits.log 2>&1
```

### Create Log Directory

```bash
sudo mkdir -p /var/log/ghidar
sudo chown www-data:www-data /var/log/ghidar
```

### Test Manually

```bash
cd /var/www/html/RockyTap/cron
php check_pending_deposits.php
```

Expected output:
```
[2025-01-01 12:00:00] Starting deposit check...
Found 3 pending deposits
Checking deposit #123 (trc20): expecting 100.00000000 USDT
  No transactions found
Checking deposit #124 (bep20): expecting 50.00000000 USDT
  Found matching transaction: 0x123... for 50.00000000 USDT
  ✅ Deposit #124 confirmed! User wallet credited with 50.00000000 USDT
Checking deposit #125 (erc20): expecting 200.00000000 USDT
  No transactions found
[2025-01-01 12:00:05] Deposit check complete
```

---

## Admin API Endpoints

### 1. List Deposits

**Endpoint:** `GET /RockyTap/api/payments/deposit/list/`

**Headers:**
```
X-Admin-Key: your_admin_monitor_key
```

**Query Parameters:**
- `status` - Filter by status (pending, confirmed, failed)
- `user_id` - Filter by user ID
- `limit` - Max results (default: 50, max: 100)
- `offset` - Pagination offset

**Example:**
```bash
curl -X GET "https://ghidar.com/RockyTap/api/payments/deposit/list/?status=pending" \
  -H "X-Admin-Key: your_admin_key"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "deposits": [
      {
        "id": 123,
        "user_id": 456,
        "telegram_id": 8308397629,
        "username": "am1ezaa",
        "first_name": "Amirreza",
        "network": "trc20",
        "product_type": "wallet_topup",
        "status": "pending",
        "address": "TNVnn7g2DgZTz4hiS2LdFWB8PJWvxqwmpn",
        "expected_amount_usdt": "100.00000000",
        "actual_amount_usdt": null,
        "tx_hash": null,
        "created_at": "2025-01-01 12:00:00",
        "confirmed_at": null
      }
    ],
    "pagination": {
      "total": 15,
      "limit": 50,
      "offset": 0,
      "has_more": false
    }
  }
}
```

### 2. Manual Confirm Deposit

**Endpoint:** `POST /RockyTap/api/payments/deposit/admin-confirm/`

**Headers:**
```
Content-Type: application/json
X-Admin-Key: your_admin_monitor_key
```

**Request Body:**
```json
{
  "deposit_id": 123,
  "tx_hash": "0x1234567890abcdef...",
  "amount_usdt": "100.00"
}
```

**Example:**
```bash
curl -X POST "https://ghidar.com/RockyTap/api/payments/deposit/admin-confirm/" \
  -H "Content-Type: application/json" \
  -H "X-Admin-Key: your_admin_key" \
  -d '{"deposit_id": 123, "tx_hash": "0x123...", "amount_usdt": "100.00"}'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Deposit confirmed successfully",
    "deposit": {
      "id": 123,
      "user_id": 456,
      "network": "trc20",
      "status": "confirmed",
      "actual_amount_usdt": "100.00000000",
      "tx_hash": "0x123...",
      "confirmed_at": "2025-01-01 12:05:00"
    },
    "wallet": {
      "usdt_balance": "100.00000000",
      "ghd_balance": "0.00000000"
    }
  }
}
```

---

## Manual Confirmation

If the automatic system doesn't detect a deposit (network issues, API limits, etc.), you can manually confirm:

### Via cURL

```bash
curl -X POST "https://ghidar.com/RockyTap/api/payments/deposit/admin-confirm/" \
  -H "Content-Type: application/json" \
  -H "X-Admin-Key: YOUR_ADMIN_MONITOR_KEY" \
  -d '{
    "deposit_id": 123,
    "tx_hash": "actual_transaction_hash_from_blockchain_explorer",
    "amount_usdt": "100.00"
  }'
```

### Via Callback (Server-to-Server)

If you have a blockchain monitoring service:

```bash
curl -X POST "https://ghidar.com/RockyTap/api/payments/deposit/callback/" \
  -H "Content-Type: application/json" \
  -H "X-PAYMENTS-CALLBACK-TOKEN: YOUR_PAYMENTS_CALLBACK_TOKEN" \
  -d '{
    "deposit_id": 123,
    "network": "trc20",
    "tx_hash": "actual_transaction_hash",
    "amount_usdt": "100.00"
  }'
```

---

## Monitoring

### Check Pending Deposits

```sql
-- Get all pending deposits
SELECT id, user_id, network, expected_amount_usdt, address, created_at
FROM deposits
WHERE status = 'pending'
ORDER BY created_at DESC;

-- Count by status
SELECT status, COUNT(*) as count
FROM deposits
GROUP BY status;

-- Pending deposits older than 1 hour (may need attention)
SELECT *
FROM deposits
WHERE status = 'pending'
AND created_at < NOW() - INTERVAL 1 HOUR;
```

### Check Recent Confirmations

```sql
SELECT d.id, d.user_id, u.username, d.network, d.actual_amount_usdt, d.tx_hash, d.confirmed_at
FROM deposits d
LEFT JOIN users u ON d.user_id = u.id
WHERE d.status = 'confirmed'
ORDER BY d.confirmed_at DESC
LIMIT 20;
```

---

## Troubleshooting

### Issue: "No transactions found" for a deposit that was sent

**Possible causes:**
1. Transaction not yet confirmed on blockchain
2. API rate limit exceeded
3. Wrong network selected by user

**Solution:**
- Wait 10-15 minutes for blockchain confirmation
- Check transaction on blockchain explorer manually
- If confirmed, use manual confirmation endpoint

### Issue: API returning errors

**Etherscan/BscScan:**
```
NOTOK: Rate limit reached
```
**Solution:** Wait 1-2 minutes, or upgrade to paid API plan

**TronGrid:**
```
Out of API request limit
```
**Solution:** Add TRONGRID_API_KEY to .env

### Issue: Deposit confirmed but wallet not credited

**Possible cause:** Database transaction failed

**Solution:**
1. Check `deposits` table for status
2. Check `wallets` table for balance
3. If deposit is confirmed but wallet not updated, manually update:

```sql
UPDATE wallets
SET usdt_balance = usdt_balance + 100.00
WHERE user_id = 456;
```

### Issue: Cron job not running

**Check cron logs:**
```bash
grep CRON /var/log/syslog | tail -20
```

**Check if PHP is correct:**
```bash
which php
php -v
```

---

## Environment Variables Summary

```bash
#------------------------------------------------------------------------------
# DEPOSIT ADDRESSES (already configured in code)
#------------------------------------------------------------------------------
DEPOSIT_ADDRESS_ERC20=0x29841Ffa59A2831997A80840c76Ce94725E4ee5C
DEPOSIT_ADDRESS_BEP20=0x29841Ffa59A2831997A80840c76Ce94725E4ee5C
DEPOSIT_ADDRESS_TRC20=TNVnn7g2DgZTz4hiS2LdFWB8PJVvxqwmpn

#------------------------------------------------------------------------------
# BLOCKCHAIN API KEYS (REQUIRED - add these to .env)
#------------------------------------------------------------------------------
ETHERSCAN_API_KEY=15F6JQWRPJFUQ6X9NN5B2ABZK59WC1Q8VB
BSCSCAN_API_KEY=15F6JQWRPJFUQ6X9NN5B2ABZK59WC1Q8VB
TRONGRID_API_KEY=e50c224e-99f5-4246-a0f9-491be0182aa2

#------------------------------------------------------------------------------
# ADMIN ACCESS (generate a secure random key)
#------------------------------------------------------------------------------
ADMIN_MONITOR_KEY=your_secure_admin_key
# Generate with: openssl rand -hex 32

#------------------------------------------------------------------------------
# CALLBACK TOKEN (for server-to-server)
#------------------------------------------------------------------------------
PAYMENTS_CALLBACK_TOKEN=your_secure_callback_token
# Generate with: openssl rand -hex 32
```

---

## Complete Cron Job Commands

```bash
# ============================================================
# COPY-PASTE THESE COMMANDS TO SET UP EVERYTHING
# ============================================================

# 1. Create log directory
sudo mkdir -p /var/log/ghidar
sudo chown www-data:www-data /var/log/ghidar
sudo chmod 755 /var/log/ghidar

# 2. Add cron job (run this command)
(crontab -l 2>/dev/null; echo "*/2 * * * * /usr/bin/php /var/www/html/RockyTap/cron/check_pending_deposits.php >> /var/log/ghidar/deposits.log 2>&1") | crontab -

# 3. Verify cron job was added
crontab -l

# 4. Test manually
cd /var/www/html/RockyTap/cron && php check_pending_deposits.php

# 5. Watch the log in real-time (to monitor deposits)
tail -f /var/log/ghidar/deposits.log
```

---

## Notification Flow

When a user deposits:

1. **User opens deposit modal** → Sees deposit address
2. **User clicks "I've Sent the Funds"** → Telegram notification: "🔄 Deposit Pending"
3. **Cron job detects transaction** → Confirms deposit → Telegram notification: "✅ Deposit Confirmed!"

All notifications are sent directly to the user's Telegram bot chat.

---

*Last Updated: January 2025*

