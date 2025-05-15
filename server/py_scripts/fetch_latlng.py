#!/usr/bin/env python3

import sys
import os
import requests
import urllib.parse
import json

def fetch_coordinates_from_file(txt_file_path):
    if not os.path.exists(txt_file_path):
        raise FileNotFoundError(f"Text file not found: {txt_file_path}")

    with open(txt_file_path, 'r', encoding='utf-8') as file:
        query_text = file.read().strip()

    if not query_text:
        raise ValueError("Text file is empty")

    encoded_query = urllib.parse.quote(query_text)
    api_url = f"http://188.245.212.246:2322/api?q={encoded_query}"

    try:
        response = requests.get(api_url, timeout=15)
        response.raise_for_status()
        data = response.json()

        features = data.get("features", [])
        if not features:
            raise ValueError("No features found in response")

        coordinates = features[0]["geometry"]["coordinates"]
        if not isinstance(coordinates, list) or len(coordinates) != 2:
            raise ValueError("Invalid coordinates in response")

        # Print lat,lng directly
        print(f"{coordinates[1]},{coordinates[0]}")

    except (requests.RequestException, json.JSONDecodeError) as e:
        print(f"❌ Error fetching or decoding API response: {e}")
    except Exception as e:
        print(f"❌ Error: {e}")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 fetch_latlng.py <transcript_file.txt>")
        sys.exit(1)

    txt_file = sys.argv[1]
    fetch_coordinates_from_file(txt_file)

