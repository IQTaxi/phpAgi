#!/bin/bash

# Asterisk AGI Setup Script
# This script sets up proper permissions and directories for the AGI system

echo "================================================"
echo "       ASTERISK AGI SETUP & PERMISSIONS"
echo "================================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "ERROR: This script must be run as root (use sudo)"
    echo "Usage: sudo bash setup_asterisk_permissions.sh"
    exit 1
fi

echo "Setting up Asterisk AGI system..."
echo ""

# 1. Convert line endings for all PHP files in AGI directory
echo "1. Converting line endings (dos2unix)..."
if command -v dos2unix >/dev/null 2>&1; then
    find /var/lib/asterisk/agi-bin -name "*.php" -exec dos2unix {} \;
    echo "   ✓ Line endings converted"
else
    echo "   Installing dos2unix..."
    if command -v apt-get >/dev/null 2>&1; then
        apt-get update && apt-get install -y dos2unix
    elif command -v yum >/dev/null 2>&1; then
        yum install -y dos2unix
    elif command -v dnf >/dev/null 2>&1; then
        dnf install -y dos2unix
    else
        echo "   ⚠ Could not install dos2unix automatically"
        echo "   Please install it manually: apt-get install dos2unix"
    fi
    
    if command -v dos2unix >/dev/null 2>&1; then
        find /var/lib/asterisk/agi-bin -name "*.php" -exec dos2unix {} \;
        echo "   ✓ Line endings converted"
    fi
fi
echo ""

# 2. Create necessary directories
echo "2. Creating required directories..."

# Create auto_register_call directory
mkdir -p /var/auto_register_call
echo "   ✓ Created /var/auto_register_call"

# Create sounds directories
mkdir -p /var/sounds/iqtaxi
mkdir -p /var/sounds/taxiway
echo "   ✓ Created /var/sounds/iqtaxi"
echo "   ✓ Created /var/sounds/taxiway"

# Create log directory
mkdir -p /var/log/auto_register_call
echo "   ✓ Created /var/log/auto_register_call"

echo ""

# 3. Set proper ownership
echo "3. Setting proper ownership..."

# Find asterisk user (common names)
ASTERISK_USER=""
for user in asterisk ast pbx freepbx; do
    if id "$user" &>/dev/null; then
        ASTERISK_USER="$user"
        break
    fi
done

if [ -z "$ASTERISK_USER" ]; then
    echo "   ⚠ Asterisk user not found, using 'asterisk'"
    ASTERISK_USER="asterisk"
    # Create asterisk user if it doesn't exist
    if ! id "$ASTERISK_USER" &>/dev/null; then
        useradd -r -s /bin/false asterisk
        echo "   ✓ Created asterisk user"
    fi
fi

echo "   Using Asterisk user: $ASTERISK_USER"

# Set ownership
chown -R $ASTERISK_USER:$ASTERISK_USER /var/lib/asterisk/agi-bin/
chown -R $ASTERISK_USER:$ASTERISK_USER /var/auto_register_call/
chown -R $ASTERISK_USER:$ASTERISK_USER /var/sounds/
chown -R $ASTERISK_USER:$ASTERISK_USER /var/log/auto_register_call/

# Ensure /var/sounds is fully accessible to asterisk
find /var/sounds -type d -exec chown $ASTERISK_USER:$ASTERISK_USER {} \;
find /var/sounds -type f -exec chown $ASTERISK_USER:$ASTERISK_USER {} \;

echo "   ✓ Set ownership to $ASTERISK_USER"
echo ""

# 4. Set proper permissions
echo "4. Setting proper permissions..."

# AGI scripts - executable
find /var/lib/asterisk/agi-bin -name "*.php" -exec chmod 755 {} \;
echo "   ✓ AGI scripts: 755 (executable)"

# Auto register directory - full access for asterisk
chmod -R 755 /var/auto_register_call/
echo "   ✓ Auto register directory: 755"

# Sounds directory - readable and accessible
chmod -R 755 /var/sounds/
find /var/sounds -type d -exec chmod 755 {} \;
find /var/sounds -type f -exec chmod 644 {} \;
echo "   ✓ Sounds directory: 755 (dirs) / 644 (files)"

