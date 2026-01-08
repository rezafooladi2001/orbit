#!/bin/bash
# Ghidar Monitoring Script
# Usage: ./monitor_ghidar.sh

echo "=== Ghidar System Status ==="
date
echo ""

# Check PHP-FPM/Apache
echo "--- Web Server Status ---"
if systemctl is-active --quiet php8.2-fpm 2>/dev/null || systemctl is-active --quiet php-fpm 2>/dev/null; then
    echo "✓ PHP-FPM is running"
elif systemctl is-active --quiet apache2 2>/dev/null; then
    echo "✓ Apache is running"
elif systemctl is-active --quiet nginx 2>/dev/null; then
    echo "✓ Nginx is running"
else
    echo "⚠ Web server status unknown (may not be systemd-managed)"
fi
echo ""

# Check database connection
echo "--- Database Status ---"
if command -v mysql >/dev/null 2>&1; then
    # Try to get database name from .env
    DB_NAME=$(grep "^DB_DATABASE=" .env 2>/dev/null | cut -d'=' -f2 | tr -d '"' | tr -d "'" | xargs)
    if [ -n "$DB_NAME" ]; then
        if mysql -e "SELECT 1;" "$DB_NAME" >/dev/null 2>&1; then
            USER_COUNT=$(mysql -N -e "SELECT COUNT(*) FROM users;" "$DB_NAME" 2>/dev/null)
            if [ -n "$USER_COUNT" ]; then
                echo "✓ Database connected - Users: $USER_COUNT"
            else
                echo "✓ Database connected"
            fi
        else
            echo "✗ Database connection failed"
        fi
    else
        echo "⚠ Database name not found in .env"
    fi
else
    echo "⚠ MySQL client not available"
fi
echo ""

# Check disk space
echo "--- Disk Space ---"
df -h / | tail -1
echo ""

# Check logs
echo "--- Recent Log Activity ---"
if [ -f "/var/log/ghidar_cron.log" ]; then
    echo "Recent cron errors:"
    tail -5 /var/log/ghidar_cron.log | grep -i error || echo "No recent cron errors"
elif [ -f "RockyTap/storage/logs/ghidar.log" ]; then
    echo "Recent application errors:"
    tail -5 RockyTap/storage/logs/ghidar.log | grep -i error || echo "No recent application errors"
else
    echo "⚠ Log files not found"
fi
echo ""

# Check critical files
echo "--- Critical Files Check ---"
REQUIRED_FILES=(".env" "RockyTap/index.php" "RockyTap/api/health/index.php")
for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✓ Found: $file"
    else
        echo "✗ Missing: $file"
    fi
done
echo ""

# Check frontend build
if [ -d "RockyTap/assets/ghidar" ]; then
    BUILD_FILES=$(ls -1 RockyTap/assets/ghidar/*.{html,js,css} 2>/dev/null | wc -l)
    if [ "$BUILD_FILES" -gt 0 ]; then
        echo "✓ Frontend build exists ($BUILD_FILES files)"
    else
        echo "⚠ Frontend build directory exists but no files found"
    fi
else
    echo "⚠ Frontend build directory not found"
fi
echo ""

echo "=== Monitoring Complete ==="

