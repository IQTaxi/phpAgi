#!/usr/bin/env python3
import sys
import json
import os

def main():
    if len(sys.argv) != 4:
        print("Usage: save_json.py <key> <value> <json_path>", file=sys.stderr)
        sys.exit(1)
    
    key = sys.argv[1]
    value = sys.argv[2]
    path = sys.argv[3]
    
    # Create directory if it doesn't exist
    os.makedirs(os.path.dirname(path), exist_ok=True)
    
    # Load existing JSON or create empty dict
    try:
        with open(path, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except:
        data = {}
    
    # Try to parse value as JSON, if it fails save as string
    try:
        # Try to parse as JSON
        parsed_value = json.loads(value)
        data[key] = parsed_value
    except:
        # If parsing fails, save as string
        data[key] = value
    
    # Save back to file
    try:
        with open(path, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"Error saving JSON: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()