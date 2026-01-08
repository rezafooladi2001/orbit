#!/bin/bash
#
# Ghidar Frontend Rebuild Script
# Run this script to rebuild and deploy the React frontend
#
# Usage: ./rebuild_frontend.sh
#

set -e  # Exit on error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEBAPP_DIR="$SCRIPT_DIR/../webapp"
ASSETS_DIR="$SCRIPT_DIR/../assets/ghidar"

echo "==================================="
echo "Ghidar Frontend Rebuild Script"
echo "==================================="
echo ""

# Check if we're in the right directory
if [ ! -d "$WEBAPP_DIR" ]; then
    echo "ERROR: webapp directory not found at $WEBAPP_DIR"
    exit 1
fi

cd "$WEBAPP_DIR"

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "Installing dependencies..."
    npm install
else
    echo "Dependencies already installed."
fi

# Build the frontend
echo ""
echo "Building frontend..."
npm run build

# Check if build was successful
if [ ! -d "dist" ]; then
    echo "ERROR: Build failed - dist directory not created"
    exit 1
fi

# Copy built assets to the ghidar assets directory
echo ""
echo "Deploying to $ASSETS_DIR..."

# Backup existing assets
if [ -d "$ASSETS_DIR" ]; then
    BACKUP_DIR="$SCRIPT_DIR/../assets/ghidar_backup_$(date +%Y%m%d_%H%M%S)"
    echo "Backing up existing assets to $BACKUP_DIR"
    cp -r "$ASSETS_DIR" "$BACKUP_DIR"
fi

# Create assets directory if it doesn't exist
mkdir -p "$ASSETS_DIR"

# Copy new assets
cp -r dist/assets/* "$ASSETS_DIR/" 2>/dev/null || true
cp dist/index.js "$ASSETS_DIR/" 2>/dev/null || true
cp dist/index.css "$ASSETS_DIR/" 2>/dev/null || true

# If Vite output has different names, copy those too
find dist -name "*.js" -exec cp {} "$ASSETS_DIR/" \; 2>/dev/null || true
find dist -name "*.css" -exec cp {} "$ASSETS_DIR/" \; 2>/dev/null || true

echo ""
echo "==================================="
echo "Build complete!"
echo "==================================="
echo ""
echo "Next steps:"
echo "1. Clear any server-side caches"
echo "2. Test the Mini App through Telegram"
echo ""
echo "If you're still seeing issues, check:"
echo "- Browser developer console for errors"
echo "- Server logs at /RockyTap/storage/logs/"
echo "- Nginx/Apache error logs"
echo ""

