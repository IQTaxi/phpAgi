#!/usr/bin/env python3
import sys
import json
import requests
import unicodedata

def load_config(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except:
        return None

def remove_diacritics(text):
    if not isinstance(text, str):
        return text
    normalized = unicodedata.normalize('NFD', text)
    return ''.join(c for c in normalized if unicodedata.category(c) != 'Mn')

def is_cosmos_extension(config, current_exten):
    """Check if current extension name is 'Cosmos' (case insensitive)"""
    if not config or current_exten not in config:
        return False
    extension_name = config[current_exten].get('name', '').lower()
    return extension_name == 'cosmos'

def is_airport_query(address_query):
    """Check if address query contains airport-related terms"""
    # Remove diacritics and convert to lowercase for comparison
    query_normalized = remove_diacritics(address_query.strip().lower())
    
    # Airport-related terms (without diacritics for comparison)
    airport_terms = [
        'αεροδομιο', 'αεροδρόμιο', 'airport',
    ]
    
    # Check if any airport term is in the query
    for term in airport_terms:
        if remove_diacritics(term.lower()) in query_normalized:
            return True
    return False

def get_athens_airport_response():
    """Return hardcoded Athens airport data"""
    output = {
        "address": "Αεροδρόμιο Αθηνών Ελευθέριος Βενιζέλος, Σπάτα",
        "location_type": "ROOFTOP",
        "latLng": {
            "lat": 37.9363405,
            "lng": 23.946668
        }
    }
    return json.dumps(output, ensure_ascii=False, separators=(',', ':'))

def fetch_coordinates(address_query, api_key, force_check, pickup, config, current_exten):
    try:
        # Check for local center addresses (existing functionality)
        if pickup == "0" and remove_diacritics(address_query.strip().lower()) in ["κεντρο", "τοπικο", "κεντρο αθηνα", "κεντρο θεσσαλονικη", "κεντρο πατρα", "κεντρο ηρακλειο", "κεντρο λαρισα"]:
            output = {
                "address": str(address_query),
                "location_type": str("EXACT"),
                "latLng": {
                    "lat": float(0),
                    "lng": float(0)
                }
            }
            return json.dumps(output, ensure_ascii=False, separators=(',', ':'))
        
        # NEW FUNCTIONALITY: Check for airport queries if extension is Cosmos
        if is_cosmos_extension(config, current_exten) and is_airport_query(address_query):
            return get_athens_airport_response()
        
        # Original API call functionality
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
            
        location_type = ""
        if force_check != "0":
            location_type = str(results[0]["geometry"]["location_type"])
        
        # Create proper JSON object
        output = {
            "address": str(formatted_address),
            "location_type": str(location_type),
            "latLng": {
                "lat": float(lat),
                "lng": float(lng)
            }
        }
        
        # Return properly formatted JSON with quoted keys
        return json.dumps(output, ensure_ascii=False, separators=(',', ':'))
        
    except Exception as e:
        return ""

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("")
        sys.exit(0)
    
    current_exten = sys.argv[1]
    force_check = sys.argv[2]
    pickup = sys.argv[3]
    address_query = " ".join(sys.argv[4:])
    
    config = load_config('/usr/local/bin/config.json')
    if not config or current_exten not in config:
        print("")
        sys.exit(0)
        
    if 'googleApiKey' not in config[current_exten]:
        print("")
        sys.exit(0)
    
    api_key = config[current_exten]['googleApiKey']
    result = fetch_coordinates(address_query, api_key, force_check, pickup, config, current_exten)
    print(result if result else "")