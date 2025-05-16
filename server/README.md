# Automatic Call Taxi with FreePBX, Docker, and Python Documentation

Based on your existing setup, I'll document how to rebuild the automated taxi call system using your FreePBX Docker setup and add the gTTS service.

## Docker Installation and Auto-Start Setup

```bash
# Update package index
sudo apt-get update

# Install required packages
sudo apt-get install -y \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    lsb-release

# Add Docker's official GPG key
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg

# Set up the stable repository
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Update apt package index again
sudo apt-get update

# Install Docker Engine and Docker Compose
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Start Docker service
sudo systemctl start docker

# Enable Docker to start on boot
sudo systemctl enable docker

# Add your user to the docker group
sudo usermod -aG docker $USER

# Apply the new group membership (or log out and back in)
newgrp docker
```

## Setting Up Your FreePBX Docker Environment

You already have a docker-compose.yml file in ~/freepbx-docker/ which is well configured. No changes needed there.

## Building and Running the gTTS API Service

You already have the gTTS service files in ~/gtts-api/. Let's create a docker-compose file for it and set it up:

```bash
cd ~/gtts-api/
```

Create a docker-compose.yml file for the gTTS service:

```bash
cat > docker-compose.yml << 'EOF'
version: '3.8'
services:
  gtts-api:
    build: .
    container_name: gtts-api
    restart: unless-stopped
    ports:
      - "221:221"
    networks:
      - freepbx-net

networks:
  freepbx-net:
    external: true
EOF
```

Now build and run the gTTS API service:

```bash
docker-compose up -d
```

## Installing Python Inside the FreePBX Docker Container

Connect to the FreePBX container:

```bash
docker exec -it freepbx bash
```

Once inside the container, install Python and required packages:

```bash
# Update package list
apt-get update

# Install Python and pip
apt-get install -y python3 python3-pip python3-venv

# Install required Python packages
pip3 install requests speechrecognition pydub python-dotenv geopy google-cloud-speech
```

## Setting Up the Automated Taxi Call System Files

Inside the FreePBX container:

```bash
# Create the directory for scripts if it doesn't exist
mkdir -p /usr/local/bin
```

### Configuration File Setup

```bash
cat > /usr/local/bin/config.json << 'EOF'
{
  "name": "test",
  "googleApiKey": "AIzaSyDt...oR5LywsKdiVPJw",
  "clientToken": "H4sIAAAAAAAACmO...mAgG3xZyBLBAAaV4WFGAAAAA==",
  "registerBaseUrl": "https://www.iqtaxi.com/IQ_WebAPIV3"
}
EOF
```

### Copy all files from /usr_local_bin/ to /usr/local/bin/
```
### Make all scripts executable
chmod +x /usr/local/bin/*.py
```

## Configuring Asterisk Extensions

### Custom Extensions Configuration
Still inside the FreePBX container:

