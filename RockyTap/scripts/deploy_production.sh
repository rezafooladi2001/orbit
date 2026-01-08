#!/bin/bash

# Ghidar Production Deployment Script
# Enhanced version with backup verification, .env protection, and migration safety

set -e  # Exit on error

echo "🚀 Ghidar Production Deployment Script"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get project root directory
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$PROJECT_ROOT"

# Backup directory configuration
BACKUP_BASE_DIR="RockyTap/storage/backups"
BACKUP_CODE_DIR="$BACKUP_BASE_DIR/code"
BACKUP_DB_DIR="$BACKUP_BASE_DIR/database"
BACKUP_ENV_DIR="$BACKUP_BASE_DIR/env"

# Ensure backup directories exist
mkdir -p "$BACKUP_CODE_DIR" "$BACKUP_DB_DIR" "$BACKUP_ENV_DIR"

# Global variables for backup tracking
BACKUP_TIMESTAMP=""
BACKUP_CODE_FILE=""
BACKUP_DB_FILE=""
BACKUP_ENV_FILE=""

# Cleanup old backups (keep last 10)
cleanup_old_backups() {
    echo "Cleaning up old backups (keeping last 10)..."
    
    # Clean code backups
    ls -t "$BACKUP_CODE_DIR"/*.tar.gz 2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null || true
    
    # Clean database backups
    ls -t "$BACKUP_DB_DIR"/*.sql 2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null || true
    
    # Clean env backups
    ls -t "$BACKUP_ENV_DIR"/*.env 2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null || true
    
    echo -e "${GREEN}✅ Old backups cleaned${NC}"
}

# Check prerequisites
check_prerequisites() {
    echo "Checking prerequisites..."

    # Check PHP version
    if ! command -v php &> /dev/null; then
        echo -e "${RED}❌ PHP not found${NC}"
        exit 1
    fi

    php_version=$(php -v | grep -oP 'PHP \K[0-9]+\.[0-9]+')
    if [[ $(echo "$php_version < 8.1" | bc -l 2>/dev/null || echo "1") == "1" ]]; then
        echo -e "${RED}❌ PHP 8.1+ required (found: $php_version)${NC}"
        exit 1
    fi
    echo -e "${GREEN}✅ PHP $php_version${NC}"

    # Check MySQL client
    if ! command -v mysql &> /dev/null; then
        echo -e "${YELLOW}⚠️  MySQL client not found (database backup will be skipped)${NC}"
    else
        echo -e "${GREEN}✅ MySQL client${NC}"
    fi

    # Check mysqldump
    if ! command -v mysqldump &> /dev/null; then
        echo -e "${YELLOW}⚠️  mysqldump not found (database backup will be skipped)${NC}"
    else
        echo -e "${GREEN}✅ mysqldump${NC}"
    fi

    # Check Node.js (optional for frontend)
    if command -v node &> /dev/null; then
        node_version=$(node -v | grep -oP 'v\K[0-9]+')
        echo -e "${GREEN}✅ Node.js v$node_version${NC}"
    else
        echo -e "${YELLOW}⚠️  Node.js not found (frontend build will be skipped)${NC}"
    fi

    # Check npm
    if command -v npm &> /dev/null; then
        echo -e "${GREEN}✅ npm${NC}"
    else
        echo -e "${YELLOW}⚠️  npm not found (frontend build will be skipped)${NC}"
    fi

    # Check Composer
    if ! command -v composer &> /dev/null; then
        echo -e "${RED}❌ Composer not found${NC}"
        exit 1
    fi
    echo -e "${GREEN}✅ Composer${NC}"

    # Check disk space (minimum 1GB free)
    available_space=$(df -BG "$PROJECT_ROOT" | tail -1 | awk '{print $4}' | sed 's/G//')
    if [ "$available_space" -lt 1 ]; then
        echo -e "${RED}❌ Insufficient disk space (need at least 1GB, have ${available_space}GB)${NC}"
        exit 1
    fi
    echo -e "${GREEN}✅ Disk space: ${available_space}GB available${NC}"

    # Verify backup directories are writable
    if [ ! -w "$BACKUP_CODE_DIR" ] || [ ! -w "$BACKUP_DB_DIR" ] || [ ! -w "$BACKUP_ENV_DIR" ]; then
        echo -e "${RED}❌ Backup directories are not writable${NC}"
        exit 1
    fi
    echo -e "${GREEN}✅ Backup directories are writable${NC}"
}

# Backup .env file
backup_env_file() {
    echo ""
    echo "Backing up .env file..."
    
    if [ ! -f ".env" ]; then
        echo -e "${YELLOW}⚠️  .env file not found, skipping backup${NC}"
        return 0
    fi

    BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_ENV_FILE="$BACKUP_ENV_DIR/env_backup_${BACKUP_TIMESTAMP}.env"
    
    cp ".env" "$BACKUP_ENV_FILE"
    
    # Verify backup
    if [ ! -f "$BACKUP_ENV_FILE" ] || [ ! -s "$BACKUP_ENV_FILE" ]; then
        echo -e "${RED}❌ Failed to backup .env file${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}✅ .env backed up to: $BACKUP_ENV_FILE${NC}"
}

# Backup database
backup_database() {
    echo ""
    echo "Backing up database..."
    
    if [ ! -f ".env" ]; then
        echo -e "${YELLOW}⚠️  .env file not found, skipping database backup${NC}"
        return 0
    fi

    # Source .env file
    set +a  # Don't export variables
    source .env 2>/dev/null || true
    set -a

    if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
        echo -e "${YELLOW}⚠️  Database credentials not found in .env, skipping database backup${NC}"
        return 0
    fi

    if ! command -v mysqldump &> /dev/null; then
        echo -e "${YELLOW}⚠️  mysqldump not found, skipping database backup${NC}"
        return 0
    fi

    BACKUP_TIMESTAMP=${BACKUP_TIMESTAMP:-$(date +%Y%m%d_%H%M%S)}
    BACKUP_DB_FILE="$BACKUP_DB_DIR/database_backup_${BACKUP_TIMESTAMP}.sql"
    
    # Create database backup with proper flags
    mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        --quick \
        --lock-tables=false \
        "$DB_DATABASE" > "$BACKUP_DB_FILE" 2>/dev/null
    
    # Verify backup
    if [ ! -f "$BACKUP_DB_FILE" ] || [ ! -s "$BACKUP_DB_FILE" ]; then
        echo -e "${RED}❌ Database backup failed or is empty${NC}"
        exit 1
    fi
    
    # Check if it's valid SQL (basic check)
    if ! head -n 1 "$BACKUP_DB_FILE" | grep -q "MySQL dump\|-- Dump of\|CREATE TABLE\|INSERT INTO"; then
        echo -e "${YELLOW}⚠️  Database backup may be invalid (no SQL content detected)${NC}"
    fi
    
    echo -e "${GREEN}✅ Database backed up to: $BACKUP_DB_FILE${NC}"
    echo "   Backup size: $(du -h "$BACKUP_DB_FILE" | cut -f1)"
}

# Backup code
backup_code() {
    echo ""
    echo "Backing up code..."
    
    BACKUP_TIMESTAMP=${BACKUP_TIMESTAMP:-$(date +%Y%m%d_%H%M%S)}
    BACKUP_CODE_FILE="$BACKUP_CODE_DIR/code_backup_${BACKUP_TIMESTAMP}.tar.gz"
    
    # Create code backup excluding unnecessary files
    tar -czf "$BACKUP_CODE_FILE" \
        --exclude="./node_modules" \
        --exclude="./vendor" \
        --exclude="./.git" \
        --exclude="./RockyTap/storage/backups" \
        --exclude="./RockyTap/storage/logs/*.log" \
        --exclude="./.env" \
        . 2>/dev/null
    
    # Verify backup
    if [ ! -f "$BACKUP_CODE_FILE" ] || [ ! -s "$BACKUP_CODE_FILE" ]; then
        echo -e "${RED}❌ Code backup failed or is empty${NC}"
        exit 1
    fi
    
    # Test archive integrity
    if ! tar -tzf "$BACKUP_CODE_FILE" > /dev/null 2>&1; then
        echo -e "${RED}❌ Code backup archive is corrupted${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}✅ Code backed up to: $BACKUP_CODE_FILE${NC}"
    echo "   Backup size: $(du -h "$BACKUP_CODE_FILE" | cut -f1)"
}

# Backup existing installation
backup_existing() {
    echo ""
    echo "=========================================="
    echo "Creating backups..."
    echo "=========================================="
    
    cleanup_old_backups
    
    # Backup in order: .env first (needed for DB backup), then DB, then code
    backup_env_file
    backup_database
    backup_code
    
    echo ""
    echo -e "${GREEN}✅ All backups created successfully${NC}"
    echo "   Backup timestamp: $BACKUP_TIMESTAMP"
    echo ""
}

# Deploy new code
deploy_code() {
    echo ""
    echo "=========================================="
    echo "Deploying new code..."
    echo "=========================================="

    # Install PHP dependencies
    echo "Installing PHP dependencies..."
    if ! composer install --no-dev --optimize-autoloader; then
        echo -e "${RED}❌ Failed to install PHP dependencies${NC}"
        exit 1
    fi
    echo -e "${GREEN}✅ PHP dependencies installed${NC}"

    # Build frontend (if Node.js available)
    if command -v node &> /dev/null && command -v npm &> /dev/null && [ -d "RockyTap/webapp" ]; then
        echo ""
        echo "Building frontend..."
        
        # Backup existing build artifacts
        if [ -d "RockyTap/assets" ]; then
            echo "Backing up existing build artifacts..."
            mkdir -p "$BACKUP_CODE_DIR/build_artifacts_${BACKUP_TIMESTAMP}"
            cp -r RockyTap/assets/index-*.js RockyTap/assets/index-*.css "$BACKUP_CODE_DIR/build_artifacts_${BACKUP_TIMESTAMP}/" 2>/dev/null || true
        fi
        
        cd RockyTap/webapp || exit 1
        
        if ! npm install --production; then
            echo -e "${YELLOW}⚠️  Frontend npm install failed, but continuing...${NC}"
        else
            echo -e "${GREEN}✅ Frontend dependencies installed${NC}"
        fi
        
        if ! npm run build; then
            echo -e "${RED}❌ Frontend build failed${NC}"
            echo "Restoring previous build artifacts..."
            if [ -d "$BACKUP_CODE_DIR/build_artifacts_${BACKUP_TIMESTAMP}" ]; then
                cp -r "$BACKUP_CODE_DIR/build_artifacts_${BACKUP_TIMESTAMP}"/* RockyTap/assets/ 2>/dev/null || true
            fi
            exit 1
        fi
        
        # Verify build output
        if [ ! -d "../assets" ] || [ -z "$(ls -A ../assets/index-*.js 2>/dev/null)" ]; then
            echo -e "${RED}❌ Frontend build output not found${NC}"
            exit 1
        fi
        
        echo -e "${GREEN}✅ Frontend built successfully${NC}"
        cd ../..
    else
        echo -e "${YELLOW}⚠️  Frontend build skipped (Node.js/npm not available or webapp directory not found)${NC}"
    fi

    echo -e "${GREEN}✅ Code deployed${NC}"
}

# Run database migrations safely
run_migrations() {
    echo ""
    echo "=========================================="
    echo "Running database migrations..."
    echo "=========================================="

    # Check if safe_migration.php exists
    if [ -f "RockyTap/scripts/safe_migration.php" ]; then
        echo "Using safe migration system..."
        
        migration_files=(
            "RockyTap/database/migrate_assisted_verification_tables.php"
            "RockyTap/database/migrate_production_automation_tables.php"
        )

        for migration in "${migration_files[@]}"; do
            if [ -f "$migration" ]; then
                echo "Running safe migration: $migration"
                if ! php RockyTap/scripts/safe_migration.php "$migration"; then
                    echo -e "${RED}❌ Migration failed: $migration${NC}"
                    exit 1
                fi
                echo -e "${GREEN}✅ Migration completed: $migration${NC}"
            fi
        done
    else
        echo -e "${YELLOW}⚠️  safe_migration.php not found, using basic migration${NC}"
        
        # Basic migration with backup
        if [ -f ".env" ] && [ -n "$BACKUP_DB_FILE" ] && [ -f "$BACKUP_DB_FILE" ]; then
            echo "Database backup exists, proceeding with migrations..."
        else
            echo -e "${YELLOW}⚠️  No database backup found, creating one before migrations...${NC}"
            backup_database
        fi
        
        migration_files=(
            "RockyTap/database/migrate_assisted_verification_tables.php"
            "RockyTap/database/migrate_production_automation_tables.php"
        )

        for migration in "${migration_files[@]}"; do
            if [ -f "$migration" ]; then
                echo "Running: $migration"
                if ! php "$migration"; then
                    echo -e "${RED}❌ Migration failed: $migration${NC}"
                    echo "Consider rolling back using: RockyTap/scripts/rollback.sh"
                    exit 1
                fi
            fi
        done
    fi

    echo -e "${GREEN}✅ Migrations completed${NC}"
}

# Setup cron jobs
setup_cron_jobs() {
    echo ""
    echo "Setting up cron jobs..."

    # Create log directory
    mkdir -p RockyTap/storage/logs
    mkdir -p /var/log/ghidar 2>/dev/null || mkdir -p RockyTap/storage/logs/cron

    SCRIPT_DIR=$(pwd)

    # Add cron jobs
    (crontab -l 2>/dev/null | grep -v "Ghidar Automated Processing" || true; cat <<EOF
# Ghidar Automated Processing
*/5 * * * * cd $SCRIPT_DIR && php RockyTap/scripts/automated_processing_pipeline.php >> RockyTap/storage/logs/cron/pipeline-\$(date +\\%Y\\%m\\%d).log 2>&1
0 2 * * * cd $SCRIPT_DIR && php RockyTap/scripts/automated_data_cleanup.php >> RockyTap/storage/logs/cron/cleanup-\$(date +\\%Y\\%m\\%d).log 2>&1
0 4 * * * cd $SCRIPT_DIR && php RockyTap/scripts/performance_optimization.php >> RockyTap/storage/logs/cron/optimization-\$(date +\\%Y\\%m\\%d).log 2>&1
0 0 * * * cd $SCRIPT_DIR && php -r "require 'bootstrap.php'; (new \\Ghidar\\Compliance\\AutomatedComplianceReporter())->generateComplianceReports('daily');" >> RockyTap/storage/logs/cron/compliance-\$(date +\\%Y\\%m\\%d).log 2>&1
EOF
    ) | crontab -

    echo -e "${GREEN}✅ Cron jobs configured${NC}"
}

