#!/usr/bin/env python3

import sys
import json
import requests
import os
import logging
import base64
import traceback
from datetime import datetime

class TaxiCallAGI:
    def __init__(self):
        self.agi_vars = {}
        self.setup_logging()
        self.load_config()
        self.setup_variables()
        
    def setup_logging(self):
        """Setup logging for debugging"""
        logging.basicConfig(
            filename='/tmp/taxi_agi.log',
            level=logging.DEBUG,
            format='%(asctime)s - %(levelname)s - %(message)s'
        )
        
    def load_config(self):
        """Load configuration from config.json"""
        try:
            with open('/usr/local/bin/config.json', 'r', encoding='utf-8') as f:
                self.config = json.load(f)
        except Exception as e:
            logging.error(f"Failed to load config: {e}")
            self.config = {}
            
    def setup_variables(self):
        """Setup AGI variables and file paths"""
        # Read AGI environment variables
        while True:
            line = sys.stdin.readline().strip()
            if line == '':
                break
            key, value = line.split(':', 1)
            self.agi_vars[key.strip()] = value.strip()
        
        # Setup variables
        self.unique_id = self.agi_vars.get('agi_uniqueid', 'unknown')
        self.caller_id = self.agi_vars.get('agi_callerid', 'unknown')
        self.filebase = f"/tmp/{self.caller_id}/{self.unique_id}"
        self.log_prefix = f"[{self.unique_id}]"
        self.max_retries = 3
        
        # Create directory structure
        os.makedirs(f"{self.filebase}/recordings", exist_ok=True)
        
        logging.info(f"{self.log_prefix} Starting call processing for {self.caller_id}")
        
    def agi_command(self, command):
        """Send AGI command and get response"""
        print(command, flush=True)
        response = sys.stdin.readline().strip()
        logging.debug(f"{self.log_prefix} AGI Command: {command} | Response: {response}")
        return response
        
    def log_message(self, message):
        """Log message to both file and logging"""
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        log_entry = f"{timestamp} - {self.log_prefix} {message}"
        logging.info(message)
        
        # Also write to call-specific log file
        with open(f"{self.filebase}/log.txt", "a", encoding='utf-8') as f:
            f.write(log_entry + "\n")
            
    def save_json(self, key, value, json_path):
        """Save key-value pair to JSON file"""
        try:
            # Create directory if it doesn't exist
            os.makedirs(os.path.dirname(json_path), exist_ok=True)
            
            # Load existing JSON or create empty dict
            try:
                with open(json_path, 'r', encoding='utf-8') as f:
                    data = json.load(f)
            except:
                data = {}
            
            # Try to parse value as JSON, if it fails save as string
            try:
                parsed_value = json.loads(value)
                data[key] = parsed_value
            except:
                data[key] = value
            
            # Save back to file
            with open(json_path, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
                
        except Exception as e:
            self.log_message(f"Error saving JSON: {e}")
            
    def validate_input(self, value):
        """Validate input - returns 1 for valid, 0 for invalid"""
        if not value or str(value).strip() == "":
            return 0
        
        # Handle special cases
        if str(value).strip() in ["null", "None", "{}", "[]", '""']:
            return 0
        
        try:
            # Try to parse as JSON
            parsed = json.loads(value)
            
            # Check if it's empty after parsing
            if parsed is None:
                return 0
            if isinstance(parsed, str) and parsed.strip() == "":
                return 0
            if isinstance(parsed, dict) and len(parsed) == 0:
                return 0
            if isinstance(parsed, list) and len(parsed) == 0:
                return 0
                
            # For location objects, check if they have required fields
            if isinstance(parsed, dict):
                if "latLng" in parsed:
                    lat_lng = parsed["latLng"]
                    if not isinstance(lat_lng, dict):
                        return 0
                    if "lat" not in lat_lng or "lng" not in lat_lng:
                        return 0
                    if lat_lng["lat"] is None or lat_lng["lng"] is None:
                        return 0
                    return 1
            
            return 1
            
        except (json.JSONDecodeError, TypeError):
            # If it's not JSON, treat it as a string and validate normally
            return 1 if str(value).strip() != "" else 0
            
    def extract_json_value(self, json_input, key_path):
        """Extract value from JSON using dot notation path"""
        try:
            data = json.loads(json_input)
            for key in key_path.split('.'):
                if isinstance(data, dict):
                    data = data.get(key, "")
                else:
                    return ""
            return str(data) if data else ""
        except Exception:
            return ""
            
    def fetch_coordinates(self, address_query):
        """Fetch coordinates from Google Geocoding API"""
        try:
            api_key = self.config.get('googleApiKey')
            if not api_key:
                return ""
                
            api_url = "https://maps.googleapis.com/maps/api/geocode/json"
            params = {
                "address": address_query,
                "key": api_key,
                "language": "el-GR"
            }
            
            response = requests.get(api_url, params=params, timeout=15)
            response.raise_for_status()
            data = response.json()
            
            if data.get("status") != "OK":
                return ""
            
            results = data.get("results", [])
            if not results:
                return ""
            
            location = results[0]["geometry"]["location"]
            lat = location.get("lat")
            lng = location.get("lng")
            formatted_address = results[0].get("formatted_address", "")
            
            if lat is None or lng is None:
                return ""
            
            # Create proper JSON object
            output = {
                "address": str(formatted_address),
                "latLng": {
                    "lat": float(lat),
                    "lng": float(lng)
                }
            }
            
            return json.dumps(output, ensure_ascii=False, separators=(',', ':'))
            
        except Exception as e:
            self.log_message(f"Error fetching coordinates: {e}")
            return ""
            
    def get_user_info(self, phone_number):
        """Get user information from API"""
        try:
            access_token = self.config.get("clientToken")
            base_url = self.config.get("registerBaseUrl")
            
            if not access_token or not base_url:
                self.log_message("Missing clientToken or registerBaseUrl in config")
                return {}
            
            url = base_url.rstrip("/") + f"/api/Calls/checkCallerID/{phone_number}"
            headers = {
                "Authorization": access_token,
                "Content-Type": "application/json; charset=UTF-8",
            }
            
            response = requests.get(url, headers=headers, timeout=30)
            response.raise_for_status()
            data = response.json()
            
            if data.get("result", {}).get("result") != "SUCCESS":
                self.log_message(f"API returned error: {data.get('result', {}).get('msg')}")
                return {}
            
            response_data = data.get("response", {})
            output = {}
            
            # Always include name if available
            if response_data.get("callerName"):
                output["name"] = response_data["callerName"]
            
            # Include address if available
            main_address = response_data.get("mainAddresss")
            if main_address:
                if main_address.get("address"):
                    output["pickup"] = main_address["address"]
                
                if main_address.get("lat") is not None and main_address.get("lng") is not None:
                    output["latLng"] = {
                        "lat": main_address["lat"],
                        "lng": main_address["lng"]
                    }
            
            return output
            
        except Exception as e:
            self.log_message(f"Error getting user info: {e}")
            return {}
            
    def speech_to_text(self, wav_file):
        """Convert speech to text using Google STT"""
        try:
            api_key = self.config.get('googleApiKey')
            if not api_key:
                return ""
                
            if not os.path.exists(wav_file):
                return ""
            
            with open(wav_file, "rb") as f:
                audio_content = base64.b64encode(f.read()).decode("utf-8")
            
            headers = {"Content-Type": "application/json"}
            body = {
                "config": {
                    "encoding": "LINEAR16",
                    "sampleRateHertz": 16000,
                    "languageCode": "el-GR",
                    "profanityFilter": True 
                },
                "audio": {
                    "content": audio_content
                }
            }
            
            url = f"https://speech.googleapis.com/v1/speech:recognize?key={api_key}"
            response = requests.post(url, headers=headers, data=json.dumps(body), timeout=30)
            
            if response.status_code == 200:
                result = response.json()
                if not result.get("results"):
                    return ""
                return " ".join(
                    alt["transcript"]
                    for res in result["results"]
                    for alt in res["alternatives"]
                )
            else:
                self.log_message(f"STT Error: {response.status_code} - {response.text}")
                return ""
                
        except Exception as e:
            self.log_message(f"STT Exception: {e}")
            return ""
            
    def register_call(self):
        """Register the call with the taxi system"""
        try:
            # Load progress data
            with open(f"{self.filebase}/progress.json", 'r', encoding='utf-8') as f:
                data = json.load(f)
            
            # Validate required fields
            required_fields = ["phone", "name", "pickup", "pickupLocation", "destination", "destinationLocation"]
            for field in required_fields:
                if field not in data:
                    return "Σφάλμα: Λείπουν απαιτούμενα πεδία"
            
            access_token = self.config.get("clientToken")
            base_url = self.config.get("registerBaseUrl")
            
            if not access_token or not base_url:
                return "Σφάλμα: Λείπουν παράμετροι ρυθμίσεων"
            
            # Extract data
            caller_phone = str(data["phone"])
            customer_name = data["name"]
            road_name = data["pickup"]
            pickup_lat = data["pickupLocation"]["latLng"]["lat"]
            pickup_lng = data["pickupLocation"]["latLng"]["lng"]
            destination = data["destination"]
            dest_lat = data["destinationLocation"]["latLng"]["lat"]
            dest_lng = data["destinationLocation"]["latLng"]["lng"]
            
            # API call
            url = base_url.rstrip("/") + "/api/Calls/RegisterNoLogin"
            headers = {
                "Authorization": access_token,
                "Content-Type": "application/json; charset=UTF-8",
            }
            
            payload = {
                "callerPhone": caller_phone,
                "customerName": customer_name,
                "roadName": road_name,
                "latitude": pickup_lat,
                "longitude": pickup_lng,
                "destination": destination,
                "destLatitude": dest_lat,
                "destLongitude": dest_lng,
                "taxisNo": 1,
                "comments": "[ΑΥΤΟΜΑΤΟΠΟΙΗΜΕΝΗ ΚΛΗΣΗ]"
            }
            
            response = requests.post(url, headers=headers, json=payload, timeout=30)
            response.raise_for_status()
            result = response.json()
            
            if result.get("response", {}).get("id", 0) > 0:
                return "Σας ευχαριστούμε που καλέσατε. Ο οδηγός θα είναι κοντά σας σύντομα. Καλή διαδρομή!"
            else:
                return "Σφάλμα: Αποτυχία καταχώρησης της κλήσης. Παρακαλώ προσπαθήστε ξανά."
                
        except Exception as e:
            self.log_message(f"Registration error: {e}")
            return "Σφάλμα: Αποτυχία σύνδεσης με τον διακομιστή"
            
    def generate_tts(self, text, filename):
        """Generate TTS audio file"""
        try:
            url = f"http://188.245.212.246:221/tts?text={text}&lang=el"
            response = requests.get(url, timeout=30)
            
            if response.status_code == 200:
                with open(filename, 'wb') as f:
                    f.write(response.content)
                return os.path.getsize(filename) > 100
            return False
            
        except Exception as e:
            self.log_message(f"TTS error: {e}")
            return False
            
    def collect_data_with_retry(self, data_type, prompt_file, max_retries=3):
        """Generic function to collect data with retries"""
        for attempt in range(1, max_retries + 1):
            self.log_message(f"Collecting {data_type} - Attempt: {attempt}/{max_retries}")
            
            # Play prompt
            self.agi_command(f"EXEC Playback {prompt_file}")
            
            # Record response
            recording_file = f"{self.filebase}/recordings/{data_type}_{attempt}.wav16"
            self.agi_command(f"EXEC Record {recording_file},2,10")
            
            # Convert to text
            self.agi_command("EXEC StartMusicOnHold")
            text_result = self.speech_to_text(recording_file)
            self.agi_command("EXEC StopMusicOnHold")
            
            self.log_message(f"{data_type.capitalize()} STT result: {text_result}")
            
            # Validate result
            if self.validate_input(text_result):
                self.log_message(f"{data_type.capitalize()} successfully captured: {text_result}")
                return text_result.strip()
            
            self.log_message(f"{data_type.capitalize()} validation failed")
            
        return None
        
    def run(self):
        """Main execution function"""
        try:
            # Initialize progress JSON
            progress_file = f"{self.filebase}/progress.json"
            self.save_json("phone", self.caller_id, progress_file)
            
            # Check for anonymous calls
            if self.caller_id.lower() in ['anonymous', '', 'unknown']:
                self.log_message("Anonymous call detected, transferring to operator")
                self.agi_command("EXEC Playback custom/anonymous-v2")
                self.agi_command("EXEC Dial SIP/10,20")
                return
            
            # Play welcome message
            self.agi_command("EXEC Wait 1")
            self.agi_command("EXEC Playback custom/welcome-v2")
            
            # Check for existing user data
            self.log_message("Checking for existing user data")
            self.agi_command("EXEC StartMusicOnHold")
            user_data = self.get_user_info(self.caller_id)
            self.agi_command("EXEC StopMusicOnHold")
            
            self.log_message(f"User data result: {json.dumps(user_data, ensure_ascii=False)}")
            
            # Handle name
            name_result = None
            if user_data.get("name") and self.validate_input(user_data["name"]):
                name_result = user_data["name"]
                self.save_json("name", name_result, progress_file)
                self.log_message(f"Using existing name: {name_result}")
            else:
                name_result = self.collect_data_with_retry("name", "custom/give-name-v2")
                if name_result:
                    self.save_json("name", name_result, progress_file)
                else:
                    self.handle_failure()
                    return
            
            # Handle pickup address
            pickup_result = None
            pickup_location = None
            
            if (user_data.get("pickup") and user_data.get("latLng") and 
                self.validate_input(user_data["pickup"])):
                
                # Offer to use existing address
                pickup_text = user_data["pickup"]
                tts_text = f"Βρήκαμε μια προεπιλεγμένη διεύθυνση: {pickup_text}. Πατήστε 1 για να τη χρησιμοποιήσετε ή 2 για να δώσετε νέα διεύθυνση."
                
                if self.generate_tts(tts_text, f"{self.filebase}/user_prompt.mp3"):
                    self.agi_command(f"EXEC Read USER_CHOICE,{self.filebase}/user_prompt,1,2,3,10")
                    choice = self.agi_command("GET VARIABLE USER_CHOICE").split('=')[1] if '=' in self.agi_command("GET VARIABLE USER_CHOICE") else ""
                    
                    if choice == "1":
                        pickup_result = pickup_text
                        pickup_location = json.dumps({
                            "address": pickup_text,
                            "latLng": user_data["latLng"]
                        }, ensure_ascii=False)
                        self.save_json("pickup", pickup_result, progress_file)
                        self.save_json("pickupLocation", pickup_location, progress_file)
                        self.agi_command("EXEC Playback custom/confirm-default-address-v2")
            
            if not pickup_result:
                pickup_result = self.collect_data_with_retry("pickup", "custom/give-pickup-address-v2")
                if pickup_result:
                    # Get coordinates
                    self.agi_command("EXEC StartMusicOnHold")
                    pickup_location = self.fetch_coordinates(pickup_result)
                    self.agi_command("EXEC StopMusicOnHold")
                    
                    if pickup_location:
                        self.save_json("pickup", pickup_result, progress_file)
                        self.save_json("pickupLocation", pickup_location, progress_file)
                    else:
                        self.handle_failure()
                        return
                else:
                    self.handle_failure()
                    return
            
            # Handle destination
            dest_result = self.collect_data_with_retry("destination", "custom/give-dest-address-v2")
            if dest_result:
                # Get coordinates
                self.agi_command("EXEC StartMusicOnHold")
                dest_location = self.fetch_coordinates(dest_result)
                self.agi_command("EXEC StopMusicOnHold")
                
                if dest_location:
                    self.save_json("destination", dest_result, progress_file)
                    self.save_json("destinationLocation", dest_location, progress_file)
                else:
                    self.handle_failure()
                    return
            else:
                self.handle_failure()
                return
            
            # Confirmation loop
            for confirm_attempt in range(1, 4):
                self.log_message(f"Confirmation attempt: {confirm_attempt}/3")
                
                # Generate confirmation TTS
                confirm_text = f"Παρακαλώ επιβεβαιώστε. Όνομα: {name_result}. Παραλαβή: {pickup_result}. Προορισμός: {dest_result}"
                
                if self.generate_tts(confirm_text, f"{self.filebase}/confirm.mp3"):
                    self.agi_command(f"EXEC Playback {self.filebase}/confirm")
                
                # Get user choice
                self.agi_command("EXEC Read DTMF_OPTION,custom/options-v2,1,2,3,10")
                choice = self.agi_command("GET VARIABLE DTMF_OPTION").split('=')[1] if '=' in self.agi_command("GET VARIABLE DTMF_OPTION") else ""
                
                self.log_message(f"User pressed DTMF: {choice}")
                
                if choice == "0":
                    # Confirm - register call
                    self.agi_command("EXEC StartMusicOnHold")
                    reg_result = self.register_call()
                    self.agi_command("EXEC StopMusicOnHold")
                    
                    if self.generate_tts(reg_result, f"{self.filebase}/register.mp3"):
                        self.agi_command(f"EXEC Playback {self.filebase}/register")
                    
                    self.log_message("Call completed successfully")
                    return
                    
                elif choice in ["1", "2", "3"]:
                    # Retry specific field
                    if choice == "1":  # Name
                        name_result = self.collect_data_with_retry("name", "custom/give-name-v2")
                        if name_result:
                            self.save_json("name", name_result, progress_file)
                        else:
                            self.handle_failure()
                            return
                    elif choice == "2":  # Pickup
                        pickup_result = self.collect_data_with_retry("pickup", "custom/give-pickup-address-v2")
                        if pickup_result:
                            self.agi_command("EXEC StartMusicOnHold")
                            pickup_location = self.fetch_coordinates(pickup_result)
                            self.agi_command("EXEC StopMusicOnHold")
                            if pickup_location:
                                self.save_json("pickup", pickup_result, progress_file)
                                self.save_json("pickupLocation", pickup_location, progress_file)
                            else:
                                self.handle_failure()
                                return
                        else:
                            self.handle_failure()
                            return
                    elif choice == "3":  # Destination
                        dest_result = self.collect_data_with_retry("destination", "custom/give-dest-address-v2")
                        if dest_result:
                            self.agi_command("EXEC StartMusicOnHold")
                            dest_location = self.fetch_coordinates(dest_result)
                            self.agi_command("EXEC StopMusicOnHold")
                            if dest_location:
                                self.save_json("destination", dest_result, progress_file)
                                self.save_json("destinationLocation", dest_location, progress_file)
                            else:
                                self.handle_failure()
                                return
                        else:
                            self.handle_failure()
                            return
                else:
                    # Invalid choice
                    self.agi_command("EXEC Playback custom/invalid-v2")
                    continue
            
            # If we get here, confirmation failed
            self.handle_failure()
            
        except Exception as e:
            self.log_message(f"Unexpected error: {e}")
            traceback.print_exc()
            self.handle_failure()
            
    def handle_failure(self):
        """Handle call failure"""
        self.log_message("Call failed - transferring to operator")
        self.agi_command("EXEC Playback custom/invalid-v3")
        self.agi_command("EXEC Dial SIP/10,20")

if __name__ == "__main__":
    agi = TaxiCallAGI()
    agi.run()