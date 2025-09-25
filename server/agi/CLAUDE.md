# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

This is an Asterisk AGI (Asterisk Gateway Interface) system for handling taxi dispatch calls with voice recognition, geocoding, and automated taxi booking capabilities. The system integrates with IQTaxi API for taxi dispatch and uses Google Cloud APIs for speech recognition, text-to-speech, and geocoding services.

## Key Architecture Components

### Core AGI Handler
- **var_lib_asterisk_agi-bin_iqtaxi/complete_agi_call_handler.php**: Main call processing script that handles voice interactions, processes customer input, and integrates with taxi dispatch API
- **var_lib_asterisk_agi-bin_iqtaxi/config.php**: Configuration file containing extension-specific settings, API keys, and operational parameters

### Web Interface
- **var_www_html/agi_analytics.php**: Analytics dashboard for monitoring call statistics, transcriptions, and system performance
- **var_www_html/callback.php**: Webhook endpoint for handling asynchronous callbacks from the taxi dispatch system
- **var_www_html/config_manager.php**: Web-based configuration management interface for updating extension settings

### Sound Files
- **var_sounds/iqtaxi/**: Directory containing audio prompts in multiple languages (Greek, English) for system interactions

## Commands

### Installation and Setup
```bash
# Run the interactive setup script (requires root)
sudo bash install.sh

# Extract FreePBX database credentials (used during setup)
bash MISC\ FILES/get_freepbx_creds.sh

# Manual permission setup if needed
sudo bash MISC\ FILES/setup_asterisk_permissions.sh
```

### PHP Syntax Validation
```bash
# Check PHP file syntax
php -l var_lib_asterisk_agi-bin_iqtaxi/complete_agi_call_handler.php
php -l var_lib_asterisk_agi-bin_iqtaxi/config.php
php -l var_www_html/agi_analytics.php
php -l var_www_html/callback.php
php -l var_www_html/config_manager.php
```

### Testing and Debugging
```bash
# Monitor AGI call logs
tail -f /tmp/auto_register_call/*.txt

# Check system logs
tail -f /var/log/auto_register_call/*.log

# Test API connectivity (requires configured extension)
curl -X POST https://www.iqtaxi.com/IQ_WebAPIV3/api/Operator/GetToken \
  -H "Content-Type: application/json" \
  -d '{"username":"USERNAME","password":"PASSWORD"}'
```

## Configuration Structure

Extensions are configured in `config.php` with the following key settings:
- **googleApiKey**: Google Cloud API key for speech/geocoding services
- **clientToken**: Authentication token for IQTaxi API
- **callbackMode**: 1 (normal) or 2 (callback with status check)
- **geocodingApiVersion**: 1 (Google Maps Geocoding) or 2 (Google Places API v1)
- **strictDropoffLocation**: Validation level for destination addresses
- **boundsRestrictionMode**: Geographic boundary constraints (0-3)
- **tts**: Text-to-speech engine ("google" or "elevenlabs")

## API Integration Points

### Google Cloud APIs Required
1. **Cloud Speech-to-Text API** (`speech.googleapis.com`): Voice-to-text conversion
2. **Cloud Text-to-Speech API** (`texttospeech.googleapis.com`): System voice prompts
3. **Cloud Translation API** (`translate.googleapis.com`): Multi-language support
4. **Maps Geocoding API** or **Places API**: Address-to-coordinates conversion

### IQTaxi API Endpoints
- Token acquisition: `/api/Operator/GetToken`
- Order registration: `/api/RegisteredOrder/RegisterOrder`
- Callback handling: Custom webhook URL configured per extension

## File Deployment Paths

Production deployment locations (set by install.sh):
- AGI Scripts: `/var/lib/asterisk/iqtaxi/`
- Sound Files: `/var/sounds/iqtaxi/`
- Web Interface: `/var/www/html/`
- Call Recordings: `/var/auto_register_call/`
- System Logs: `/var/log/auto_register_call/`

## Key Implementation Details

### Call Flow Processing
The system processes calls through these stages:
1. Extension validation and configuration loading
2. Language selection (Greek/English)
3. Customer name capture via speech recognition
4. Pickup address collection and geocoding validation
5. Destination address collection and geocoding
6. Date/time selection for booking
7. API submission to taxi dispatch system
8. Confirmation playback to customer

### Error Handling
- All API failures are logged to `/tmp/auto_register_call/`
- Failed calls can be redirected to configured fallback numbers
- Speech recognition failures trigger retry prompts
- Geocoding failures allow manual address retry

### Security Considerations
- Database credentials stored in `agi_analytics.php` (line ~31)
- API keys restricted to specific services only
- File permissions set to 775 with specific ownership (999:995)
- SELinux contexts configured for Asterisk access