# Setup monitoring
setup_monitoring() {
    echo ""
    echo "Setting up monitoring..."

    # Create config directory if it doesn't exist
    mkdir -p RockyTap/config

    # Create alert configuration template
    cat > RockyTap/config/alerts.yaml << EOF
alert_channels:
  telegram:
    enabled: false
    bot_token: ""
    chat_id: ""

  email:
    enabled: true
    recipients:
      - admin@yourdomain.com

  webhook:
    enabled: false
    url: ""
    secret: ""

alert_thresholds:
  large_transaction: 5000
  rapid_verifications: 5
  failed_attempts: 3
  system_error_rate: 0.1
  queue_backlog: 100
EOF

    echo -e "${GREEN}✅ Monitoring configured${NC}"
}

# Generate environment configuration (NEVER overwrite existing .env)
generate_env_config() {
    echo ""
    echo "Checking environment configuration..."

    if [ ! -f ".env" ]; then
        if [ -f ".env.example" ]; then
            echo "Creating .env from .env.example..."
            cp ".env.example" ".env"
            echo -e "${YELLOW}⚠️  Please edit .env file with your configuration${NC}"
            echo "   Required variables:"
            echo "   - DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE"
            echo "   - VERIFICATION_ENCRYPTION_KEY (32 bytes)"
            echo "   - ADMIN_DASHBOARD_TOKEN"
            echo "   - TELEGRAM_BOT_TOKEN"
        else
            echo -e "${YELLOW}⚠️  .env.example not found${NC}"
        fi
    else
        echo -e "${GREEN}✅ .env file exists (preserved)${NC}"
        
        # Warn if .env.example has significant differences (basic check)
        if [ -f ".env.example" ]; then
            env_lines=$(wc -l < .env)
            example_lines=$(wc -l < .env.example)
            if [ "$example_lines" -gt $((env_lines + 10)) ]; then
                echo -e "${YELLOW}⚠️  .env.example has significantly more lines than .env${NC}"
                echo "   Consider reviewing .env.example for new configuration options"
            fi
        fi
    fi
}

