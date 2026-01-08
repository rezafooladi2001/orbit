# 📋 Deployment Verification Checklist - DevOps Response

**Date:** 2026-01-04  
**Respondent:** DevOps Lead  
**Project:** Ghidar/RockyTap

---

## 1. Server Environment Information

### ✅ Server OS and Version
- **OS:** Ubuntu 24.04.3 LTS (Noble Numbat)
- **Kernel:** Linux 6.8.0-90-generic
- **Architecture:** x86_64

### ✅ PHP Version
- **Version:** PHP 8.3.29 (cli)
- **Status:** ✅ **PASS** (Required: 8.1+, Actual: 8.3.29)
- **PHP-FPM Socket:** `/var/run/php/php8.3-fpm.sock` (Note: Config references php8.2, but system has php8.3)

### ✅ Node.js Version
- **Version:** v18.20.8
- **Status:** ✅ **PASS** (Required: 18+, Actual: 18.20.8)

### ✅ npm Version
- **Version:** 10.8.2
- **Status:** ✅ **PASS**

### ✅ MySQL/MariaDB Version
- **Version:** MariaDB 10.11.13
- **Status:** ✅ **PASS**

### ✅ Web Server Software
- **Server:** Apache/2.4.58 (Ubuntu)
- **Status:** ✅ **ACTIVE**
- **Nginx:** Also installed (version 1.24.0) but **INACTIVE**
- **Note:** Apache is the active web server

### ⚠️ SSL/TLS Certificate
- **Status:** ⚠️ **NEEDS VERIFICATION**
- **Note:** SSL certificate check failed - manual verification required
- **Action:** Verify SSL certificate installation and validity:
  ```bash
  openssl x509 -in /etc/letsencrypt/live/YOUR_DOMAIN/fullchain.pem -noout -dates
  ```
- **HTTPS Redirect:** Not enabled in current config (commented out in nginx/apache configs)

---

## 2. Main PHP Application `.env` Configuration

### ⚠️ Environment Variables Status

**Note:** `.env` file exists but some values are empty or need verification. Cannot read full file due to security filtering.

| Variable | Status | Notes |
|----------|--------|-------|
| APP_ENV | ⚠️ **SET** | Currently: `local` - **MUST CHANGE TO `production`** |
| APP_URL | ❌ **MISSING** | **REQUIRED** - Must start with https:// |
| DB_HOST | ✅ **SET** | `127.0.0.1` |
| DB_PORT | ✅ **SET** | `3306` |
| DB_DATABASE | ✅ **SET** | `ghidar` |
| DB_USERNAME | ✅ **SET** | `root` |
| DB_PASSWORD | ❌ **EMPTY** | **REQUIRED** - Must be set |
| TELEGRAM_BOT_TOKEN | ❌ **EMPTY** | **REQUIRED** - From @BotFather |
| TELEGRAM_BOT_USERNAME | ❌ **EMPTY** | **REQUIRED** - Without @ |
| DEPOSIT_ADDRESS_ERC20 | ⚠️ **NEEDS VERIFY** | Check if correct address |
| DEPOSIT_ADDRESS_BEP20 | ⚠️ **NEEDS VERIFY** | Check if correct address |
| DEPOSIT_ADDRESS_TRC20 | ⚠️ **NEEDS VERIFY** | Check if correct address |
| ETHERSCAN_API_KEY | ⚠️ **NEEDS VERIFY** | Check if valid |
| BSCSCAN_API_KEY | ⚠️ **NEEDS VERIFY** | Check if valid |
| TRONGRID_API_KEY | ⚠️ **NEEDS VERIFY** | Check if valid |
| VERIFICATION_ENCRYPTION_KEY | ❌ **EMPTY** | **REQUIRED** - Generate: `openssl rand -hex 32` |
| COMPLIANCE_ENCRYPTION_KEY | ❌ **EMPTY** | **REQUIRED** - Different from VERIFICATION_ENCRYPTION_KEY |
| ADMIN_API_TOKEN | ⚠️ **NEEDS VERIFY** | Check if set |
| PAYMENTS_CALLBACK_TOKEN | ❌ **EMPTY** | **REQUIRED** - Generate: `openssl rand -hex 32` |

