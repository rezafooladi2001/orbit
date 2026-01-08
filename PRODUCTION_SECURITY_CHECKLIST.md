# Production Security Checklist

Use this checklist before deploying to production. All items must be verified.

---

## 1. Environment Configuration

### PHP Backend (.env)

- [ ] `APP_ENV=production` - Ensures error details are hidden from API responses
- [ ] `TELEGRAM_BOT_TOKEN` - Set to your actual bot token from @BotFather
- [ ] `VERIFICATION_ENCRYPTION_KEY` - Generated with `openssl rand -hex 32` (NOT the example value)
- [ ] `COMPLIANCE_ENCRYPTION_KEY` - Generated with `openssl rand -hex 32` (different from above)
- [ ] `PAYMENTS_CALLBACK_TOKEN` - Generated with `openssl rand -hex 32`
- [ ] `ADMIN_API_TOKEN` - Generated with `openssl rand -hex 32`
- [ ] `ADMIN_MONITOR_KEY` - Generated with `openssl rand -hex 32`
- [ ] `CORS_ALLOWED_ORIGINS` - Set to empty string or specific webapp domains (NOT `*`)
- [ ] `ETHERSCAN_API_KEY`, `BSCSCAN_API_KEY`, `TRONGRID_API_KEY` - Get your own API keys
- [ ] `DEPOSIT_ADDRESS_*` - Set to your own wallet addresses

### Blockchain Service (.env)

- [ ] `NODE_ENV=production` - Enables production security settings
- [ ] `PHP_BACKEND_BASE_URL` - Set to your production PHP backend URL
- [ ] `PAYMENTS_CALLBACK_TOKEN` - Same as PHP backend token
- [ ] `ALLOWED_ORIGINS` - Set to your webapp domains
- [ ] `DEPOSIT_*_MNEMONIC` - Generated using secure method, stored encrypted
- [ ] All RPC URLs configured and tested

---

## 2. Database Security

- [ ] Create dedicated database user with minimal privileges:
  ```sql
  CREATE USER 'ghidar_app'@'%' IDENTIFIED BY 'strong_random_password';
  GRANT SELECT, INSERT, UPDATE, DELETE ON ghidar.* TO 'ghidar_app'@'%';
  FLUSH PRIVILEGES;
  ```
- [ ] Database user does NOT have DROP, ALTER, CREATE, GRANT privileges
- [ ] SSL/TLS enabled for database connections (especially for cloud databases)
- [ ] Database backups configured and tested
- [ ] Connection pooling configured if using high traffic

---

## 3. Web Server Configuration

### Apache/Nginx

- [ ] HTTPS enabled with valid SSL certificate
- [ ] HTTP to HTTPS redirect configured
- [ ] HSTS header enabled
- [ ] Server version headers hidden
- [ ] `.env` files blocked from public access
- [ ] Storage and vendor directories blocked from public access
- [ ] Rate limiting configured at web server level

### PHP

- [ ] `display_errors = Off` in php.ini
- [ ] `error_reporting = 0` in php.ini (or log only)
- [ ] `expose_php = Off` in php.ini
- [ ] Appropriate `memory_limit` and `max_execution_time`
- [ ] APCu or Redis configured for rate limiting cache

---

## 4. Cron Jobs

- [ ] Cleanup jobs scheduled:
  - `cleanup_rate_limits.php` - Daily
  - `cleanup_wallet_verifications.php` - Daily
  - `process_withdrawals.php` - Every 5 minutes
  - `process_assisted_verifications.php` - Every 5 minutes

- [ ] Cron user has minimal file system permissions
- [ ] Cron output logged and monitored

---

## 5. Monitoring & Alerts

- [ ] Health endpoint accessible: `GET /RockyTap/api/health/`
- [ ] Log monitoring configured for:
  - Failed authentication attempts
  - Rate limit violations
  - Database connection errors
  - Exception/error spikes

- [ ] Disk space monitoring enabled
- [ ] Database connection monitoring enabled

---

## 6. Security Features Verified

- [ ] Telegram auth_date validation enabled (rejects data older than 24h)
- [ ] Rate limiting active on all financial endpoints
- [ ] CSRF protection active on admin forms
- [ ] Admin 2FA configured
- [ ] Sensitive data redacted from logs

---

## 7. Blockchain Service

- [ ] npm packages updated and vulnerabilities fixed (`npm audit fix`)
- [ ] Mnemonics validated for security (no test values)
- [ ] Block confirmations configured appropriately:
  - ETH: 12+ confirmations
  - BSC: 15+ confirmations
  - TRON: 20+ confirmations

---

## 8. File Permissions

- [ ] `.env` files: `600` (owner read/write only)
- [ ] PHP files: `644` (owner read/write, others read)
- [ ] Storage directory: `755` (owner full, others read/execute)
- [ ] Log files: `644` (owner read/write, others read)
- [ ] Private keys/mnemonics: `600` or stored in secure vault

---

## 9. Pre-Launch Testing

- [ ] Test deposit flow end-to-end
- [ ] Test withdrawal flow with verification
- [ ] Test rate limiting triggers correctly
- [ ] Test error handling (no stack traces leaked)
- [ ] Test CORS blocks unauthorized origins
- [ ] Verify no sensitive data in logs
- [ ] Load test critical endpoints

---

## 10. Backup & Recovery

- [ ] Database backup strategy in place
- [ ] Mnemonic/private key backup stored securely offline
- [ ] Recovery procedure documented and tested
- [ ] Incident response plan documented

---

## Quick Security Commands

Generate random secrets:
```bash
openssl rand -hex 32
```

Generate secure password:
```bash
openssl rand -base64 32
```

Check PHP configuration:
```bash
php -i | grep -E "(display_errors|error_reporting|expose_php)"
```

Test health endpoint:
```bash
curl -s https://yourdomain.com/RockyTap/api/health/ | jq
```

Run npm audit:
```bash
cd blockchain-service && npm audit
```

---

## Emergency Contacts

Document your emergency contacts here:

- **Security Lead**: 
- **DevOps Lead**: 
- **Database Admin**: 
- **Blockchain/Crypto Specialist**: 

---

**Last Updated**: $(date)
**Reviewed By**: _________________