# Log directory - writable
chmod -R 755 /var/log/auto_register_call/
echo "   ✓ Log directory: 755"

echo ""

# 5. Set SELinux context if SELinux is enabled
echo "5. Checking SELinux..."
if command -v getenforce >/dev/null 2>&1 && [ "$(getenforce)" != "Disabled" ]; then
    echo "   SELinux is enabled, setting contexts..."
    
    # Set proper SELinux contexts
    if command -v setsebool >/dev/null 2>&1; then
        setsebool -P asterisk_use_nfs on 2>/dev/null || true
        setsebool -P asterisk_read_user_content on 2>/dev/null || true
    fi
    
    if command -v chcon >/dev/null 2>&1; then
        chcon -R -t asterisk_exec_t /var/lib/asterisk/agi-bin/ 2>/dev/null || true
        chcon -R -t var_t /var/auto_register_call/ 2>/dev/null || true
        chcon -R -t asterisk_var_lib_t /var/sounds/ 2>/dev/null || true
    fi
    
    echo "   ✓ SELinux contexts set"
else
    echo "   ✓ SELinux not enabled or not found"
fi
echo ""

# 6. Test directory creation and permissions
echo "6. Testing directory permissions..."

# Test auto_register_call directory
sudo -u $ASTERISK_USER mkdir -p /var/auto_register_call/test_dir 2>/dev/null
if [ -d "/var/auto_register_call/test_dir" ]; then
    rm -rf /var/auto_register_call/test_dir
    echo "   ✓ Directory creation test: PASSED"
else
    echo "   ⚠ Directory creation test: FAILED"
    echo "   Manual fix needed: Check /var/auto_register_call permissions"
fi

# Test log directory
sudo -u $ASTERISK_USER touch /var/log/auto_register_call/test_log.txt 2>/dev/null
if [ -f "/var/log/auto_register_call/test_log.txt" ]; then
    rm -f /var/log/auto_register_call/test_log.txt
    echo "   ✓ Log file creation test: PASSED"
else
    echo "   ⚠ Log file creation test: FAILED"
    echo "   Manual fix needed: Check /var/log/auto_register_call permissions"
fi

# Test sounds directory access
if sudo -u $ASTERISK_USER test -r /var/sounds && sudo -u $ASTERISK_USER test -x /var/sounds; then
    echo "   ✓ Sounds directory access test: PASSED"
else
    echo "   ⚠ Sounds directory access test: FAILED"
    echo "   Manual fix needed: Check /var/sounds permissions"
fi

# Test if asterisk can read sound files
SOUND_FILES_COUNT=$(find /var/sounds -name "*.mp3" -o -name "*.wav" | wc -l)
if [ "$SOUND_FILES_COUNT" -gt 0 ]; then
    FIRST_SOUND_FILE=$(find /var/sounds -name "*.mp3" -o -name "*.wav" | head -1)
    if sudo -u $ASTERISK_USER test -r "$FIRST_SOUND_FILE"; then
        echo "   ✓ Sound file read test: PASSED"
    else
        echo "   ⚠ Sound file read test: FAILED"
        echo "   Manual fix needed: Check sound file permissions"
    fi
else
    echo "   ℹ No sound files found for testing (add them later)"
fi

echo ""

# 7. Display summary
echo "================================================"
echo "                  SETUP COMPLETE"
echo "================================================"
echo "Directories created:"
echo "  - /var/auto_register_call/          (recordings & logs)"
echo "  - /var/sounds/iqtaxi/               (sound files)"
echo "  - /var/sounds/taxiway/              (sound files)"
echo "  - /var/log/auto_register_call/      (system logs)"
echo ""
echo "Permissions set for user: $ASTERISK_USER"
echo ""
echo "Next steps:"
echo "1. Copy your sound files to /var/sounds/iqtaxi/ or /var/sounds/taxiway/"
echo "2. Test your AGI script with a call"
echo "3. Check logs in /var/log/auto_register_call/"
echo ""
echo "If you still have issues, check:"
echo "- Asterisk user name: $ASTERISK_USER"
echo "- File permissions in /var/lib/asterisk/agi-bin/"
echo "- Sound file formats and locations"
echo ""
echo "Script completed successfully!"
echo "================================================"