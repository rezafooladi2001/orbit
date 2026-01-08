#!/bin/bash

# Rollback Script for Ghidar Deployment
# Restores code, database, and .env from backups

set -e

echo "🔄 Ghidar Rollback Script"
echo "========================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Get project root directory
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$PROJECT_ROOT"

# Backup directories
BACKUP_CODE_DIR="RockyTap/storage/backups/code"
BACKUP_DB_DIR="RockyTap/storage/backups/database"
BACKUP_ENV_DIR="RockyTap/storage/backups/env"

# List available backups
list_backups() {
    echo ""
    echo "=== Available Backups ==="
    echo ""
    
    echo -e "${BLUE}Code Backups:${NC}"
    if [ -d "$BACKUP_CODE_DIR" ] && [ "$(ls -A $BACKUP_CODE_DIR/*.tar.gz 2>/dev/null)" ]; then
        ls -lh "$BACKUP_CODE_DIR"/*.tar.gz 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}'
    else
        echo "  No code backups found"
    fi
    
    echo ""
    echo -e "${BLUE}Database Backups:${NC}"
    if [ -d "$BACKUP_DB_DIR" ] && [ "$(ls -A $BACKUP_DB_DIR/*.sql 2>/dev/null)" ]; then
        ls -lh "$BACKUP_DB_DIR"/*.sql 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}'
    else
        echo "  No database backups found"
    fi
    
    echo ""
    echo -e "${BLUE}.env Backups:${NC}"
    if [ -d "$BACKUP_ENV_DIR" ] && [ "$(ls -A $BACKUP_ENV_DIR/*.env 2>/dev/null)" ]; then
        ls -lh "$BACKUP_ENV_DIR"/*.env 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}'
    else
        echo "  No .env backups found"
    fi
    echo ""
}

# Extract timestamp from backup filename
extract_timestamp() {
    local filename="$1"
    echo "$filename" | grep -oP '\d{8}_\d{6}' | head -1
}

# Find backups by timestamp
find_backups_by_timestamp() {
    local timestamp="$1"
    
    local code_backup=$(ls -t "$BACKUP_CODE_DIR"/code_backup_${timestamp}*.tar.gz 2>/dev/null | head -1)
    local db_backup=$(ls -t "$BACKUP_DB_DIR"/database_backup_${timestamp}*.sql 2>/dev/null | head -1)
    local env_backup=$(ls -t "$BACKUP_ENV_DIR"/env_backup_${timestamp}*.env 2>/dev/null | head -1)
    
    echo "$code_backup|$db_backup|$env_backup"
}

# Restore code from backup
restore_code() {
    local backup_file="$1"
    
    if [ -z "$backup_file" ] || [ ! -f "$backup_file" ]; then
        echo -e "${RED}❌ Code backup file not found: $backup_file${NC}"
        return 1
    fi
    
    echo ""
    echo "Restoring code from: $backup_file"
    
    # Verify backup integrity
    if ! tar -tzf "$backup_file" > /dev/null 2>&1; then
        echo -e "${RED}❌ Backup archive is corrupted${NC}"
        return 1
    fi
    
    # Create temporary extraction directory
    TEMP_DIR=$(mktemp -d)
    trap "rm -rf $TEMP_DIR" EXIT
    
    # Extract backup
    echo "Extracting backup..."
    if ! tar -xzf "$backup_file" -C "$TEMP_DIR"; then
        echo -e "${RED}❌ Failed to extract backup${NC}"
        return 1
    fi
    
    # Backup current state before restore
    CURRENT_BACKUP="RockyTap/storage/backups/pre_rollback_$(date +%Y%m%d_%H%M%S).tar.gz"
    echo "Backing up current state to: $CURRENT_BACKUP"
    tar -czf "$CURRENT_BACKUP" \
        --exclude="./node_modules" \
        --exclude="./vendor" \
        --exclude="./.git" \
        --exclude="./RockyTap/storage/backups" \
        . 2>/dev/null || true
    
    # Restore files (excluding certain directories)
    echo "Restoring files..."
    rsync -av --delete \
        --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='.git' \
        --exclude='RockyTap/storage/backups' \
        --exclude='RockyTap/storage/logs/*.log' \
        "$TEMP_DIR/" "$PROJECT_ROOT/"
    
    echo -e "${GREEN}✅ Code restored${NC}"
    return 0
}

# Restore database from backup
restore_database() {
    local backup_file="$1"
    
    if [ -z "$backup_file" ] || [ ! -f "$backup_file" ]; then
        echo -e "${YELLOW}⚠️  Database backup file not found, skipping database restore${NC}"
        return 0
    fi
    
    echo ""
    echo "Restoring database from: $backup_file"
    
    if [ ! -f ".env" ]; then
        echo -e "${RED}❌ .env file not found, cannot restore database${NC}"
        return 1
    fi
    
    # Source .env file
    set +a
    source .env 2>/dev/null || true
    set -a
    
    if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
        echo -e "${RED}❌ Database credentials not found in .env${NC}"
        return 1
    fi
    
    if ! command -v mysql &> /dev/null; then
        echo -e "${RED}❌ MySQL client not found${NC}"
        return 1
    fi
    
    # Confirm database restore (destructive operation)
    echo -e "${YELLOW}⚠️  WARNING: This will overwrite the current database!${NC}"
    read -p "Are you sure you want to restore the database? (yes/no): " confirm
    if [ "$confirm" != "yes" ]; then
        echo "Database restore cancelled"
        return 0
    fi
    
    # Create backup of current database before restore
    CURRENT_DB_BACKUP="$BACKUP_DB_DIR/pre_rollback_$(date +%Y%m%d_%H%M%S).sql"
    echo "Backing up current database to: $CURRENT_DB_BACKUP"
    mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        "$DB_DATABASE" > "$CURRENT_DB_BACKUP" 2>/dev/null || echo "Current database backup failed"
    
    # Restore database
    echo "Restoring database..."
    if mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$backup_file" 2>/dev/null; then
        echo -e "${GREEN}✅ Database restored${NC}"
        return 0
    else
        echo -e "${RED}❌ Database restore failed${NC}"
        return 1
    fi
}

# Restore .env file
restore_env() {
    local backup_file="$1"
    
    if [ -z "$backup_file" ] || [ ! -f "$backup_file" ]; then
        echo -e "${YELLOW}⚠️  .env backup file not found, skipping .env restore${NC}"
        return 0
    fi
    
    echo ""
    echo "Restoring .env from: $backup_file"
    
    # Backup current .env if it exists
    if [ -f ".env" ]; then
        CURRENT_ENV_BACKUP="$BACKUP_ENV_DIR/pre_rollback_$(date +%Y%m%d_%H%M%S).env"
        cp ".env" "$CURRENT_ENV_BACKUP"
        echo "Current .env backed up to: $CURRENT_ENV_BACKUP"
    fi
    
    # Restore .env
    cp "$backup_file" ".env"
    echo -e "${GREEN}✅ .env restored${NC}"
    return 0
}

# Verify restoration
verify_restoration() {
    echo ""
    echo "=== Verifying Restoration ==="
    
    # Check critical files
    if [ -f "bootstrap.php" ]; then
        echo -e "${GREEN}✅ bootstrap.php exists${NC}"
    else
        echo -e "${RED}❌ bootstrap.php not found${NC}"
        return 1
    fi
    
    if [ -f ".env" ]; then
        echo -e "${GREEN}✅ .env file exists${NC}"
    else
        echo -e "${YELLOW}⚠️  .env file not found${NC}"
    fi
    
    # Test database connection if .env exists
    if [ -f ".env" ]; then
        set +a
        source .env 2>/dev/null || true
        set -a
        
        if [ ! -z "$DB_DATABASE" ] && [ ! -z "$DB_USERNAME" ] && [ ! -z "$DB_PASSWORD" ]; then
            if command -v mysql &> /dev/null; then
                if mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1" "$DB_DATABASE" > /dev/null 2>&1; then
                    echo -e "${GREEN}✅ Database connection successful${NC}"
                else
                    echo -e "${YELLOW}⚠️  Database connection test failed${NC}"
                fi
            fi
        fi
    fi
    
    echo -e "${GREEN}✅ Restoration verification completed${NC}"
    return 0
}

# Interactive rollback
interactive_rollback() {
    list_backups
    
    echo "Select rollback method:"
    echo "1) Rollback by timestamp (restore all from same deployment)"
    echo "2) Rollback code only"
    echo "3) Rollback database only"
    echo "4) Rollback .env only"
    echo "5) Cancel"
    echo ""
    read -p "Enter choice (1-5): " choice
    
    case $choice in
        1)
            echo ""
            read -p "Enter backup timestamp (YYYYMMDD_HHMMSS): " timestamp
            if [ -z "$timestamp" ]; then
                echo -e "${RED}❌ Invalid timestamp${NC}"
                exit 1
            fi
            
            backups=$(find_backups_by_timestamp "$timestamp")
            code_backup=$(echo "$backups" | cut -d'|' -f1)
            db_backup=$(echo "$backups" | cut -d'|' -f2)
            env_backup=$(echo "$backups" | cut -d'|' -f3)
            
            if [ -z "$code_backup" ] && [ -z "$db_backup" ] && [ -z "$env_backup" ]; then
                echo -e "${RED}❌ No backups found for timestamp: $timestamp${NC}"
                exit 1
            fi
            
            restore_code "$code_backup"
            restore_database "$db_backup"
            restore_env "$env_backup"
            ;;
        2)
            echo ""
            read -p "Enter code backup file path: " backup_file
            restore_code "$backup_file"
            ;;
        3)
            echo ""
            read -p "Enter database backup file path: " backup_file
            restore_database "$backup_file"
            ;;
        4)
            echo ""
            read -p "Enter .env backup file path: " backup_file
            restore_env "$backup_file"
            ;;
        5)
            echo "Rollback cancelled"
            exit 0
            ;;
        *)
            echo -e "${RED}❌ Invalid choice${NC}"
            exit 1
            ;;
    esac
}

# Rollback from latest backup
rollback_latest() {
    echo "Rolling back to latest backup..."
    
    # Find latest backups
    code_backup=$(ls -t "$BACKUP_CODE_DIR"/*.tar.gz 2>/dev/null | head -1)
    db_backup=$(ls -t "$BACKUP_DB_DIR"/*.sql 2>/dev/null | head -1)
    env_backup=$(ls -t "$BACKUP_ENV_DIR"/*.env 2>/dev/null | head -1)
    
    if [ -z "$code_backup" ] && [ -z "$db_backup" ] && [ -z "$env_backup" ]; then
        echo -e "${RED}❌ No backups found${NC}"
        exit 1
    fi
    
    restore_code "$code_backup"
    restore_database "$db_backup"
    restore_env "$env_backup"
}

# Main function
main() {
    if [ "$1" = "--list" ] || [ "$1" = "-l" ]; then
        list_backups
        exit 0
    fi
    
    if [ "$1" = "--latest" ] || [ "$1" = "-L" ]; then
        rollback_latest
        verify_restoration
        echo ""
        echo -e "${GREEN}🎉 Rollback completed!${NC}"
        exit 0
    fi
    
    if [ "$1" = "--timestamp" ] || [ "$1" = "-t" ]; then
        if [ -z "$2" ]; then
            echo -e "${RED}❌ Timestamp required${NC}"
            echo "Usage: $0 --timestamp YYYYMMDD_HHMMSS"
            exit 1
        fi
        
        backups=$(find_backups_by_timestamp "$2")
        code_backup=$(echo "$backups" | cut -d'|' -f1)
        db_backup=$(echo "$backups" | cut -d'|' -f2)
        env_backup=$(echo "$backups" | cut -d'|' -f3)
        
        restore_code "$code_backup"
        restore_database "$db_backup"
        restore_env "$env_backup"
        verify_restoration
        echo ""
        echo -e "${GREEN}🎉 Rollback completed!${NC}"
        exit 0
    fi
    
    # Interactive mode
    interactive_rollback
    verify_restoration
    
    echo ""
    echo -e "${GREEN}🎉 Rollback completed!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Verify the application is working correctly"
    echo "2. Check logs: tail -f RockyTap/storage/logs/ghidar.log"
    echo "3. Test critical functionality"
    echo ""
}

# Execute main function
main "$@"

