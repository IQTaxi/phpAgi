#!/usr/bin/env python3

import sys
import requests
import json
import os
import traceback

def send_to_stt(wav_file):
    server_url = "http://188.245.212.246:2700/stt"
    try:
        if not os.path.exists(wav_file):
            return f"Error: WAV file {wav_file} does not exist"

        with open(wav_file, 'rb') as f:
            files = {'file': (os.path.basename(wav_file), f, 'audio/wav')}
            response = requests.post(server_url, files=files, timeout=30)

        if response.status_code == 200:
            result = response.json()
            return result.get("text", "No speech detected.")
        else:
            return f"Error: Server returned {response.status_code} - {response.text}"

    except requests.RequestException as e:
        traceback.print_exc()
        return f"Error: Network issue - {str(e)}"
    except json.JSONDecodeError as e:
        traceback.print_exc()
        return f"Error: Invalid JSON response - {str(e)}"
    except Exception as e:
        traceback.print_exc()
        return f"Error: Unexpected issue - {str(e)}"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python send_to_stt.py <wav_file>", file=sys.stderr)
        sys.exit(1)

    wav_file = sys.argv[1]
    result = send_to_stt(wav_file)
    print(result, flush=True)

