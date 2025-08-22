#!/usr/bin/env python3
import sys
import json
import requests
import logging

# Set up logging for debugging
logging.basicConfig(
    filename='/tmp/get_user.log',
    level=logging.DEBUG,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

def load_config(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        logging.error(f"Failed to load config file {filepath}: {e}")
        print("{}")  # Return empty JSON object on error
        sys.exit(1)

def get_user_info(current_exten, phone_number):
    # Load configuration
    config = load_config('/usr/local/bin/config.json')
    
    # Check if extension exists in config
    if current_exten not in config:
        logging.error(f"Extension {current_exten} not found in config")
        print("{}")
        sys.exit(1)
    
    extension_config = config[current_exten]
    accessToken = extension_config.get("clientToken")
    base_url = extension_config.get("registerBaseUrl")
    
    if not accessToken or not base_url:
        logging.error(f"Missing clientToken or registerBaseUrl for extension {current_exten}")
        print("{}")
        sys.exit(1)

    # Construct API URL
    url = base_url.rstrip("/") + f"/api/Calls/checkCallerID/{phone_number}"
    headers = {
        "Authorization": accessToken,
        "Content-Type": "application/json; charset=UTF-8",
    }

    try:
        response = requests.get(url, headers=headers, timeout=30)
        response.raise_for_status()
        data = response.json()

        # Check if the request was successful
        if data.get("result", {}).get("result") != "SUCCESS":
            logging.error(f"API returned error: {data.get('result', {}).get('msg')}")
            return {}

        response_data = data.get("response", {})
        output = {}

        # Always include name if available
        if response_data.get("callerName"):
            output["name"] = response_data["callerName"]
	
        if response_data.get("doNotServe"):
            output["doNotServe"] = "1" if response_data["doNotServe"] else "0"

        # Include address if available
        main_address = response_data.get("mainAddresss")
        if main_address:
            if main_address.get("address"):
                output["pickup"] = main_address["address"]
            
            if main_address.get("comments"):
                output["comments"] = main_address["comments"]
            else :
                output["comments"] = ""

            if main_address.get("lat") is not None and main_address.get("lng") is not None:
                output["latLng"] = {
                        "lat": main_address["lat"],
                        "lng": main_address["lng"]
                    }

        return output

    except requests.RequestException as e:
        logging.error(f"API request error: {e}")
        return {}
    except json.JSONDecodeError:
        logging.error("Invalid JSON response from API")
        return {}
    except Exception as e:
        logging.error(f"Unexpected error: {e}")
        return {}

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("{}")  # Return empty JSON if arguments are incorrect
        sys.exit(1)

    current_exten = sys.argv[1]
    phone_number = sys.argv[2]

    try:
        user_info = get_user_info(current_exten, phone_number)
        print(json.dumps(user_info, ensure_ascii=False))
    except Exception as e:
        logging.error(f"Script execution failed: {e}")
        print("{}")  # Return empty JSON on error
        sys.exit(1)