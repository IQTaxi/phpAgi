#!/bin/bash

# Dynamic Asterisk AGI Setup Script
# Interactive setup with file copying and verification

echo "================================================"
echo "    DYNAMIC ASTERISK AGI SETUP & DEPLOYMENT"
echo "================================================"
echo ""

# Colors for better output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: This script must be run as root (use sudo)${NC}"
    echo "Usage: sudo bash dynamic_asterisk_setup.sh"
    exit 1
fi

# Function to wait for user confirmation
wait_for_confirmation() {
    local message="$1"
    while true; do
        echo -e "${YELLOW}$message (Y/n):${NC} "
        read -r response
        case $response in
            [Yy]* ) return 0;;
            [Nn]* ) 
                echo -e "${RED}Operation cancelled by user.${NC}"
                exit 1;;
            * ) echo "Please answer Y (yes) or n (no).";;
        esac
    done
}

# Function to check if file exists
check_file_exists() {
    local file_path="$1"
    local file_name="$2"
    if [ -f "$file_path" ]; then
        echo -e "   ${GREEN}✓ $file_name found${NC}"
        return 0
    else
        echo -e "   ${RED}✗ $file_name NOT found at $file_path${NC}"
        return 1
    fi
}

# Function to check if directory has files
check_directory_contents() {
    local dir_path="$1"
    local description="$2"
    if [ -d "$dir_path" ] && [ "$(ls -A $dir_path)" ]; then
        echo -e "   ${GREEN}✓ $description directory contains files${NC}"
        return 0
    else
        echo -e "   ${RED}✗ $description directory is empty or doesn't exist${NC}"
        return 1
    fi
}

# Find asterisk user
echo -e "${BLUE}Finding Asterisk user...${NC}"
ASTERISK_USER=""
for user in asterisk ast pbx freepbx; do
    if id "$user" &>/dev/null; then
        ASTERISK_USER="$user"
        break
    fi
done

if [ -z "$ASTERISK_USER" ]; then
    echo -e "${YELLOW}Asterisk user not found, creating 'asterisk' user...${NC}"
    ASTERISK_USER="asterisk"
    if ! id "$ASTERISK_USER" &>/dev/null; then
        useradd -r -s /bin/false asterisk
        echo -e "${GREEN}✓ Created asterisk user${NC}"
    fi
fi

echo -e "${GREEN}Using Asterisk user: $ASTERISK_USER${NC}"
echo ""

# STEP 1: AGI PHP Files
echo "================================================"
echo -e "${BLUE}STEP 1: Copy AGI PHP Files${NC}"
echo "================================================"

# Create destination directory if it doesn't exist
echo "Creating destination directory..."
mkdir -p /var/lib/asterisk/iqtaxi
echo -e "${GREEN}✓ Created /var/lib/asterisk/iqtaxi/${NC}"
echo ""

echo -e "${YELLOW}Please copy the following files from your repository:${NC}"
echo ""
echo "FROM: phpAgi\\server\\agi\\var_lib_asterisk_agi-bin_iqtaxi\\"
echo "  - complete_agi_call_handler.php"
echo "  - config.php"
echo ""
echo "TO: /var/lib/asterisk/iqtaxi/"
echo ""

wait_for_confirmation "Have you copied both PHP files to /var/lib/asterisk/iqtaxi/?"

echo "Checking copied files..."
AGI_FILES_OK=true
if ! check_file_exists "/var/lib/asterisk/iqtaxi/complete_agi_call_handler.php" "complete_agi_call_handler.php"; then
    AGI_FILES_OK=false
fi
if ! check_file_exists "/var/lib/asterisk/iqtaxi/config.php" "config.php"; then
    AGI_FILES_OK=false
fi

if [ "$AGI_FILES_OK" = false ]; then
    echo -e "${RED}Please copy the missing files and run the script again.${NC}"
    exit 1
fi
echo ""

# STEP 2: Sound Files
echo "================================================"
echo -e "${BLUE}STEP 2: Copy Sound Files${NC}"
echo "================================================"

# Create destination directory if it doesn't exist
echo "Creating sounds destination directory..."
mkdir -p /var/sounds/iqtaxi
echo -e "${GREEN}✓ Created /var/sounds/iqtaxi/${NC}"
echo ""

