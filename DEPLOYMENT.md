# Deployment Guide for Ghidar

This guide provides comprehensive instructions for safely deploying the Ghidar application with proper backup, verification, and rollback procedures.

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Deployment Process](#deployment-process)
3. [Rollback Procedures](#rollback-procedures)
4. [Troubleshooting](#troubleshooting)
5. [Emergency Recovery](#emergency-recovery)

## Pre-Deployment Checklist

Before deploying, ensure all items are checked:

### System Requirements

- [ ] PHP 8.1+ installed and verified
- [ ] MySQL/MariaDB 5.7+ or TiDB accessible
- [ ] Composer installed and up to date
- [ ] Node.js 18+ and npm (for frontend builds)
- [ ] At least 1GB free disk space
- [ ] Backup directories are writable

### Version Control

- [ ] Git repository initialized (`scripts/setup_git.sh`)
- [ ] All changes committed to Git
- [ ] Current branch is `main` or `master`
- [ ] Remote repository configured (optional but recommended)

### Configuration

- [ ] `.env` file exists and is configured
- [ ] Database credentials are correct
- [ ] Telegram Bot Token is set
- [ ] All required environment variables are set

### Pre-Deployment Checks

Run the pre-deployment check script:

```bash
cd /path/to/project
./RockyTap/scripts/pre_deployment_check.sh
```

This will verify:
- Git repository status
- Environment configuration
- Database connectivity
- Disk space
- Prerequisites
- File permissions

**Do not proceed if the check script reports errors.**

## Deployment Process

### Step 1: Run Pre-Deployment Checks

```bash
./RockyTap/scripts/pre_deployment_check.sh
```

Fix any errors before proceeding.

### Step 2: Create Manual Backup (Optional but Recommended)

Before automated deployment, create a manual backup:

```bash
./RockyTap/scripts/backup_manager.sh backup
```

This creates backups of:
- Code
- Database
- `.env` file

### Step 3: Run Deployment Script

Execute the deployment script:

```bash
./RockyTap/scripts/deploy_production.sh
```

The script will:
1. Check prerequisites
2. Create automatic backups (code, database, `.env`)
3. Verify backup integrity
4. Deploy new code
5. Install PHP dependencies
6. Build frontend (if Node.js available)
7. Run database migrations safely
8. Set up cron jobs
9. Configure monitoring
10. Run tests
11. Verify deployment

### Step 4: Verify Deployment

After deployment completes:

1. **Check application health:**
   ```bash
   curl https://yourdomain.com/RockyTap/api/health/
   ```

2. **Test critical functionality:**
   - User login
   - API endpoints
   - Database operations

3. **Monitor logs:**
   ```bash
   tail -f RockyTap/storage/logs/ghidar.log
   ```

4. **Check for errors:**
   ```bash
   grep -i error RockyTap/storage/logs/ghidar.log
   ```

## Rollback Procedures

If deployment fails or issues are discovered, use the rollback script to restore the previous state.

### Quick Rollback (Latest Backup)

Rollback to the most recent backup:

```bash
./RockyTap/scripts/rollback.sh --latest
```

### Rollback by Timestamp

Rollback to a specific deployment timestamp:

```bash
./RockyTap/scripts/rollback.sh --timestamp 20240115_143022
```

### Interactive Rollback

For more control, use interactive mode:

```bash
./RockyTap/scripts/rollback.sh
```

Options:
1. Rollback by timestamp (restores code, database, and `.env` from same deployment)
2. Rollback code only
3. Rollback database only
4. Rollback `.env` only
5. Cancel

### List Available Backups

View all available backups:

```bash
./RockyTap/scripts/rollback.sh --list
```

### Manual Rollback Steps

If the rollback script is unavailable:

1. **Restore code:**
   ```bash
   cd /path/to/project
   tar -xzf RockyTap/storage/backups/code/code_backup_TIMESTAMP.tar.gz
   ```

2. **Restore database:**
   ```bash
   mysql -u USERNAME -p DATABASE < RockyTap/storage/backups/database/database_backup_TIMESTAMP.sql
   ```

3. **Restore .env:**
   ```bash
   cp RockyTap/storage/backups/env/env_backup_TIMESTAMP.env .env
   ```

## Troubleshooting

### Deployment Fails During Backup

**Problem:** Backup creation fails

**Solution:**
1. Check disk space: `df -h`
2. Verify backup directory permissions: `ls -la RockyTap/storage/backups/`
3. Check database credentials in `.env`
4. Ensure `mysqldump` is installed

### Migration Fails

**Problem:** Database migration fails

**Solution:**
1. Check migration logs: `tail -f RockyTap/storage/logs/migrations.log`
2. Test migration in dry-run mode:
   ```bash
   php RockyTap/scripts/safe_migration.php RockyTap/database/migrate_file.php --dry-run
   ```
3. Restore database from backup if needed
4. Fix migration file and retry

### Frontend Build Fails

**Problem:** Frontend build fails during deployment

**Solution:**
1. Check Node.js version: `node -v` (should be 18+)
2. Check npm version: `npm -v`
3. Clear npm cache: `npm cache clean --force`
4. Remove node_modules and reinstall:
   ```bash
   cd RockyTap/webapp
   rm -rf node_modules package-lock.json
   npm install
   npm run build
   ```

### .env File Overwritten

**Problem:** `.env` file was overwritten during deployment

**Solution:**
1. Restore from backup:
   ```bash
   cp RockyTap/storage/backups/env/env_backup_LATEST.env .env
   ```
2. Verify configuration
3. The deployment script now protects `.env` from being overwritten

### Database Connection Errors

**Problem:** Application cannot connect to database after deployment

**Solution:**
1. Verify `.env` file has correct credentials
2. Test database connection:
   ```bash
   mysql -u DB_USERNAME -p DB_DATABASE
   ```
3. Check database server is running
4. Verify network connectivity to database host

### Code Corruption

**Problem:** Code files appear corrupted after deployment

**Solution:**
1. Immediately rollback:
   ```bash
   ./RockyTap/scripts/rollback.sh --latest
   ```
2. Verify Git repository:
   ```bash
   git status
   git log --oneline -10
   ```
3. Restore from Git if needed:
   ```bash
   git checkout HEAD -- .
   ```

## Emergency Recovery

### Complete System Recovery

If the system is completely broken:

1. **Stop the application** (if possible)

2. **Restore from latest backup:**
   ```bash
   ./RockyTap/scripts/rollback.sh --latest
   ```

3. **If rollback script fails, manual recovery:**
   ```bash
   # Find latest backups
   ls -lt RockyTap/storage/backups/code/
   ls -lt RockyTap/storage/backups/database/
   ls -lt RockyTap/storage/backups/env/
   
   # Restore code
   cd /path/to/project
   tar -xzf RockyTap/storage/backups/code/code_backup_LATEST.tar.gz
   
   # Restore database
   mysql -u USERNAME -p DATABASE < RockyTap/storage/backups/database/database_backup_LATEST.sql
   
   # Restore .env
   cp RockyTap/storage/backups/env/env_backup_LATEST.env .env
   ```

4. **Verify restoration:**
   ```bash
   ./RockyTap/scripts/pre_deployment_check.sh
   ```

5. **Test application:**
   ```bash
   curl https://yourdomain.com/RockyTap/api/health/
   ```

### Database-Only Recovery

If only the database needs recovery:

```bash
# List database backups
ls -lt RockyTap/storage/backups/database/

# Restore specific backup
mysql -u USERNAME -p DATABASE < RockyTap/storage/backups/database/database_backup_TIMESTAMP.sql
```

### Code-Only Recovery

If only code needs recovery:

```bash
# List code backups
ls -lt RockyTap/storage/backups/code/

# Extract backup
cd /path/to/project
tar -xzf RockyTap/storage/backups/code/code_backup_TIMESTAMP.tar.gz
```

## Backup Management

### Automated Backups

Set up automated daily backups via cron:

```bash
# Add to crontab
0 2 * * * cd /path/to/project && ./RockyTap/scripts/backup_manager.sh backup
```

### Manual Backup

Create a backup at any time:

```bash
./RockyTap/scripts/backup_manager.sh backup
```

### List Backups

View all backups:

```bash
./RockyTap/scripts/backup_manager.sh list
```

### Verify Backups

Verify backup integrity:

```bash
./RockyTap/scripts/backup_manager.sh verify
```

### Cleanup Old Backups

Manually clean up old backups (automatic cleanup happens during deployment):

```bash
./RockyTap/scripts/backup_manager.sh cleanup
```

## Safe Migration Usage

### Dry-Run Migration

Test a migration without executing it:

```bash
php RockyTap/scripts/safe_migration.php RockyTap/database/migrate_file.php --dry-run
```

### Execute Migration

Run a migration safely:

```bash
php RockyTap/scripts/safe_migration.php RockyTap/database/migrate_file.php
```

### Migration Without Backup

Skip backup (not recommended):

```bash
php RockyTap/scripts/safe_migration.php RockyTap/database/migrate_file.php --no-backup
```

### Migration Without Transaction

Skip transaction support (not recommended):

```bash
php RockyTap/scripts/safe_migration.php RockyTap/database/migrate_file.php --no-transaction
```

## Best Practices

1. **Always run pre-deployment checks** before deploying
2. **Create manual backups** before major deployments
3. **Test migrations in dry-run mode** first
4. **Deploy during low-traffic periods** when possible
5. **Monitor logs** after deployment
6. **Keep backups for at least 30 days**
7. **Document any manual changes** made during deployment
8. **Use Git** for version control and rollback capability
9. **Never skip backup verification**
10. **Have a rollback plan** ready before deploying

## Backup Retention Policy

- **Daily backups:** Kept for 30 days
- **Monthly backups:** Kept for 1 year
- **Pre-migration backups:** Kept until next successful migration
- **Pre-rollback backups:** Kept for 7 days

## Support and Help

If you encounter issues not covered in this guide:

1. Check application logs: `RockyTap/storage/logs/ghidar.log`
2. Check migration logs: `RockyTap/storage/logs/migrations.log`
3. Check deployment logs: `RockyTap/storage/backups/deployment_*.log`
4. Review Git history: `git log --oneline -20`

## Quick Reference

### Essential Commands

```bash
# Pre-deployment check
./RockyTap/scripts/pre_deployment_check.sh

# Deploy
./RockyTap/scripts/deploy_production.sh

# Rollback to latest
./RockyTap/scripts/rollback.sh --latest

# List backups
./RockyTap/scripts/rollback.sh --list

# Create backup
./RockyTap/scripts/backup_manager.sh backup

# Test migration
php RockyTap/scripts/safe_migration.php MIGRATION_FILE --dry-run
```

### Backup Locations

- Code backups: `RockyTap/storage/backups/code/`
- Database backups: `RockyTap/storage/backups/database/`
- .env backups: `RockyTap/storage/backups/env/`
- Logs: `RockyTap/storage/logs/`

---

**Last Updated:** 2024-01-15
**Version:** 1.0