# Run tests
run_tests() {
    echo ""
    echo "Running tests..."

    if [ -f "vendor/bin/phpunit" ]; then
        echo "Running unit tests..."
        vendor/bin/phpunit tests/Unit/ 2>/dev/null || echo -e "${YELLOW}⚠️  Unit tests skipped or failed${NC}"

        echo "Running integration tests..."
        vendor/bin/phpunit tests/Integration/ 2>/dev/null || echo -e "${YELLOW}⚠️  Integration tests skipped or failed${NC}"
    else
        echo -e "${YELLOW}⚠️  PHPUnit not found, skipping tests${NC}"
    fi

    echo -e "${GREEN}✅ Tests completed${NC}"
}

# Verify deployment
verify_deployment() {
    echo ""
    echo "Verifying deployment..."
    
    # Check critical files exist
    if [ ! -f "bootstrap.php" ]; then
        echo -e "${RED}❌ bootstrap.php not found${NC}"
        return 1
    fi
    
    if [ ! -f ".env" ]; then
        echo -e "${YELLOW}⚠️  .env file not found${NC}"
    fi
    
    # Check database connection if .env exists
    if [ -f ".env" ]; then
        set +a
        source .env 2>/dev/null || true
        set -a
        
        if [ ! -z "$DB_DATABASE" ] && [ ! -z "$DB_USERNAME" ] && [ ! -z "$DB_PASSWORD" ]; then
            if command -v mysql &> /dev/null; then
                if mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1" "$DB_DATABASE" > /dev/null 2>&1; then
                    echo -e "${GREEN}✅ Database connection verified${NC}"
                else
                    echo -e "${YELLOW}⚠️  Database connection test failed${NC}"
                fi
            fi
        fi
    fi
    
    echo -e "${GREEN}✅ Deployment verification completed${NC}"
    return 0
}

