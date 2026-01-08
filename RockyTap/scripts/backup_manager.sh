#!/bin/bash

# Backup Manager for Ghidar
# Automated backup system with retention policy and verification

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Get project root directory
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$PROJECT_ROOT"

# Backup directories
BACKUP_BASE_DIR="RockyTap/storage/backups"
BACKUP_CODE_DIR="$BACKUP_BASE_DIR/code"
BACKUP_DB_DIR="$BACKUP_BASE_DIR/database"
BACKUP_ENV_DIR="$BACKUP_BASE_DIR/env"

# Retention policy (days)
DAILY_RETENTION_DAYS=30
MONTHLY_RETENTION_DAYS=365

# Ensure backup directories exist
mkdir -p "$BACKUP_CODE_DIR" "$BACKUP_DB_DIR" "$BACKUP_ENV_DIR"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$BACKUP_BASE_DIR/backup_manager.log"
}

# Backup .env file
backup_env() {
    if [ ! -f ".env" ]; then
        log "${YELLOW}⚠️  .env file not found, skipping${NC}"
        return 0
    fi
    
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_file="$BACKUP_ENV_DIR/env_backup_${timestamp}.env"
    
    cp ".env" "$backup_file"
    
    if [ -f "$backup_file" ] && [ -s "$backup_file" ]; then
        log "${GREEN}✅ .env backed up: $backup_file${NC}"
        return 0
    else
        log "${RED}❌ .env backup failed${NC}"
        return 1
    fi
}

# Backup database
backup_database() {
    if [ ! -f ".env" ]; then
        log "${YELLOW}⚠️  .env file not found, skipping database backup${NC}"
        return 0
    fi
    
    set +a
    source .env 2>/dev/null || true
    set -a
    
    if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
        log "${YELLOW}⚠️  Database credentials not found, skipping database backup${NC}"
        return 0
    fi
    
    if ! command -v mysqldump &> /dev/null; then
        log "${YELLOW}⚠️  mysqldump not found, skipping database backup${NC}"
        return 0
    fi
    
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_file="$BACKUP_DB_DIR/database_backup_${timestamp}.sql"
    
    # Create database backup
    if mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        --quick \
        --lock-tables=false \
        "$DB_DATABASE" > "$backup_file" 2>/dev/null; then
        
        if [ -f "$backup_file" ] && [ -s "$backup_file" ]; then
            local size=$(du -h "$backup_file" | cut -f1)
            log "${GREEN}✅ Database backed up: $backup_file (${size})${NC}"
            return 0
        else
            log "${RED}❌ Database backup file is empty${NC}"
            rm -f "$backup_file"
            return 1
        fi
    else
        log "${RED}❌ Database backup failed${NC}"
        return 1
    fi
}

# Backup code
backup_code() {
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_file="$BACKUP_CODE_DIR/code_backup_${timestamp}.tar.gz"
    
    # Create code backup
    if tar -czf "$backup_file" \
        --exclude="./node_modules" \
        --exclude="./vendor" \
        --exclude="./.git" \
        --exclude="./RockyTap/storage/backups" \
        --exclude="./RockyTap/storage/logs/*.log" \
        --exclude="./.env" \
        . 2>/dev/null; then
        
        # Verify backup integrity
        if tar -tzf "$backup_file" > /dev/null 2>&1; then
            local size=$(du -h "$backup_file" | cut -f1)
            log "${GREEN}✅ Code backed up: $backup_file (${size})${NC}"
            return 0
        else
            log "${RED}❌ Code backup archive is corrupted${NC}"
            rm -f "$backup_file"
            return 1
        fi
    else
        log "${RED}❌ Code backup failed${NC}"
        return 1
    fi
}

