#!/bin/bash

# Setup script for Assisted Verification cron jobs
# This script adds cron jobs for processing assisted verifications

echo "Setting up Assisted Verification cron jobs..."

# Get the absolute path to the project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PROCESS_SCRIPT="$PROJECT_ROOT/scripts/process_assisted_verifications.php"
CLEANUP_SCRIPT="$PROJECT_ROOT/scripts/cleanup_old_verifications.php"

# Create log directory if it doesn't exist
LOG_DIR="/var/log/ghidar/assisted-verification"
mkdir -p "$LOG_DIR"

# Check if scripts exist
if [ ! -f "$PROCESS_SCRIPT" ]; then
    echo "Error: Process script not found at $PROCESS_SCRIPT"
    exit 1
fi

# Get current crontab
CURRENT_CRONTAB=$(crontab -l 2>/dev/null || echo "")

# Check if cron jobs already exist
if echo "$CURRENT_CRONTAB" | grep -q "process_assisted_verifications.php"; then
    echo "Warning: Assisted verification cron jobs already exist."
    echo "Do you want to replace them? (y/n)"
    read -r response
    if [ "$response" != "y" ]; then
        echo "Aborted."
        exit 0
    fi
    # Remove existing entries
    CURRENT_CRONTAB=$(echo "$CURRENT_CRONTAB" | grep -v "process_assisted_verifications.php" | grep -v "cleanup_old_verifications.php")
fi

# Add new cron jobs
{
    echo "$CURRENT_CRONTAB"
    echo ""
    echo "# Assisted Verification Processing"
    echo "# Process pending verifications every 5 minutes"
    echo "*/5 * * * * php $PROCESS_SCRIPT >> $LOG_DIR/process-\$(date +\\%Y\\%m\\%d).log 2>&1"
    echo ""
    echo "# Assisted Verification Cleanup"
    echo "# Cleanup old verifications daily at 3 AM"
    if [ -f "$CLEANUP_SCRIPT" ]; then
        echo "0 3 * * * php $CLEANUP_SCRIPT >> $LOG_DIR/cleanup-\$(date +\\%Y\\%m\\%d).log 2>&1"
    else
        echo "# Cleanup script not found at $CLEANUP_SCRIPT"
    fi
} | crontab -

echo ""
echo "✓ Cron jobs added successfully:"
echo "  1. Process pending verifications every 5 minutes"
if [ -f "$CLEANUP_SCRIPT" ]; then
    echo "  2. Cleanup old verifications daily at 3 AM"
fi
echo ""
echo "Logs will be written to: $LOG_DIR/"
echo ""
echo "To view current crontab: crontab -l"
echo "To remove cron jobs: crontab -e (then delete the lines)"

