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

def send_to_google_stt(api_key, wav_file):
    try:
        if not os.path.exists(wav_file):
            return f"Error: WAV file {wav_file} does not exist"
            
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
            "audio": {"content": audio_content}
        }
        
        response = requests.post(
            f"https://speech.googleapis.com/v1/speech:recognize?key={api_key}",
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
    if len(sys.argv) != 3:
        print("Usage: python send_to_google_stt.py <current_exten> <wav_file>", file=sys.stderr)
        sys.exit(1)
    
    current_exten = sys.argv[1]
    wav_file = sys.argv[2]
    
    config = load_config('/usr/local/bin/config.json')
    if not config or current_exten not in config:
        print(f"Extension {current_exten} not found in config", file=sys.stderr)
        sys.exit(1)
        
    if 'googleApiKey' not in config[current_exten]:
        print("API key not found for extension", file=sys.stderr)
        sys.exit(1)
    
    api_key = config[current_exten]['googleApiKey']
    result = send_to_google_stt(api_key, wav_file)
    print(result, flush=True)