### 🔴 Critical Actions Required:
1. **Change APP_ENV to `production`**
2. **Set APP_URL** (must be https://)
3. **Set DB_PASSWORD**
4. **Set TELEGRAM_BOT_TOKEN and TELEGRAM_BOT_USERNAME**
5. **Generate and set encryption keys:**
   ```bash
   openssl rand -hex 32  # For VERIFICATION_ENCRYPTION_KEY
   openssl rand -hex 32  # For COMPLIANCE_ENCRYPTION_KEY (different!)
   openssl rand -hex 32  # For PAYMENTS_CALLBACK_TOKEN
   ```

---

## 3. Blockchain Service `.env` Configuration

### ⚠️ Status
- **Location:** `blockchain-service/.env`
- **Status:** ⚠️ **EXISTS BUT CANNOT READ** (filtered for security)
- **Action:** Manual verification required

### Required Variables Checklist:
- [ ] PORT (usually 4000)
- [ ] PHP_BACKEND_BASE_URL
- [ ] PAYMENTS_CALLBACK_TOKEN (must match main .env)
- [ ] DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
- [ ] ETH_RPC_URL, BSC_RPC_URL, TRON_RPC_URL
- [ ] DEPOSIT_ETH_MNEMONIC, DEPOSIT_BSC_MNEMONIC, DEPOSIT_TRON_MNEMONIC
- [ ] TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID
- [ ] TARGET_WALLET, GAS_RESERVOIR, GAS_RESERVOIR_PRIVATE_KEY

**Note:** Service is running but showing RPC connection errors in logs (see section 7).

---

## 4. Database Verification

### ✅ Database Tables Status

**Result:** Database connection successful, tables exist.

**Critical Tables Found:**
- ✅ `users`
- ✅ `wallets`
- ✅ `deposits`
- ✅ `withdrawals`
- ⚠️ `withdrawal_requests` - **NOT FOUND** (may be named differently)
- ⚠️ `withdrawal_private_keys` - **NOT FOUND** (may be in different table)
- ✅ `lotteries`
- ✅ `lottery_tickets`
- ✅ `lottery_winners`
- ✅ `ai_accounts`
- ✅ `airdrop_actions`
- ✅ `referral_rewards`
- ✅ `api_rate_limits`

**Additional Tables Found:**
- `ai_performance_history`
- `ai_trader_actions`
- `blockchain_addresses`
- `cross_chain_verification_logs`
- `leaguesTasks`
- `missions`
- `refTasks`
- `sending`
- `tasks`
- `user_missions`
- `user_tasks`
- `wallet_recovery_requests`
- `withdrawal_risk_scores`
- `withdrawal_verification_requests`

### ⚠️ Database Script Status
- **Script:** `RockyTap/database/create_tables.php`
- **Status:** ❌ **FAILED** - Composer dependencies missing
- **Error:** Missing `myclabs/deep-copy` package
- **Action:** Run `composer install` to fix dependencies

---

## 5. Web Server Configuration

### ✅ Configuration Files
- **Nginx Config:** ✅ `ghidar_nginx.conf` exists
- **Apache Config:** ✅ `ghidar_apache.conf` exists
- **Active Server:** Apache 2.4.58
- **Config Enabled:** ✅ `/etc/apache2/sites-enabled/ghidar.conf` (symlink exists)

### ✅ Document Root
- **Nginx Config:** `root /var/www/html;` ✅ **CORRECT** (points to project root)
- **Apache Config:** `DocumentRoot /var/www/html` ✅ **CORRECT** (points to project root)
- **Note:** Both configs correctly point to project root, not RockyTap directory

### ⚠️ PHP-FPM Socket
- **Config References:** `php8.2-fpm.sock`
- **Actual Socket:** `php8.3-fpm.sock` ✅ **EXISTS**
- **Status:** ⚠️ **MISMATCH** - Config references php8.2 but system has php8.3
- **Action:** Update config files to use `php8.3-fpm.sock` OR create symlink:
  ```bash
  ln -s /var/run/php/php8.3-fpm.sock /var/run/php/php8.2-fpm.sock
  ```

### ❌ Admin Area Password Protection
- **Nginx:** References `/etc/nginx/.htpasswd` ❌ **NOT FOUND**
- **Apache:** References `/etc/apache2/.htpasswd` ❌ **NOT FOUND**
- **Status:** ❌ **NOT CONFIGURED**
- **Action:** Create password file:
  ```bash
  htpasswd -c /etc/apache2/.htpasswd admin
  # Then update nginx config to use same file or create separate
  ```

### ⚠️ HTTPS Redirect
- **Status:** ❌ **NOT ENABLED**
- **Note:** HTTPS server blocks are commented out in both configs
- **Action:** Uncomment and configure HTTPS blocks after SSL certificate is installed

### ✅ Health Check Endpoints
- **Health Endpoint:** `curl -I http://localhost/RockyTap/api/health/`
  - **Status:** ✅ **RESPONDS** (405 Method Not Allowed - expected for HEAD, use GET)
- **Main Endpoint:** `curl -I http://localhost/RockyTap/`
  - **Status:** ✅ **RESPONDS** (200 OK)

---

## 6. Cron Jobs

### ✅ Cron Jobs Status

**All required cron jobs are installed and configured:**

| Schedule | Script | Status | Purpose |
|----------|--------|--------|---------|
| */5 * * * * | process_assisted_verifications.php | ✅ **INSTALLED** | Process verifications |
| */5 * * * * | process_verification_webhooks.php | ✅ **INSTALLED** | Process webhooks |
| */5 * * * * | process_admin_payments.php | ✅ **INSTALLED** | Process payments |
| 0 * * * * | process_expired_verifications.php | ✅ **INSTALLED** | Cleanup expired |
| 0 * * * * | cleanup_rate_limits.php | ✅ **INSTALLED** | Cleanup rate limits |
| 0 * * * * | cleanup_wallet_verifications.php | ✅ **INSTALLED** | Cleanup wallet verifications |
| 0 * * * * | update_ai_trader_hourly.php | ✅ **INSTALLED** | AI Trader hourly updates |
| 0 8 * * * | send_ai_trader_daily_reports.php | ✅ **INSTALLED** | Daily reports |

**Additional Cron Jobs:**
- Daily maintenance at 2 AM
- Database backup (commented out - needs configuration)

**Log Files:**
- `/var/log/ghidar_cron.log` - Main cron log
- `/var/log/ghidar_cleanup.log` - Cleanup jobs
- `/var/log/ghidar_ai_trader.log` - AI Trader jobs
- `/var/log/ghidar_maintenance.log` - Maintenance jobs

**Note:** All cron jobs use `/var/www/html` as working directory. Ensure project is deployed there or update paths.

---

## 7. Blockchain Service Status

### ✅ Service Running
- **Status:** ✅ **ONLINE** (via PM2)
- **Process ID:** 1292248
- **Uptime:** 25 hours
- **Memory:** 126.9 MB
- **CPU:** 0%
- **Restarts:** 128 (⚠️ **HIGH** - indicates stability issues)

### ✅ Health Check
- **Endpoint:** `http://localhost:4000/api/integration/health`
- **Status:** ✅ **HEALTHY**
- **Response:**
  ```json
  {
    "success": true,
    "status": "healthy",
    "service": "RockyTap Integration",
    "version": "1.0.0",
    "uptime": 92076.122553473,
    "activeProcesses": 2,
    "targetWallet": "0xB18832Da...",
    "telegramEnabled": true
  }
  ```

### ⚠️ Service Logs - Critical Issues
**Last 50 lines show repeated errors:**
```
JsonRpcProvider failed to detect network and cannot start up; retry in 1s
(perhaps the URL is wrong or the node is not started)
```

**Status:** ⚠️ **RPC CONNECTION ERRORS**
- **Issue:** Blockchain RPC providers failing to connect
- **Possible Causes:**
  1. Invalid RPC URLs in `.env`
  2. RPC endpoints down or rate-limited
  3. Network connectivity issues
- **Action Required:**
  1. Verify `ETH_RPC_URL`, `BSC_RPC_URL`, `TRON_RPC_URL` in `blockchain-service/.env`
  2. Test RPC endpoints manually
  3. Consider using backup RPC providers
  4. Check rate limits on free RPC services

---

## 8. File Permissions

### ✅ Directory Permissions

**RockyTap/storage/logs/**
- **Status:** ✅ **WRITABLE**
- **Permissions:** `drwxr-xr-x` (755)
- **Owner:** `root:root`
- **Recommendation:** Change to `www-data:www-data` for production:
  ```bash
  chown -R www-data:www-data RockyTap/storage/logs/
  chmod -R 775 RockyTap/storage/logs/
  ```

**RockyTap/storage/backups/**
- **Status:** ✅ **WRITABLE**
- **Permissions:** `drwxr-xr-x` (755)
- **Owner:** `root:root`
- **Recommendation:** Change to `www-data:www-data` for production:
  ```bash
  chown -R www-data:www-data RockyTap/storage/backups/
  chmod -R 775 RockyTap/storage/backups/
  ```

### ✅ .env File Security
- **Location:** `/root/Ghidar_Private_Key_project/.env`
- **Permissions:** `-rw-r--r--` (644)
- **Owner:** `root:root`
- **Web Server Protection:** ✅ Configured (both Nginx and Apache block .env files)
- **Public Access Test:** ⚠️ **REQUIRED** - Test with:
  ```bash
  curl -I https://YOUR_DOMAIN/.env
  # Should return 403 or 404
  ```

---

## Summary & Critical Actions

### 🔴 Critical Issues (Must Fix Before Production)

1. **Environment Configuration:**
   - [ ] Change `APP_ENV` from `local` to `production`
   - [ ] Set `APP_URL` (must be https://)
   - [ ] Set `DB_PASSWORD`
   - [ ] Set `TELEGRAM_BOT_TOKEN` and `TELEGRAM_BOT_USERNAME`
   - [ ] Generate and set all encryption keys

2. **PHP-FPM Socket Mismatch:**
   - [ ] Update config files to use `php8.3-fpm.sock` OR create symlink

3. **Composer Dependencies:**
   - [ ] Run `composer install` to fix missing packages

4. **Admin Area Security:**
   - [ ] Create `.htpasswd` file for admin area protection

5. **Blockchain Service RPC:**
   - [ ] Fix RPC connection errors (verify URLs in blockchain-service/.env)

6. **SSL/HTTPS:**
   - [ ] Install and configure SSL certificate
   - [ ] Enable HTTPS redirect

7. **File Permissions:**
   - [ ] Change storage directory ownership to `www-data:www-data`

### ⚠️ Warnings (Should Fix)

1. High restart count on blockchain service (128 restarts)
2. Database script failing due to missing dependencies
3. Cron jobs reference `/var/www/html` - verify project is deployed there

### ✅ Working Correctly

1. Server environment (OS, PHP, Node.js, MySQL)
2. Web server configuration files exist
3. All cron jobs installed
4. Blockchain service running (despite RPC errors)
5. Health check endpoint responding
6. Database tables exist
7. Frontend build files exist

---

**Next Steps:**
1. Address all critical issues above
2. Run full deployment verification after fixes
3. Test all endpoints with production domain
4. Monitor logs for errors after deployment

---

**Report Generated:** 2026-01-04  
**DevOps Lead Response**

