# Automatic Call Taxi System

An automated taxi booking system that integrates with FreePBX/Asterisk to provide voice-based taxi ordering through phone calls. The system uses Google's Speech-to-Text API for voice recognition and Geocoding API for address validation.

## Features

- **Voice-to-Text Processing**: Converts caller speech to text using Google Speech-to-Text API
- **Address Validation**: Automatically validates and geocodes pickup and destination addresses
- **Call Flow Management**: Guided voice prompts for seamless user experience
- **Integration Ready**: Connects with existing taxi dispatch systems via API
- **Comprehensive Logging**: Detailed logs for troubleshooting and call tracking
- **Multi-language Support**: Configurable for different languages (via Google APIs)

## System Requirements

### Hardware Requirements
- **CPU**: 2+ cores recommended for concurrent call handling
- **RAM**: Minimum 4GB, 8GB recommended
- **Storage**: At least 10GB free space for logs and recordings

### Software Requirements
- **Operating System**: Ubuntu 18.04+ or CentOS 7+ (Linux-based system)
- **Python**: Python 3.6 or higher
- **Asterisk/FreePBX**: FreePBX 15+ with Asterisk 16+
- **Network**: Stable internet connection for Google API calls

### FreePBX installation ###
- **Make sure that you have installided Debian v12+
- **Then follow instructions from:
```
	https://github.com/FreePBX/sng_freepbx_debian_install
```

```
	wget https://github.com/FreePBX/sng_freepbx_debian_install/raw/master/sng_freepbx_debian_install.sh -O /tmp/sng_freepbx_debian_install.sh
	bash /tmp/sng_freepbx_debian_install.sh
```
	
### OpenVPN installation###
- **follow instructions from:
```
	https://www.cyberciti.biz/faq/debian-11-set-up-openvpn-server-in-5-minutes/
```

```
	sudo apt update
	sudo apt upgrade
	ip a show eth0
	wget https://raw.githubusercontent.com/Angristan/openvpn-install/master/openvpn-install.sh -O debian-11-vpn-server.sh
	chmod -v +x debian-11-vpn-server.sh
	sudo ./debian-11-vpn-server.sh
```	


### Required APIs
- **Google Speech-to-Text API**: For voice recognition
- **Google Geocoding API**: For address location services
- **Taxi Service API**: Your taxi dispatch system endpoint

### 1. Script Installation

```bash

# Copy all Python scripts
sudo cp *.py /usr/local/bin/

# Set proper permissions
sudo chmod +x /usr/local/bin/*.py

```

### 2. Configuration Setup

Create the main configuration file:

```bash
sudo nano /usr/local/bin/config.json
```

**Configuration Template:**

```json
{
	"4036":
	{
	  "name": "<NAME_NOT_CURRENTLY_IN_USR>",
	  "googleApiKey": "<API_KEY_LOOK_AT_3>",
	  "clientToken": "a0a5d57*****************4165a",
	  "registerBaseUrl": "http://******************IQTaxiApi"
	  "failCallTo": "SIP/<NUMBER_TO_CALL>@<TRUNK>"
	},
	"4039":
	{
	  "name": "<NAME_NOT_CURRENTLY_IN_USR>",
	  "googleApiKey": "<API_KEY_LOOK_AT_3>",
	  "clientToken": "asdf*****************zxcv",
	  "registerBaseUrl": "http://******************IQTaxiApi"
	  "failCallTo": "SIP/<NUMBER_TO_CALL>@<TRUNK>"
	}
}
```

### 3. Google API Setup

#### Enable Required APIs:
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the following APIs:
   - **Speech-to-Text API**
   - **Geocoding API**
4. Create credentials (API Key)
5. Restrict API key to your server's IP address

### 4. FreePBX Configuration

#### Upload Audio Files:
1. Access FreePBX web interface (typically `http://your-server-ip/admin`)
2. Navigate to **Admin → System Recordings**
3. upload and check alow all files from sounds

#### Asterisk Extensions Configuration:

Create or edit `/etc/asterisk/extensions_custom.conf`:

copy file extensions_custom.conf

### 5. Testing and Validation

#### Test Individual Components:

