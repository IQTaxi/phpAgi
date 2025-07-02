#!/usr/bin/env python3
import sys
import os
import base64
import json
import traceback
import requests
import subprocess

def load_config(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        print(f"Error loading config file: {e}", file=sys.stderr)
        return None

def call_google_tts_api(api_key, text, language_code="el-GR", voice_name=None):
    """Call Google Cloud Text-to-Speech API to generate speech"""
    try:
        url = f"https://texttospeech.googleapis.com/v1/text:synthesize?key={api_key}"
        headers = {"Content-Type": "application/json"}
        
        voice_config = {"languageCode": language_code}
        if voice_name:
            voice_config["name"] = voice_name
            
        data = {
            "input": {"text": text},
            "voice": voice_config,
            "audioConfig": {
                "audioEncoding": "MP3",
                "speakingRate": 1.0,
                "pitch": 0.0,
                "volumeGainDb": 0.0
            }
        }
        
        response = requests.post(url, headers=headers, json=data, timeout=30)
        
        if response.status_code == 200:
            response_data = response.json()
            audio_content = base64.b64decode(response_data['audioContent'])
            return audio_content
        else:
            return None
            
    except Exception as e:
        traceback.print_exc()
        return None

def save_audio_files(audio_data, base_path, output_format="both", gain=10):
    """Save MP3 and/or WAV files based on output_format"""
    try:
        saved_files = []
        
        if output_format in ["mp3", "both"]:
            # Save MP3 file
            mp3_path = f"{base_path}.mp3"
            with open(mp3_path, 'wb') as f:
                f.write(audio_data)
            saved_files.append(mp3_path)
        
        if output_format in ["wav", "both"]:
            # Convert to WAV using ffmpeg
            wav_path = f"{base_path}.wav"
            
            # For WAV, we need the MP3 as input to ffmpeg
            if output_format == "wav":
                # Create temporary MP3 file if we're only saving WAV
                temp_mp3_path = f"{base_path}_temp.mp3"
                with open(temp_mp3_path, 'wb') as f:
                    f.write(audio_data)
                input_file = temp_mp3_path
            else:
                # Use the MP3 we already saved
                input_file = f"{base_path}.mp3"
            
            # Build ffmpeg command
            # Apply gain (convert dB to linear scale for ffmpeg volume filter)
            if gain != 0:
                # Convert dB to linear scale: linear = 10^(dB/20)
                linear_gain = 10 ** (gain / 20.0)
                volume_filter = f"volume={linear_gain}"
            else:
                volume_filter = "volume=1.0"
            
            cmd = [
                'ffmpeg', '-y',  # -y to overwrite output files
                '-i', input_file,  # input file
                '-ac', '1',      # mono audio
                '-ar', '8000',   # 8kHz sample rate
                '-af', volume_filter,  # apply volume gain
                wav_path         # output file
            ]
            
            # Run ffmpeg conversion
            result = subprocess.run(cmd, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"ffmpeg error: {result.stderr}", file=sys.stderr)
                return False
            
            saved_files.append(wav_path)
            
            # Clean up temporary MP3 file if we only wanted WAV
            if output_format == "wav" and os.path.exists(temp_mp3_path):
                os.remove(temp_mp3_path)
        
        return saved_files
        
    except Exception as e:
        traceback.print_exc()
        return False

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("Usage: python send_to_google_tts.py <current_exten> <text> <output_base_path> [lang] [gain] [format]", file=sys.stderr)
        print("format: mp3, wav, or both (default: both)", file=sys.stderr)
        print("Example: python send_to_google_tts.py 101 'Hello world' '/tmp/audio' el-GR 10 wav", file=sys.stderr)
        sys.exit(1)
    
    current_exten = sys.argv[1]
    text = sys.argv[2]
    output_base_path = sys.argv[3]
    language_code = sys.argv[4] if len(sys.argv) > 4 else "el-GR"
    gain = float(sys.argv[5]) if len(sys.argv) > 5 else 10
    output_format = sys.argv[6] if len(sys.argv) > 6 else "both"
    
    # Validate output format
    if output_format not in ["mp3", "wav", "both"]:
        print("Error: format must be 'mp3', 'wav', or 'both'", file=sys.stderr)
        sys.exit(1)
    
    # Load config
    config = load_config('/usr/local/bin/config.json')
    if not config or current_exten not in config:
        print(f"Extension {current_exten} not found in config", file=sys.stderr)
        sys.exit(1)
    
    # Try different API key names in config
    api_key = None
    for key_name in ['googleTtsApiKey', 'googleApiKey']:
        if key_name in config[current_exten]:
            api_key = config[current_exten][key_name]
            break
    
    if not api_key:
        print("TTS API key not found for extension", file=sys.stderr)
        sys.exit(1)
    
    # Generate audio
    audio_data = call_google_tts_api(api_key, text, language_code)
    if audio_data is None:
        print("Error: Failed to generate speech from Google TTS API", file=sys.stderr)
        sys.exit(1)
    
    # Save audio files
    saved_files = save_audio_files(audio_data, output_base_path, output_format, gain)
    if saved_files:
        print(f"Audio files saved: {', '.join(saved_files)}", flush=True)
    else:
        print("Error: Failed to save audio files", file=sys.stderr)
        sys.exit(1)