```bash
cat > /etc/asterisk/extensions_custom.conf << 'EOF'
[from-internal]
exten => 32,1,Set(UNIQ=${UNIQUEID})
 same => n,Set(FILEBASE=/tmp/${CALLERID(num)}/${UNIQ})
 same => n,System(mkdir -p ${FILEBASE})
 same => n,Wait(1)
 same => n,Playback(custom/welcome-v2)

; --- NAME (3 retries) ---
 same => n,Set(NAME_TRY=1)
 same => n(name_retry),GotoIf($[${NAME_TRY} > 3]?fail)
 same => n,Playback(custom/give-mame-v2)
 same => n,Record(${FILEBASE}/name.wav16,2,10)
 same => n,System(python3.9 /usr/local/bin/send_to_google_stt.py ${FILEBASE}/name.wav16 > ${FILEBASE}/name.txt)
 same => n,Wait(1)
 same => n,Set(NAME_SIZE=${STAT(s,${FILEBASE}/name.txt)})
 same => n,GotoIf($[${NAME_SIZE} < 2]?name_retry_inc)
 same => n,Goto(pickup)

 same => n(name_retry_inc),Set(NAME_TRY=$[${NAME_TRY} + 1])
 same => n,Goto(name_retry)

; --- PICKUP (3 retries) ---
 same => n(pickup),Set(PICKUP_TRY=1)
 same => n(pickup_retry),GotoIf($[${PICKUP_TRY} > 3]?fail)
 same => n,Playback(custom/give-pickup-address-v2)
 same => n,Record(${FILEBASE}/pickup.wav16,2,10)
 same => n,System(python3.9 /usr/local/bin/send_to_google_stt.py ${FILEBASE}/pickup.wav16 > ${FILEBASE}/pickup.txt)
 same => n,System(python3.9 /usr/local/bin/fetch_latlng_google.py ${FILEBASE}/pickup.txt > ${FILEBASE}/location-pickup.txt)
 same => n,Wait(1)
 same => n,Set(PICKUP_TXT_SIZE=${STAT(s,${FILEBASE}/pickup.txt)})
 same => n,GotoIf($[${PICKUP_TXT_SIZE} < 2]?pickup_retry_inc)
 same => n,Set(PICKUP_LOC_SIZE=${STAT(s,${FILEBASE}/location-pickup.txt)})
 same => n,GotoIf($[${PICKUP_LOC_SIZE} < 2]?pickup_retry_inc)
 same => n,Goto(dest)

 same => n(pickup_retry_inc),Set(PICKUP_TRY=$[${PICKUP_TRY} + 1])
 same => n,Goto(pickup_retry)

; --- DESTINATION (3 retries) ---
 same => n(dest),Set(DEST_TRY=1)
 same => n(dest_retry),GotoIf($[${DEST_TRY} > 3]?fail)
 same => n,Playback(custom/give-dest-address-v2)
 same => n,Record(${FILEBASE}/dest.wav16,2,10)
 same => n,System(python3.9 /usr/local/bin/send_to_google_stt.py ${FILEBASE}/dest.wav16 > ${FILEBASE}/dest.txt)
 same => n,System(python3.9 /usr/local/bin/fetch_latlng_google.py ${FILEBASE}/dest.txt > ${FILEBASE}/location-dest.txt)
 same => n,Wait(1)
 same => n,Set(DEST_TXT_SIZE=${STAT(s,${FILEBASE}/dest.txt)})
 same => n,GotoIf($[${DEST_TXT_SIZE} < 2]?dest_retry_inc)
 same => n,Set(DEST_LOC_SIZE=${STAT(s,${FILEBASE}/location-dest.txt)})
 same => n,GotoIf($[${DEST_LOC_SIZE} < 2]?dest_retry_inc)
 same => n,Goto(confirm)

 same => n(dest_retry_inc),Set(DEST_TRY=$[${DEST_TRY} + 1])
 same => n,Goto(dest_retry)

; --- CONFIRM with 3 DTMF attempts ---
 same => n(confirm),Set(CONFIRM_TRY=1)
 same => n(confirm_loop),GotoIf($[${CONFIRM_TRY} > 3]?fail)
 same => n,System(wget -q -O ${FILEBASE}/confirm.mp3 "http://188.245.212.246:221/tts?text=Παρακαλώ επιβεβαιώστε. Όνομα: $(cat ${FILEBASE}/name.txt). Παραλαβή: $(cat ${FILEBASE}/pickup.txt). Προορισμός: $(cat ${FILEBASE}/dest.txt)&lang=el")
 same => n,MP3Player(${FILEBASE}/confirm.mp3)

 same => n,System(wget -q -O ${FILEBASE}/options.mp3 "http://188.245.212.246:221/tts?text=Πατήστε 0 για επιβεβαίωση, 1 για αλλαγή ονόματος, 2 για αλλαγή παραλαβής, 3 για αλλαγή προορισμού&lang=el")
 same => n,Read(DTMF_OPTION,${FILEBASE}/options,1,,,5)
 same => n,GotoIf($["${DTMF_OPTION}" = "0"]?do_register)
 same => n,GotoIf($["${DTMF_OPTION}" = "1"]?name_retry)
 same => n,GotoIf($["${DTMF_OPTION}" = "2"]?pickup_retry)
 same => n,GotoIf($["${DTMF_OPTION}" = "3"]?dest_retry)
 same => n,Playback(custom/invalid-v2)
 same => n,Set(CONFIRM_TRY=$[${CONFIRM_TRY} + 1])
 same => n,Goto(confirm_loop)

; --- REGISTER ---
 same => n(do_register),System(python3.9 /usr/local/bin/register_call.py ${CALLERID(num)} ${FILEBASE}/name.txt ${FILEBASE}/pickup.txt ${FILEBASE}/location-pickup.txt ${FILEBASE}/dest.txt ${FILEBASE}/location-dest.txt > ${FILEBASE}/register.txt)
 same => n,System(wget -q -O ${FILEBASE}/register.mp3 "http://188.245.212.246:221/tts?text=$(cat ${FILEBASE}/register.txt)&lang=el")
 same => n,MP3Player(${FILEBASE}/register.mp3)
 same => n,Hangup()

; --- FAIL PATH ---
 same => n(fail),Playback(custom/invalid-v3)
 same => n,Dial(SIP/20,20)
 same => n,Hangup()
EOF

!!! DONT FORGET TO CHECK AUDIO FILES EXIST!!!
!!! AND CHANGE http://188.245.212.246:221 TO gTTS of this system *(5 lines above)!!!
!!! this if fail redirects call to 20 (change it) !!!

# Reload Asterisk dialplan
asterisk -x "dialplan reload"
```

## Creating a TTS Interface Script to Use Your gTTS Service

