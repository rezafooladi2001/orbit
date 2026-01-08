#!/bin/bash

# Pre-Deployment Safety Checks
# Verifies all prerequisites before deployment

set -e

echo "🔍 Pre-Deployment Safety Checks"
echo "==============================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Get project root directory
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$PROJECT_ROOT"

ERRORS=0
WARNINGS=0

# Check function
check() {
    local name="$1"
    local command="$2"
    local required="${3:-false}"
    
    echo -n "Checking $name... "
    
    if eval "$command" > /dev/null 2>&1; then
        echo -e "${GREEN}✅${NC}"
        return 0
    else
        if [ "$required" = "true" ]; then
            echo -e "${RED}❌ REQUIRED${NC}"
            ((ERRORS++))
            return 1
        else
            echo -e "${YELLOW}⚠️  WARNING${NC}"
            ((WARNINGS++))
            return 0
        fi
    fi
}

# Check Git repository status
check_git_status() {
    echo ""
    echo "=== Git Repository Status ==="
    
    if [ ! -d ".git" ]; then
        echo -e "${YELLOW}⚠️  Git repository not initialized${NC}"
        echo "   Run: scripts/setup_git.sh to initialize"
        ((WARNINGS++))
        return 0
    fi
    
    # Check for uncommitted changes
    if [ -n "$(git status --porcelain)" ]; then
        echo -e "${YELLOW}⚠️  Uncommitted changes detected${NC}"
        echo "   Uncommitted files:"
        git status --short | head -5
        if [ "$(git status --short | wc -l)" -gt 5 ]; then
            echo "   ... and more"
        fi
        echo ""
        read -p "Continue with uncommitted changes? (y/n) " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo -e "${RED}❌ Deployment aborted by user${NC}"
            exit 1
        fi
        ((WARNINGS++))
    else
        echo -e "${GREEN}✅ Git repository is clean${NC}"
    fi
    
    # Check current branch
    current_branch=$(git branch --show-current)
    echo "   Current branch: $current_branch"
    
    if [ "$current_branch" != "main" ] && [ "$current_branch" != "master" ]; then
        echo -e "${YELLOW}⚠️  Not on main/master branch${NC}"
        ((WARNINGS++))
    fi
    
    # Check if remote is configured
    if git remote | grep -q "origin"; then
        echo -e "${GREEN}✅ Remote repository configured${NC}"
    else
        echo -e "${YELLOW}⚠️  No remote repository configured${NC}"
        ((WARNINGS++))
    fi
}

