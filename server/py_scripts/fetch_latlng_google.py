#!/usr/bin/env python3

import sys
import os
import requests
import json

def load_config(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        print(f"Error loading config file: {e}", file=sys.stderr)
        return None

config = load_config('/usr/local/bin/config.json')  # Adjust path accordingly
if not config or 'googleApiKey' not in config:
    print("API key not found in config")
    sys.exit(1)

API_KEY = config['googleApiKey']

def fetch_coordinates_from_file(txt_file_path):
    if not os.path.exists(txt_file_path):
        print("")
        return

    try:
        with open(txt_file_path, 'r', encoding='utf-8') as file:
            query_text = file.read().strip()

        if not query_text:
            print("")
            return

        api_url = "https://maps.googleapis.com/maps/api/geocode/json"
        params = {
            "address": query_text,
            "key": API_KEY
        }

        response = requests.get(api_url, params=params, timeout=15)
        response.raise_for_status()
        data = response.json()

        if data.get("status") != "OK":
            print("")
            return

        results = data.get("results", [])
        if not results:
            print("")
            return

        location = results[0]["geometry"]["location"]
        lat = location.get("lat")
        lng = location.get("lng")

        if lat is None or lng is None:
            print("")
            return

        print(f"{lat},{lng}")

    except Exception:
        print("")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("")
        sys.exit(1)

    txt_file = sys.argv[1]
    fetch_coordinates_from_file(txt_file)