# Verify backup integrity
verify_backup() {
    local backup_file="$1"
    local backup_type="$2"
    
    if [ ! -f "$backup_file" ]; then
        log "${RED}❌ Backup file not found: $backup_file${NC}"
        return 1
    fi
    
    if [ ! -s "$backup_file" ]; then
        log "${RED}❌ Backup file is empty: $backup_file${NC}"
        return 1
    fi
    
    case "$backup_type" in
        code)
            if tar -tzf "$backup_file" > /dev/null 2>&1; then
                log "${GREEN}✅ Code backup verified: $backup_file${NC}"
                return 0
            else
                log "${RED}❌ Code backup verification failed: $backup_file${NC}"
                return 1
            fi
            ;;
        database)
            # Basic SQL check
            if head -n 1 "$backup_file" | grep -q "MySQL dump\|-- Dump of\|CREATE TABLE\|INSERT INTO"; then
                log "${GREEN}✅ Database backup verified: $backup_file${NC}"
                return 0
            else
                log "${YELLOW}⚠️  Database backup may be invalid: $backup_file${NC}"
                return 0  # Don't fail, just warn
            fi
            ;;
        env)
            if [ -s "$backup_file" ]; then
                log "${GREEN}✅ .env backup verified: $backup_file${NC}"
                return 0
            else
                log "${RED}❌ .env backup verification failed: $backup_file${NC}"
                return 1
            fi
            ;;
    esac
}