echo -e "${YELLOW}Please copy the sound files from your repository:${NC}"
echo ""
echo "FROM: phpAgi\\server\\agi\\var_sounds\\iqtaxi\\ (including all sound files)"
echo "TO: /var/sounds/iqtaxi/"
echo ""

wait_for_confirmation "Have you copied all sound files to /var/sounds/iqtaxi/?"

echo "Checking sound files..."
if ! check_directory_contents "/var/sounds/iqtaxi" "Sound files"; then
    echo -e "${RED}Please copy the sound files and run the script again.${NC}"
    exit 1
fi
echo ""

# STEP 3: Web PHP Files
echo "================================================"
echo -e "${BLUE}STEP 3: Copy Web PHP Files${NC}"
echo "================================================"

# Create destination directory if it doesn't exist
echo "Creating web directory..."
mkdir -p /var/www/html
echo -e "${GREEN}✓ Web directory /var/www/html/ ready${NC}"
echo ""

echo -e "${YELLOW}Please copy ALL PHP files from your repository:${NC}"
echo ""
echo "FROM: phpAgi\\server\\agi\\var_www_html\\ (all .php files)"
echo "TO: /var/www/html/"
echo ""

wait_for_confirmation "Have you copied all PHP files to /var/www/html/?"

echo "Checking web PHP files..."
WEB_FILES_FOUND=$(find /var/www/html -name "*.php" | wc -l)
if [ "$WEB_FILES_FOUND" -eq 0 ]; then
    echo -e "${RED}✗ No PHP files found in /var/www/html/${NC}"
    echo -e "${RED}Please copy the PHP files and run the script again.${NC}"
    exit 1
else
    echo -e "${GREEN}✓ Found $WEB_FILES_FOUND PHP files in /var/www/html/${NC}"
fi
echo ""

# STEP 4: FreePBX Credentials Script
echo "================================================"
echo -e "${BLUE}STEP 4: FreePBX Credentials Setup${NC}"
echo "================================================"

echo -e "${YELLOW}Please copy the credentials script:${NC}"
echo ""
echo "FROM: phpAgi\\server\\agi\\MISC FILES\\get_freepbx_creds.sh"
echo "TO: /tmp/"
echo ""

wait_for_confirmation "Have you copied get_freepbx_creds.sh to /tmp/?"

if ! check_file_exists "/tmp/get_freepbx_creds.sh" "get_freepbx_creds.sh"; then
    echo -e "${RED}Please copy the script and run this setup again.${NC}"
    exit 1
fi

echo "Making script executable and running it..."
chmod +x /tmp/get_freepbx_creds.sh

echo -e "${YELLOW}Running get_freepbx_creds.sh...${NC}"
echo "================================================"
/tmp/get_freepbx_creds.sh
echo "================================================"
echo ""

echo -e "${YELLOW}IMPORTANT: Copy the password from the output above!${NC}"
echo ""
echo -e "${YELLOW}Please edit /var/www/html/agi_analytics.php:${NC}"
echo "- Find line ~31 with 'primary_pass'"
echo "- Put the password between the single quotes"
echo "- Example: \$primary_pass = 'your_password_here';"
echo ""

wait_for_confirmation "Have you updated the password in agi_analytics.php?"

# STEP 5: File Processing and Permissions
echo "================================================"
echo -e "${BLUE}STEP 5: File Processing and Permissions${NC}"
echo "================================================"

echo "Converting line endings with dos2unix..."
if command -v dos2unix >/dev/null 2>&1; then
    dos2unix /var/lib/asterisk/iqtaxi/complete_agi_call_handler.php
    dos2unix /var/lib/asterisk/iqtaxi/config.php
    echo -e "${GREEN}✓ Line endings converted${NC}"
else
    echo "Installing dos2unix..."
    if command -v apt-get >/dev/null 2>&1; then
        apt-get update && apt-get install -y dos2unix
    elif command -v yum >/dev/null 2>&1; then
        yum install -y dos2unix
    elif command -v dnf >/dev/null 2>&1; then
        dnf install -y dos2unix
    fi
    
    if command -v dos2unix >/dev/null 2>&1; then
        dos2unix /var/lib/asterisk/iqtaxi/complete_agi_call_handler.php
        dos2unix /var/lib/asterisk/iqtaxi/config.php
        echo -e "${GREEN}✓ Line endings converted${NC}"
    else
        echo -e "${YELLOW}⚠ Could not install dos2unix, you may need to do this manually${NC}"
    fi
