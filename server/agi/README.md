# Asterisk AGI Setup Instructions

## Overview
This guide walks you through setting up a complete Asterisk AGI (Asterisk Gateway Interface) system with interactive voice response capabilities, web analytics, and proper file permissions.

## Prerequisites
- Root access to your Asterisk server
- Access to the `phpAgi` repository with all required files
- Basic knowledge of Linux file operations

## Setup Process

### Step 1: AGI PHP Files Deployment

**Purpose:** Deploy the core AGI scripts that handle call processing

**Files to copy:**
- `complete_agi_call_handler.php`
- `config.php`

**Source Location:** 
```
phpAgi\server\agi\var_lib_asterisk_agi-bin_iqtaxi\
```

**Destination Location:**
```
/var/lib/asterisk/iqtaxi/
```

**What happens:**
- Script creates the destination directory if it doesn't exist
- You manually copy both PHP files
- Script verifies both files are present before continuing

---

### Step 2: Sound Files Deployment

**Purpose:** Deploy audio files used for interactive voice prompts

**Files to copy:** All sound files (mp3, wav, etc.)

**Source Location:**
```
phpAgi\server\agi\var_sounds\iqtaxi\
```

**Destination Location:**
```
/var/sounds/iqtaxi/
```

**What happens:**
- Script creates the sounds directory structure
- You copy all audio files from the repository
- Script verifies the directory contains files

---

### Step 3: Web Interface Files Deployment

**Purpose:** Deploy web-based analytics and management interface

**Files to copy:** All PHP files from the web directory

**Source Location:**
```
phpAgi\server\agi\var_www_html\
```

**Destination Location:**
```
/var/www/html/
```

**Key Files Include:**
- `callback.php`
- `agi_analytics.php` 
- `config_manager.php`
- Any additional PHP files

**What happens:**
- Script ensures web directory exists
- You copy all PHP files
- Script counts and verifies PHP files are present

---

### Step 4: Database Credentials Configuration

**Purpose:** Configure database connection credentials for FreePBX integration

**File to copy:**
```
get_freepbx_creds.sh
```

**Source Location:**
```
phpAgi\server\agi\MISC FILES\get_freepbx_creds.sh
```

**Destination Location:**
```
/tmp/
```

**Process:**
1. Copy the script to `/tmp/`
2. Script automatically makes it executable (`chmod +x`)
3. Script runs the credential extraction script
4. **Important:** Copy the password from the output
5. Edit `/var/www/html/agi_analytics.php`
6. Find line ~31 containing `primary_pass`
7. Paste the password between the single quotes:
   ```php
   $primary_pass = 'your_password_here';
   ```

---

### Step 5: File Processing and Permissions (Automatic)

**Purpose:** Ensure all files have correct formatting, ownership, and permissions

**Line Ending Conversion:**
- Installs `dos2unix` if not present
- Converts Windows line endings to Unix format for:
  - `complete_agi_call_handler.php`
  - `config.php`

**Ownership Configuration:**
- Sets general ownership to Asterisk user (usually `asterisk`)
- Sets specific ownership (UID 999, GID 995) for critical files

**Permission Configuration:**
- Sets 775 permissions on critical files:
  - `/var/lib/asterisk/iqtaxi/complete_agi_call_handler.php`
  - `/var/lib/asterisk/iqtaxi/config.php`
  - `/var/www/html/callback.php`
  - `/var/www/html/agi_analytics.php`
  - `/var/www/html/config_manager.php`

---

### Step 6: System Directory Creation (Automatic)

**Purpose:** Create additional directories required for system operation

**Directories Created:**
- `/var/auto_register_call/` - For call recordings and logs
- `/var/log/auto_register_call/` - For system logs

**Security Configuration:**
- SELinux contexts set if SELinux is enabled
- Proper security contexts for Asterisk operation

---

## File Structure Summary

After completion, your system will have:

```
/var/lib/asterisk/iqtaxi/
├── complete_agi_call_handler.php (775, 999:995)
└── config.php (775, 999:995)

/var/sounds/iqtaxi/
├── [all sound files from repository]
└── [proper ownership and permissions]

/var/www/html/
├── callback.php (775, 999:995)
├── agi_analytics.php (775, 999:995)
├── config_manager.php (775, 999:995)
└── [other PHP files from repository]

/var/auto_register_call/
└── [call recordings and temporary files]

/var/log/auto_register_call/
└── [system logs]
```

## Verification

The script performs automatic verification at each step:

- **File Existence:** Confirms all copied files are present
- **Directory Contents:** Verifies directories contain expected files
- **Permission Testing:** Tests that Asterisk user can read/write files
- **Sound File Access:** Verifies Asterisk can access audio files

## Troubleshooting

**Common Issues:**

1. **Permission Denied Errors:**
   - Ensure script is run with `sudo`
   - Check that Asterisk user exists

2. **File Not Found:**
   - Verify source repository paths
   - Ensure all files are copied completely

3. **Database Connection Issues:**
   - Verify password was correctly copied from credential script
   - Check line 31 in `agi_analytics.php`

4. **SELinux Issues:**
   - Script automatically handles SELinux contexts
   - May require manual adjustment on some systems

## Usage

After setup completion:
1. Your AGI scripts are ready to handle calls
2. Web interface is accessible for analytics
3. Sound files are properly positioned for playback
4. All permissions and ownership are correctly configured

## Script Execution

To run the setup:
```bash
sudo bash dynamic_asterisk_setup.sh
```

Follow the interactive prompts and copy files as instructed. The script will guide you through each step and verify completion before proceeding.

---

*Note: This setup configures a complete Asterisk AGI system with web analytics, interactive voice response, and proper security permissions. Ensure you have backups of any existing configurations before running.*