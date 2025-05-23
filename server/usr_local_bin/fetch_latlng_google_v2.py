#!/usr/bin/env python3

import sys
import json
import requests

def load_config(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception:
        return None

def fetch_coordinates(address_query, api_key):
    try:
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

        return json.dumps({
            "address": formatted_address,
            "latLng": {
                "lat": lat,
                "lng": lng
            }
        })

    except Exception:
        return ""

if __name__ == "__main__":
    config = load_config('/usr/local/bin/config.json')
    if not config or 'googleApiKey' not in config:
        print("")
        sys.exit(1)

    if len(sys.argv) < 2:
        print("")
        sys.exit(1)
    
    address_query = " ".join(sys.argv[1:])
    result = fetch_coordinates(address_query, config['googleApiKey'])
    print(result)