fi

echo ""
echo "Setting ownership to asterisk user..."
chown -R $ASTERISK_USER:$ASTERISK_USER /var/lib/asterisk/iqtaxi/
chown -R $ASTERISK_USER:$ASTERISK_USER /var/sounds/iqtaxi/
echo -e "${GREEN}✓ Ownership set to $ASTERISK_USER${NC}"

echo ""
echo "Setting specific ownership (ID 999:995) and permissions (775)..."

# AGI files
chown 999:995 /var/lib/asterisk/iqtaxi/complete_agi_call_handler.php
chown 999:995 /var/lib/asterisk/iqtaxi/config.php
chmod 775 /var/lib/asterisk/iqtaxi/complete_agi_call_handler.php
chmod 775 /var/lib/asterisk/iqtaxi/config.php

echo -e "${GREEN}✓ AGI files: complete_agi_call_handler.php, config.php${NC}"

# Web files
if [ -f "/var/www/html/callback.php" ]; then
    chown 999:995 /var/www/html/callback.php
    chmod 775 /var/www/html/callback.php
    echo -e "${GREEN}✓ Web file: callback.php${NC}"
fi

if [ -f "/var/www/html/agi_analytics.php" ]; then
    chown 999:995 /var/www/html/agi_analytics.php
    chmod 775 /var/www/html/agi_analytics.php
    echo -e "${GREEN}✓ Web file: agi_analytics.php${NC}"
fi

if [ -f "/var/www/html/config_manager.php" ]; then
    chown 999:995 /var/www/html/config_manager.php
    chmod 775 /var/www/html/config_manager.php
    echo -e "${GREEN}✓ Web file: config_manager.php${NC}"
fi

echo ""

# STEP 6: Create additional required directories
echo "Creating additional required directories..."
mkdir -p /var/auto_register_call
mkdir -p /var/log/auto_register_call

chown -R $ASTERISK_USER:$ASTERISK_USER /var/auto_register_call/
chown -R $ASTERISK_USER:$ASTERISK_USER /var/log/auto_register_call/
chmod -R 755 /var/auto_register_call/
chmod -R 755 /var/log/auto_register_call/

echo -e "${GREEN}✓ Created additional directories${NC}"

# SELinux handling
echo ""
echo "Checking SELinux..."
if command -v getenforce >/dev/null 2>&1 && [ "$(getenforce)" != "Disabled" ]; then
    echo -e "${YELLOW}SELinux is enabled, setting contexts...${NC}"
    
    if command -v setsebool >/dev/null 2>&1; then
        setsebool -P asterisk_use_nfs on 2>/dev/null || true
        setsebool -P asterisk_read_user_content on 2>/dev/null || true
    fi
    
    if command -v chcon >/dev/null 2>&1; then
        chcon -R -t asterisk_exec_t /var/lib/asterisk/ 2>/dev/null || true
        chcon -R -t var_t /var/auto_register_call/ 2>/dev/null || true
        chcon -R -t asterisk_var_lib_t /var/sounds/ 2>/dev/null || true
    fi
    
    echo -e "${GREEN}✓ SELinux contexts set${NC}"
else
    echo -e "${GREEN}✓ SELinux not enabled or not found${NC}"
fi

# Final Summary
echo ""
echo "================================================"
echo -e "${GREEN}           SETUP COMPLETED SUCCESSFULLY!${NC}"
echo "================================================"
echo ""
echo -e "${BLUE}Files deployed:${NC}"
echo "✓ AGI Scripts: /var/lib/asterisk/iqtaxi/"
echo "  - complete_agi_call_handler.php"
echo "  - config.php"
echo ""
echo "✓ Sound Files: /var/sounds/iqtaxi/"
echo ""
echo "✓ Web Files: /var/www/html/"
echo "  - All PHP files from repository"
echo ""
echo -e "${BLUE}Permissions set:${NC}"
echo "✓ Owner ID: 999, Group ID: 995"
echo "✓ Permissions: 775"
echo "✓ Line endings: Converted (dos2unix)"
echo ""
echo -e "${BLUE}Directories created:${NC}"
echo "✓ /var/auto_register_call/"
echo "✓ /var/log/auto_register_call/"
echo ""
echo -e "${GREEN}Your Asterisk AGI system is ready to use!${NC}"
echo "================================================"