```bash
cat > /usr/local/bin/tts.py << 'EOF'
#!/usr/bin/env python3
"""
tts.py - Generate speech from text using the gTTS API service
Usage: tts.py "Text to speak" output_file.mp3 [language]
"""
import sys
import json
import requests
import logging

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger("tts")

def load_config():
    """Load configuration from config.json"""
    try:
        with open("/usr/local/bin/config.json", "r") as f:
            return json.load(f)
    except Exception as e:
        logger.error(f"Error loading config: {e}")
        return None

def text_to_speech(text, output_file, language=None):
    """Convert text to speech using the gTTS API service"""
    config = load_config()
    if not config:
        return False
    
    tts_url = config["tts_service"]["url"]
    default_lang = config["tts_service"]["language"]
    
    # Use provided language or fall back to default
    lang = language or default_lang
    
    try:
        # Make request to gTTS API service
        params = {
            "text": text,
            "lang": lang
        }
        response = requests.get(tts_url, params=params, stream=True)
        
        if response.status_code == 200:
            with open(output_file, 'wb') as f:
                for chunk in response.iter_content(chunk_size=1024):
                    if chunk:
                        f.write(chunk)
            logger.info(f"Speech file saved to {output_file}")
            return True
        else:
            logger.error(f"TTS API error: {response.status_code} - {response.text}")
            return False
    except Exception as e:
        logger.error(f"Error generating speech: {e}")
        return False

def main():
    if len(sys.argv) < 3:
        print("Usage: tts.py \"Text to speak\" output_file.mp3 [language]")
        return 1
    
    text = sys.argv[1]
    output_file = sys.argv[2]
    language = sys.argv[3] if len(sys.argv) > 3 else None
    
    if text_to_speech(text, output_file, language):
        return 0
    else:
        return 1

if __name__ == "__main__":
    sys.exit(main())
EOF

# Make the script executable
chmod +x /usr/local/bin/tts.py
```

## Configuring Network for Services Communication

To ensure that the FreePBX container and gTTS API container can communicate, they need to be on the same network. We've already configured the gTTS service to use the 'freepbx-net' network from your existing setup.

## Accessing FreePBX Web Interface

After installation completes, access the FreePBX web interface at:
```
http://your-server-ip:8082
```

Login credentials (as defined in your docker-compose.yml):
- Username: admin
- Password: J9mB2vL5xT8qW3zR6kN

### Initial Setup Steps in Web UI:
1. Navigate to Admin → System Recordings
2. Upload any necessary voice prompts for your taxi service
3. Sounds located in /sounds

### Adding Custom Extension via Web UI:
1. Go to the Admin tab
2. Navigate to Custom Extensions
3. Ensure your custom extension configuration matches the one in extensions_custom.conf

## Testing the System

### Test the gTTS API Service
```bash
curl "http://localhost:221/tts?text=Hello%20this%20is%20a%20test&lang=en" -o test.mp3
```

### Test TTS Script Inside FreePBX Container
```bash
docker exec -it freepbx bash
/usr/local/bin/tts.py "This is a test" /tmp/test.mp3
```

### Simulate a Call
From within the FreePBX container:
```bash
asterisk -rx "console dial 123@from-internal"
```

### Check Logs
```bash
tail -f /var/log/asterisk/full
```

### Verify Call Processing
Check the caller-specific log directory:
```bash
ls -la /tmp/{CALLERID}/
```

### Updating FreePBX Container
```bash
cd ~/freepbx-docker/
docker-compose pull
docker-compose down
docker-compose up -d
```

### Updating gTTS API Container
```bash
cd ~/gtts-api/
docker-compose down
docker-compose build
docker-compose up -d
```

## FreePBX Utility Guide Quick Reference

### Useful Commands
To connect to the FreePBX container:
```bash
docker exec -it freepbx bash
```

To reload the dial plan:
```bash
asterisk -x "dialplan reload"
```

To edit custom extensions:
```bash
nano /etc/asterisk/extensions_custom.conf
```

To debug extension 333 (taxi service):
```bash
tail -f /var/log/asterisk/full | grep "PJSIP/333"
```

### Important Paths
Path for config file:
```bash
cat /usr/local/bin/config.json
```

Path for scripts:
```bash
cd /usr/local/bin
```

Path for logs (per CALLERID):
```bash
cd /tmp/{CALLERID}
```
Replace {CALLERID} with the actual caller ID to access specific logs.

Path for recordings:
```bash
cd /var/spool/asterisk/monitor
```

Notes:
- Ensure all scripts in /usr/local/bin have executable permissions (chmod +x scriptname.py).
- Make sure config.json has correct values.
- All Python scripts must be placed in the correct folder with proper permissions.
- After setup, access the web UI at http://your-server-ip:8082/admin and go to the admin tab, then system recordings, and in custom extensions, verify the extensions_custom.conf.