```bash
# Test configuration file
python3 -c "import json; print(json.load(open('/usr/local/bin/config.json')))"

# Test speech-to-text conversion with extension ID and WAV file path
python3 /usr/local/bin/send_to_google_stt.py 39 /path/to/audio.wav
# Converts Greek speech in WAV file (16kHz LINEAR16) to text using Google Speech-to-Text API

# Test geocoding with extension ID, force_check flag, pickup flag, and address query
python3 /usr/local/bin/fetch_latlng_google_v4.py 39 0 0 "athens center"
# Returns JSON with coordinates - handles local centers, airports (Cosmos extension), and Google Maps geocoding

# Test call registration (with sample data)
python3 /usr/local/bin/register_call.py /tmp/auto_register_call/<CALLER_ID>/<UNIQUE_CALL_ID>/progress.json
```

#### System Integration Test:

```bash
# Reload Asterisk configuration
sudo asterisk -rx "dialplan reload"

# Check extension is loaded
sudo asterisk -rx "dialplan show 4036"

# Monitor Asterisk logs
sudo tail -f /var/log/asterisk/full

sudo tail -f /var/log/asterisk/full | grep 4037 | ccze -A

```

## File Structure

```
/usr/local/bin/
├── config.json                    # Main configuration file
├── google-credentials.json        # Google service account credentials
├── send_to_google_stt.py         # Speech-to-text processing
├── fetch_latlng_google.py        # Location geocoding
├── register_call.py              # Call registration with taxi API
├── tts.py                        # Text-to-speech generation
└── utils.py                      # Shared utility functions

/etc/asterisk/
└── extensions_custom.conf        # Custom Asterisk extensions

/tmp/auto_register_call/
└── [CALLER_ID]/
    └── [UNIQUE_ID]/
        ├── recordings/           # Call recordings
        ├── progress.json          # Progress json file
        └── log.txt             # Call-specific log
```

## Usage

### Call Flow Process:

1. **Incoming Call**: Caller dials extension `4036`
2. **Welcome Message**: System plays welcome message
3. **Name Collection**: System prompts for and records caller's name
4. **Pickup Address**: System requests pickup location with validation
5. **Destination Address**: System requests destination with validation
6. **Confirmation**: System reads back all information for confirmation
7. **Registration**: Validated call is registered with taxi dispatch system
8. **Completion**: Success message played and call terminated

### Expected Call Duration:
- **Minimum**: 1-3 minutes (smooth interaction)
- **Maximum**: 5-7 minutes (with retries and confirmation)

#### Audio Quality Issues:
- **Low Quality**: Check microphone settings, use noise cancellation
- **File Format**: Ensure recordings are in WAV format, 8kHz sample rate
- **Duration**: Limit recordings to 30 seconds maximum

#### Asterisk Integration:
```bash
# Check Asterisk CLI
sudo asterisk -rvvv

# Reload dialplan
sudo asterisk -rx "dialplan reload"

```

### Log Analysis:

```bash

# /tmp/get_user.log 
Has log of get_user with caller id

# /tmp/register_call_v3.log
Has log of all registerCalls

```

## Contact and Support

For technical support or questions:
- **Documentation**: Refer to this guide and inline code comments
- **Logs**: Check system logs for specific error messages
- **Community**: FreePBX community forums for Asterisk-related issues
- **APIs**: Google Cloud Support for API-related problems

---

**Version**: 2.0.0  
**Last Updated**: 30 May 2025  
**Compatibility**: FreePBX 15+, Asterisk 16+, Python 3.6+

Downgrade Asterisk for SIP (not PJSIP)
https://sangomakb.atlassian.net/wiki/spaces/FP/pages/242581508/Using+FreePBX+17+with+chan_sip

# Apache Analytics Debug Site Setup

For analytics debug site to work, you need to disable Apache's PrivateTmp setting.

## Steps

### 1. Create override directory
```bash
sudo mkdir -p /etc/systemd/system/apache2.service.d/
```

### 2. Create override file to disable PrivateTmp
```bash
sudo tee /etc/systemd/system/apache2.service.d/override.conf > /dev/null << 'EOF'
[Service]
PrivateTmp=no
EOF
```

### 3. Reload systemd configuration
```bash
sudo systemctl daemon-reload
```

### 4. Restart Apache
```bash
sudo systemctl restart apache2
```

### 5. Verify the change
```bash
sudo systemctl show apache2 | grep PrivateTmp
```

The output should show `PrivateTmp=no` if the configuration was applied successfully.