# Cleanup old backups based on retention policy
cleanup_old_backups() {
    log "Cleaning up old backups..."
    
    local current_date=$(date +%s)
    local daily_cutoff=$((current_date - (DAILY_RETENTION_DAYS * 86400)))
    local monthly_cutoff=$((current_date - (MONTHLY_RETENTION_DAYS * 86400)))
    
    # Clean code backups (keep daily for 30 days, monthly for 1 year)
    local deleted=0
    for backup in "$BACKUP_CODE_DIR"/*.tar.gz; do
        if [ -f "$backup" ]; then
            local file_date=$(stat -f %m "$backup" 2>/dev/null || stat -c %Y "$backup" 2>/dev/null)
            local file_name=$(basename "$backup")
            
            # Check if it's a monthly backup (first of month)
            local is_monthly=false
            if echo "$file_name" | grep -qE 'code_backup_[0-9]{8}_000000'; then
                is_monthly=true
            fi
            
            if [ "$is_monthly" = true ]; then
                if [ "$file_date" -lt "$monthly_cutoff" ]; then
                    rm -f "$backup"
                    ((deleted++))
                fi
            else
                if [ "$file_date" -lt "$daily_cutoff" ]; then
                    rm -f "$backup"
                    ((deleted++))
                fi
            fi
        fi
    done
    
    # Clean database backups
    for backup in "$BACKUP_DB_DIR"/*.sql; do
        if [ -f "$backup" ]; then
            local file_date=$(stat -f %m "$backup" 2>/dev/null || stat -c %Y "$backup" 2>/dev/null)
            if [ "$file_date" -lt "$daily_cutoff" ]; then
                rm -f "$backup"
                ((deleted++))
            fi
        fi
    done
    
    # Clean .env backups (keep for 30 days)
    for backup in "$BACKUP_ENV_DIR"/*.env; do
        if [ -f "$backup" ]; then
            local file_date=$(stat -f %m "$backup" 2>/dev/null || stat -c %Y "$backup" 2>/dev/null)
            if [ "$file_date" -lt "$daily_cutoff" ]; then
                rm -f "$backup"
                ((deleted++))
            fi
        fi
    done
    
    if [ $deleted -gt 0 ]; then
        log "${GREEN}✅ Cleaned up $deleted old backup(s)${NC}"
    else
        log "${GREEN}✅ No old backups to clean${NC}"
    fi
}

# Create full backup
create_full_backup() {
    log "=========================================="
    log "Creating full backup..."
    log "=========================================="
    
    local success=0
    
    backup_env && ((success++))
    backup_database && ((success++))
    backup_code && ((success++))
    
    if [ $success -eq 3 ]; then
        log "${GREEN}✅ Full backup completed successfully${NC}"
        return 0
    else
        log "${YELLOW}⚠️  Full backup completed with some failures${NC}"
        return 1
    fi
}

# List backups
list_backups() {
    echo ""
    echo "=== Backup Summary ==="
    echo ""
    
    echo -e "${GREEN}Code Backups:${NC}"
    local code_count=$(ls -1 "$BACKUP_CODE_DIR"/*.tar.gz 2>/dev/null | wc -l)
    echo "  Total: $code_count"
    if [ $code_count -gt 0 ]; then
        echo "  Latest: $(ls -t "$BACKUP_CODE_DIR"/*.tar.gz 2>/dev/null | head -1 | xargs basename)"
        echo "  Size: $(du -sh "$BACKUP_CODE_DIR" 2>/dev/null | cut -f1)"
    fi
    
    echo ""
    echo -e "${GREEN}Database Backups:${NC}"
    local db_count=$(ls -1 "$BACKUP_DB_DIR"/*.sql 2>/dev/null | wc -l)
    echo "  Total: $db_count"
    if [ $db_count -gt 0 ]; then
        echo "  Latest: $(ls -t "$BACKUP_DB_DIR"/*.sql 2>/dev/null | head -1 | xargs basename)"
        echo "  Size: $(du -sh "$BACKUP_DB_DIR" 2>/dev/null | cut -f1)"
    fi
    
    echo ""
    echo -e "${GREEN}.env Backups:${NC}"
    local env_count=$(ls -1 "$BACKUP_ENV_DIR"/*.env 2>/dev/null | wc -l)
    echo "  Total: $env_count"
    if [ $env_count -gt 0 ]; then
        echo "  Latest: $(ls -t "$BACKUP_ENV_DIR"/*.env 2>/dev/null | head -1 | xargs basename)"
    fi
    
    echo ""
    echo "Total backup size: $(du -sh "$BACKUP_BASE_DIR" 2>/dev/null | cut -f1)"
    echo ""
}

# Verify all backups
verify_all_backups() {
    log "Verifying all backups..."
    
    local verified=0
    local failed=0
    
    # Verify code backups
    for backup in "$BACKUP_CODE_DIR"/*.tar.gz; do
        if [ -f "$backup" ]; then
            if verify_backup "$backup" "code"; then
                ((verified++))
            else
                ((failed++))
            fi
        fi
    done
    
    # Verify database backups
    for backup in "$BACKUP_DB_DIR"/*.sql; do
        if [ -f "$backup" ]; then
            if verify_backup "$backup" "database"; then
                ((verified++))
            else
                ((failed++))
            fi
        fi
    done
    
    # Verify .env backups
    for backup in "$BACKUP_ENV_DIR"/*.env; do
        if [ -f "$backup" ]; then
            if verify_backup "$backup" "env"; then
                ((verified++))
            else
                ((failed++))
            fi
        fi
    done
    
    log "Verification complete: $verified verified, $failed failed"
    
    if [ $failed -eq 0 ]; then
        return 0
    else
        return 1
    fi
}

# Main function
main() {
    case "${1:-backup}" in
        backup)
            create_full_backup
            cleanup_old_backups
            ;;
        cleanup)
            cleanup_old_backups
            ;;
        list)
            list_backups
            ;;
        verify)
            verify_all_backups
            ;;
        env)
            backup_env
            ;;
        database|db)
            backup_database
            ;;
        code)
            backup_code
            ;;
        *)
            echo "Usage: $0 {backup|cleanup|list|verify|env|database|code}"
            echo ""
            echo "Commands:"
            echo "  backup    - Create full backup (code, database, .env)"
            echo "  cleanup   - Clean up old backups based on retention policy"
            echo "  list      - List all backups"
            echo "  verify    - Verify integrity of all backups"
            echo "  env       - Backup .env file only"
            echo "  database  - Backup database only"
            echo "  code      - Backup code only"
            exit 1
            ;;
    esac
}

# Execute main function
main "$@"

