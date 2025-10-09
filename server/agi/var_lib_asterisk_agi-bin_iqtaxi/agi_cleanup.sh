#!/bin/bash
# AGI Call System - Automatic Cleanup Script
# Removes ALL old files and folders to maintain disk space
# Location: /var/lib/asterisk/agi-bin/iqtaxi/agi_cleanup.sh

# ===== CONFIGURATION =====
DAYS_OLD=7                       # Delete everything older than 7 days
CLEANUP_DIR="/var/auto_register_call"  # Directory to clean
LOG_FILE="/var/log/auto_register_call/cleanup.log"

# ===== MAIN CLEANUP PROCESS =====

# Create log directory if it doesn't exist
mkdir -p /var/log/auto_register_call

# Log start
echo "[$(date '+%Y-%m-%d %H:%M:%S')] ========== Starting Cleanup ==========" >> "$LOG_FILE"

# Check if cleanup directory exists
if [ ! -d "$CLEANUP_DIR" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: Directory $CLEANUP_DIR does not exist" >> "$LOG_FILE"
    exit 1
fi

# Count items before cleanup (ALL items, not just old ones)
BEFORE_COUNT=$(find "$CLEANUP_DIR" -mindepth 1 2>/dev/null | wc -l)
OLD_ITEMS_COUNT=$(find "$CLEANUP_DIR" -mindepth 1 -mtime +$DAYS_OLD 2>/dev/null | wc -l)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Total items before cleanup: $BEFORE_COUNT" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Items older than $DAYS_OLD days: $OLD_ITEMS_COUNT" >> "$LOG_FILE"

# Check disk space before cleanup
DISK_BEFORE=$(df -h "$CLEANUP_DIR" | awk 'NR==2 {print $4}')
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Free space before cleanup: $DISK_BEFORE" >> "$LOG_FILE"

# Delete all FILES older than 7 days
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deleting files older than $DAYS_OLD days..." >> "$LOG_FILE"
find "$CLEANUP_DIR" -type f -mtime +$DAYS_OLD -exec rm -f {} \; 2>/dev/null

# Delete all empty DIRECTORIES older than 7 days
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deleting empty directories older than $DAYS_OLD days..." >> "$LOG_FILE"
find "$CLEANUP_DIR" -type d -empty -mtime +$DAYS_OLD -exec rmdir {} \; 2>/dev/null

# For non-empty directories older than 7 days, delete them and their contents
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deleting directories and contents older than $DAYS_OLD days..." >> "$LOG_FILE"
find "$CLEANUP_DIR" -mindepth 1 -type d -mtime +$DAYS_OLD -exec rm -rf {} \; 2>/dev/null

# Count items after cleanup (ALL items)
AFTER_COUNT=$(find "$CLEANUP_DIR" -mindepth 1 2>/dev/null | wc -l)
DELETED=$((BEFORE_COUNT - AFTER_COUNT))

# Check disk space after cleanup
DISK_AFTER=$(df -h "$CLEANUP_DIR" | awk 'NR==2 {print $4}')

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Total items after cleanup: $AFTER_COUNT" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cleanup complete. Deleted $DELETED items" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Free space after cleanup: $DISK_AFTER" >> "$LOG_FILE"

# Warning if disk space is critically low
DISK_PERCENT=$(df "$CLEANUP_DIR" | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_PERCENT" -gt 90 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️  WARNING: Disk usage is at ${DISK_PERCENT}% - critically low!" >> "$LOG_FILE"
fi

# Keep log file from growing too large (keep last 1000 lines)
if [ $(wc -l < "$LOG_FILE") -gt 1000 ]; then
    tail -n 1000 "$LOG_FILE" > "$LOG_FILE.tmp" && mv "$LOG_FILE.tmp" "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Log file trimmed to 1000 lines" >> "$LOG_FILE"
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] ========== Cleanup Finished ==========" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

exit 0