#!/usr/bin/env python3

import sys
import os
import base64
import json
import traceback
import requests

def load_config(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        print(f"Error loading config file: {e}", file=sys.stderr)
        return None

config = load_config('/usr/local/bin/config.json')  # Adjust path as needed
if not config or 'googleApiKey' not in config:
    print("API key not found in config", file=sys.stderr)
    sys.exit(1)

API_KEY = config['googleApiKey']

GOOGLE_STT_URL = "https://speech.googleapis.com/v1/speech:recognize"

def send_to_google_stt(wav_file):
    try:
        if not os.path.exists(wav_file):
            return f"Error: WAV file {wav_file} does not exist"

        with open(wav_file, "rb") as f:
            audio_content = base64.b64encode(f.read()).decode("utf-8")

        headers = {
            "Content-Type": "application/json"
        }

        body = {
            "config": {
                "encoding": "LINEAR16",
                "sampleRateHertz": 16000,
                "languageCode": "el-GR"
            },
            "audio": {
                "content": audio_content
            }
        }

        response = requests.post(
            f"{GOOGLE_STT_URL}?key={API_KEY}",
            headers=headers,
            data=json.dumps(body),
            timeout=30
        )

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
            return f"Error: {response.status_code} - {response.text}"

    except Exception as e:
        traceback.print_exc()
        return f"Error: {str(e)}"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python send_to_google_stt.py <wav_file>", file=sys.stderr)
        sys.exit(1)

    wav_file = sys.argv[1]
    result = send_to_google_stt(wav_file)
    print(result, flush=True)