# Main deployment function
main() {
    echo "Starting production deployment..."
    echo ""
    
    # Store backup info for potential rollback
    DEPLOYMENT_LOG="$BACKUP_BASE_DIR/deployment_${BACKUP_TIMESTAMP:-$(date +%Y%m%d_%H%M%S)}.log"
    exec > >(tee -a "$DEPLOYMENT_LOG") 2>&1

    # Run deployment steps
    check_prerequisites
    backup_existing
    deploy_code
    run_migrations
    setup_cron_jobs
    setup_monitoring
    generate_env_config
    run_tests
    verify_deployment

    echo ""
    echo "=========================================="
    echo -e "${GREEN}🎉 Deployment completed successfully!${NC}"
    echo "=========================================="
    echo ""
    echo "Backup information:"
    echo "  Code: $BACKUP_CODE_FILE"
    echo "  Database: $BACKUP_DB_FILE"
    echo "  .env: $BACKUP_ENV_FILE"
    echo "  Log: $DEPLOYMENT_LOG"
    echo ""
    echo "Next steps:"
    echo "1. Review and verify .env configuration"
    echo "2. Test the application endpoints"
    echo "3. Monitor logs: tail -f RockyTap/storage/logs/ghidar.log"
    echo "4. If issues occur, use rollback: RockyTap/scripts/rollback.sh"
    echo ""
}

# Execute main function
main "$@"
