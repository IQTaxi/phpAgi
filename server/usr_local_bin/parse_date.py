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

def parse_date(api_key, datetext):
    try:
        headers = {"Content-Type": "application/json"}
        body = {
            "input" : datetext,
            "key" : api_key,
            "translateFrom" : "en",
            "translateTo" : "en",
            "matchLang" : "en-US"
        }
        
        response = requests.post(
            "http://www.iqdriver.com/Recognizers/api/Recognize/Date",
            headers=headers,
            data=json.dumps(body),
            timeout=60
        )
        
        if response.status_code == 200:
            result = response.json()
            if not result.get("bestMatch"):
                return ""
            return json.dumps(result, ensure_ascii=False, separators=(',', ':'))
        else:
            return f"Error: {response.status_code} - {response.text}"
            
    except Exception as e:
        traceback.print_exc()
        return f"Error: {str(e)}"

if __name__ == "__main__":
    datetext = "Σε μιαμιση ώρα από τώρα"
    api_key = "AIzaSyDtMW5sRWQ2IsBtAT7ZxoR5LywsKdiVPJw"
    if len(sys.argv) != 3:
        print("Usage: python parse_date.py <current_exten> <datetext>", file=sys.stderr)
        sys.exit(1)
    
    current_exten = sys.argv[1]
    datetext = sys.argv[2]
    
    config = load_config('/usr/local/bin/config.json')
    if not config or current_exten not in config:
        print(f"Extension {current_exten} not found in config", file=sys.stderr)
        sys.exit(1)
        
    if 'googleApiKey' not in config[current_exten]:
        print("API key not found for extension", file=sys.stderr)
        sys.exit(1)
    
    api_key = config[current_exten]['googleApiKey']
    result = parse_date(api_key, datetext)
    print(result, flush=True)