# Check environment variables
check_environment() {
    echo ""
    echo "=== Environment Configuration ==="
    
    if [ ! -f ".env" ]; then
        echo -e "${RED}❌ .env file not found${NC}"
        echo "   Create .env from .env.example"
        ((ERRORS++))
        return 1
    fi
    
    echo -e "${GREEN}✅ .env file exists${NC}"
    
    # Source .env and check required variables
    set +a
    source .env 2>/dev/null || true
    set -a
    
    local required_vars=(
        "DB_HOST"
        "DB_DATABASE"
        "DB_USERNAME"
        "DB_PASSWORD"
        "TELEGRAM_BOT_TOKEN"
    )
    
    local missing_vars=()
    for var in "${required_vars[@]}"; do
        if [ -z "${!var}" ]; then
            missing_vars+=("$var")
        fi
    done
    
    if [ ${#missing_vars[@]} -gt 0 ]; then
        echo -e "${RED}❌ Missing required environment variables:${NC}"
        for var in "${missing_vars[@]}"; do
            echo "   - $var"
        done
        ((ERRORS++))
    else
        echo -e "${GREEN}✅ Required environment variables set${NC}"
    fi
}

# Check database connection
check_database() {
    echo ""
    echo "=== Database Connection ==="
    
    if [ ! -f ".env" ]; then
        echo -e "${YELLOW}⚠️  Cannot check database (no .env file)${NC}"
        ((WARNINGS++))
        return 0
    fi
    
    set +a
    source .env 2>/dev/null || true
    set -a
    
    if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
        echo -e "${YELLOW}⚠️  Database credentials not configured${NC}"
        ((WARNINGS++))
        return 0
    fi
    
    if ! command -v mysql &> /dev/null; then
        echo -e "${YELLOW}⚠️  MySQL client not found, cannot test connection${NC}"
        ((WARNINGS++))
        return 0
    fi
    
    if mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -h "${DB_HOST:-localhost}" -e "SELECT 1" "$DB_DATABASE" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Database connection successful${NC}"
    else
        echo -e "${RED}❌ Database connection failed${NC}"
        ((ERRORS++))
    fi
}

# Check disk space
check_disk_space() {
    echo ""
    echo "=== Disk Space ==="
    
    available_space=$(df -BG "$PROJECT_ROOT" | tail -1 | awk '{print $4}' | sed 's/G//')
    required_space=1
    
    if [ "$available_space" -ge "$required_space" ]; then
        echo -e "${GREEN}✅ Disk space: ${available_space}GB available (required: ${required_space}GB)${NC}"
    else
        echo -e "${RED}❌ Insufficient disk space: ${available_space}GB available (required: ${required_space}GB)${NC}"
        ((ERRORS++))
    fi
}

# Check prerequisites
check_prerequisites() {
    echo ""
    echo "=== Prerequisites ==="
    
    # PHP
    if command -v php &> /dev/null; then
        php_version=$(php -v | grep -oP 'PHP \K[0-9]+\.[0-9]+')
        if [[ $(echo "$php_version >= 8.1" | bc -l 2>/dev/null || echo "0") == "1" ]]; then
            echo -e "${GREEN}✅ PHP $php_version${NC}"
        else
            echo -e "${RED}❌ PHP version too old: $php_version (required: 8.1+)${NC}"
            ((ERRORS++))
        fi
    else
        echo -e "${RED}❌ PHP not found${NC}"
        ((ERRORS++))
    fi
    
    # Composer
    check "Composer" "command -v composer" true
    
    # MySQL client (optional but recommended)
    check "MySQL client" "command -v mysql" false
    
    # mysqldump (optional but recommended)
    check "mysqldump" "command -v mysqldump" false
    
    # Node.js (optional for frontend)
    if command -v node &> /dev/null; then
        node_version=$(node -v)
        echo -e "${GREEN}✅ Node.js $node_version${NC}"
    else
        echo -e "${YELLOW}⚠️  Node.js not found (frontend build will be skipped)${NC}"
        ((WARNINGS++))
    fi
    
    # npm (optional for frontend)
    check "npm" "command -v npm" false
}

# Check backup directories
check_backup_directories() {
    echo ""
    echo "=== Backup Directories ==="
    
    backup_dirs=(
        "RockyTap/storage/backups"
        "RockyTap/storage/backups/code"
        "RockyTap/storage/backups/database"
        "RockyTap/storage/backups/env"
    )
    
    for dir in "${backup_dirs[@]}"; do
        if [ ! -d "$dir" ]; then
            echo "Creating directory: $dir"
            mkdir -p "$dir"
        fi
        
        if [ -w "$dir" ]; then
            echo -e "${GREEN}✅ $dir is writable${NC}"
        else
            echo -e "${RED}❌ $dir is not writable${NC}"
            ((ERRORS++))
        fi
    done
}

# Check file permissions
check_file_permissions() {
    echo ""
    echo "=== File Permissions ==="
    
    critical_files=(
        "bootstrap.php"
        "composer.json"
        "RockyTap/scripts/deploy_production.sh"
    )
    
    for file in "${critical_files[@]}"; do
        if [ -f "$file" ]; then
            if [ -r "$file" ]; then
                echo -e "${GREEN}✅ $file is readable${NC}"
            else
                echo -e "${RED}❌ $file is not readable${NC}"
                ((ERRORS++))
            fi
        else
            echo -e "${RED}❌ $file not found${NC}"
            ((ERRORS++))
        fi
    done
}

# Check Composer dependencies
check_composer_dependencies() {
    echo ""
    echo "=== Composer Dependencies ==="
    
    if [ ! -f "composer.json" ]; then
        echo -e "${YELLOW}⚠️  composer.json not found${NC}"
        ((WARNINGS++))
        return 0
    fi
    
    if [ -f "composer.lock" ]; then
        echo -e "${GREEN}✅ composer.lock exists${NC}"
        
        # Check if vendor directory exists and is up to date
        if [ -d "vendor" ]; then
            echo -e "${GREEN}✅ vendor directory exists${NC}"
        else
            echo -e "${YELLOW}⚠️  vendor directory not found (will be created during deployment)${NC}"
            ((WARNINGS++))
        fi
    else
        echo -e "${YELLOW}⚠️  composer.lock not found${NC}"
        ((WARNINGS++))
    fi
}

# Main function
main() {
    check_git_status
    check_environment
    check_database
    check_disk_space
    check_prerequisites
    check_backup_directories
    check_file_permissions
    check_composer_dependencies
    
    echo ""
    echo "=========================================="
    echo "Check Summary"
    echo "=========================================="
    echo -e "Errors: ${RED}$ERRORS${NC}"
    echo -e "Warnings: ${YELLOW}$WARNINGS${NC}"
    echo ""
    
    if [ $ERRORS -gt 0 ]; then
        echo -e "${RED}❌ Pre-deployment checks FAILED${NC}"
        echo "Please fix the errors above before deploying."
        exit 1
    elif [ $WARNINGS -gt 0 ]; then
        echo -e "${YELLOW}⚠️  Pre-deployment checks completed with warnings${NC}"
        echo "Deployment can proceed, but please review warnings."
        exit 0
    else
        echo -e "${GREEN}✅ All pre-deployment checks passed${NC}"
        echo "Safe to proceed with deployment."
        exit 0
    fi
}

# Execute main function
main "$@"

