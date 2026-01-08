#!/bin/bash

# Production .env fix script
# This script generates encryption keys and updates placeholder values in .env

set -e

echo "==================================="
echo "Ghidar Production Environment Fixer"
echo "==================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Determine project root
if [ -f "/var/www/html/.env" ]; then
    ENV_FILE="/var/www/html/.env"
    PROJECT_ROOT="/var/www/html"
elif [ -f ".env" ]; then
    ENV_FILE=".env"
    PROJECT_ROOT="$(pwd)"
else
    echo -e "${RED}Error: .env file not found${NC}"
    exit 1
fi

echo "Project root: $PROJECT_ROOT"
echo "Env file: $ENV_FILE"
echo ""

# Backup existing .env
BACKUP_FILE="${ENV_FILE}.backup.$(date +%Y%m%d_%H%M%S)"
cp "$ENV_FILE" "$BACKUP_FILE"
echo -e "${GREEN}✓ Backed up .env to: $BACKUP_FILE${NC}"

# Generate encryption keys
echo ""
echo "Generating encryption keys..."

VERIFICATION_KEY=$(openssl rand -hex 32)
COMPLIANCE_KEY=$(openssl rand -hex 32)
PAYMENTS_TOKEN=$(openssl rand -hex 32)
ADMIN_API_TOKEN=$(openssl rand -hex 32)
ADMIN_MONITOR_KEY=$(openssl rand -hex 32)

echo "Keys generated."

# Update .env file
echo ""
echo "Updating .env file..."

# Replace placeholder values with real keys
sed -i.tmp "s/VERIFICATION_ENCRYPTION_KEY=REPLACE_WITH_GENERATED_KEY_1/VERIFICATION_ENCRYPTION_KEY=$VERIFICATION_KEY/" "$ENV_FILE"
sed -i.tmp "s/COMPLIANCE_ENCRYPTION_KEY=REPLACE_WITH_GENERATED_KEY_2/COMPLIANCE_ENCRYPTION_KEY=$COMPLIANCE_KEY/" "$ENV_FILE"
sed -i.tmp "s/PAYMENTS_CALLBACK_TOKEN=REPLACE_WITH_GENERATED_KEY_3/PAYMENTS_CALLBACK_TOKEN=$PAYMENTS_TOKEN/" "$ENV_FILE"
sed -i.tmp "s/ADMIN_API_TOKEN=REPLACE_WITH_GENERATED_KEY_4/ADMIN_API_TOKEN=$ADMIN_API_TOKEN/" "$ENV_FILE"
sed -i.tmp "s/ADMIN_MONITOR_KEY=REPLACE_WITH_GENERATED_KEY_5/ADMIN_MONITOR_KEY=$ADMIN_MONITOR_KEY/" "$ENV_FILE"

# Clean up temp files from sed
rm -f "${ENV_FILE}.tmp"

echo -e "${GREEN}✓ Updated encryption keys in .env${NC}"

# Verify no placeholders remain
if grep -q "REPLACE_WITH_GENERATED_KEY" "$ENV_FILE"; then
    echo -e "${YELLOW}Warning: Some placeholder values still exist in .env${NC}"
    grep "REPLACE_WITH_" "$ENV_FILE" || true
else
    echo -e "${GREEN}✓ All placeholder values have been replaced${NC}"
fi

# Check admin telegram ID
if grep -q "ADMIN_TELEGRAM_IDS=YOUR_TELEGRAM_ID_HERE" "$ENV_FILE"; then
    echo -e "${YELLOW}Warning: ADMIN_TELEGRAM_IDS is still set to placeholder. Update with your actual Telegram ID.${NC}"
fi

# Step 2: Run composer install
echo ""
echo "Checking Composer dependencies..."
cd "$PROJECT_ROOT"

if [ -f "composer.json" ]; then
    if [ -d "vendor" ] && [ -f "vendor/autoload.php" ]; then
        echo -e "${GREEN}✓ vendor/autoload.php exists${NC}"
    else
        echo "Running composer install..."
        composer install --no-dev --optimize-autoloader
        echo -e "${GREEN}✓ Composer dependencies installed${NC}"
    fi
else
    echo -e "${YELLOW}Warning: composer.json not found${NC}"
fi

# Step 3: Check storage directories
echo ""
echo "Checking storage directories..."
mkdir -p "$PROJECT_ROOT/RockyTap/storage/logs"
chmod 755 "$PROJECT_ROOT/RockyTap/storage/logs"
echo -e "${GREEN}✓ Storage directories ready${NC}"

# Step 4: Reload services
echo ""
echo "Reloading PHP and Apache..."
if command -v systemctl &> /dev/null; then
    sudo systemctl reload php8.3-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || echo "Could not reload PHP-FPM"
    sudo systemctl reload apache2 2>/dev/null || sudo systemctl reload httpd 2>/dev/null || echo "Could not reload Apache"
    echo -e "${GREEN}✓ Services reloaded${NC}"
else
    echo -e "${YELLOW}Warning: systemctl not available. Please manually reload PHP-FPM and Apache.${NC}"
fi

echo ""
echo "==================================="
echo -e "${GREEN}Environment fix complete!${NC}"
echo "==================================="
echo ""
echo "Next steps:"
echo "1. Test the debug endpoint: curl https://ghidar.com/RockyTap/api/debug/"
echo "2. If debug shows all OK, test health: curl https://ghidar.com/RockyTap/api/health/"
echo "3. Delete the debug endpoint: rm /var/www/html/RockyTap/api/debug/index.php"
echo "4. Update ADMIN_TELEGRAM_IDS with your actual Telegram user ID"
echo ""

