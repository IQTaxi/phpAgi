#!/usr/bin/env python3
import sys
import json
import os

def load_json_file(filepath):
    """Load JSON data from a file"""
    try:
        if not os.path.exists(filepath):
            return None, f"File not found: {filepath}"
        
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f), None
    except json.JSONDecodeError as e:
        return None, f"Invalid JSON in file: {e}"
    except Exception as e:
        return None, f"Error reading file: {e}"

def get_value_by_path(data, path):
    """
    Extract value from JSON data using a dot-notation path
    Examples:
    - "name" -> data["name"]
    - "user.email" -> data["user"]["email"]
    - "items.0.title" -> data["items"][0]["title"]
    - "settings.advanced.enabled" -> data["settings"]["advanced"]["enabled"]
    """
    try:
        current = data
        path_parts = path.split('.')
        
        for part in path_parts:
            if part == '':
                continue
                
            # Check if this part is an array index
            if part.isdigit():
                index = int(part)
                if isinstance(current, list) and 0 <= index < len(current):
                    current = current[index]
                else:
                    return None, f"Invalid array index '{index}' or not a list"
            else:
                # Regular dictionary key
                if isinstance(current, dict) and part in current:
                    current = current[part]
                else:
                    return None, f"Key '{part}' not found"
        
        return current, None
    except Exception as e:
        return None, f"Error traversing path: {e}"

def format_output(value, trim=False):
    """Format the output value appropriately"""
    if value is None:
        result = "null"
    elif isinstance(value, (dict, list)):
        result = json.dumps(value, ensure_ascii=False, indent=2)
    elif isinstance(value, str):
        result = value
    else:
        result = str(value)
    
    # Apply trimming if requested
    if trim and ',' in result:
        # Remove text after the last comma
        last_comma_index = result.rfind(',')
        result = result[:last_comma_index]
    
    return result

def print_usage():
    """Print usage information"""
    print("Usage: python json_extractor.py <json_file> <json_path> [trim]")
    print("")
    print("Arguments:")
    print("  json_file  - Path to the JSON file")
    print("  json_path  - Dot-notation path to the desired value")
    print("  trim       - Optional: set to '1' to remove text after the last comma")
    print("")
    print("Examples:")
    print("  python json_extractor.py data.json name")
    print("  python json_extractor.py config.json database.host")
    print("  python json_extractor.py users.json users.0.email")
    print("  python json_extractor.py settings.json app.features.2")
    print("  python json_extractor.py data.json address 1  # with trimming")
    print("")
    print("Path format:")
    print("  - Use dots (.) to separate nested keys")
    print("  - Use numbers for array indices")
    print("  - Example: 'users.0.profile.name' accesses data['users'][0]['profile']['name']")
    print("")
    print("Trim functionality:")
    print("  - When trim=1, removes everything after the last comma in the result")
    print("  - Useful for removing trailing parts of addresses or similar data")

if __name__ == "__main__":
    # Check command line arguments
    if len(sys.argv) < 3 or len(sys.argv) > 4:
        print_usage()
        sys.exit(1)
    
    json_filename = sys.argv[1]
    json_path = sys.argv[2]
    
    # Check for trim parameter
    trim = False
    if len(sys.argv) == 4:
        trim_param = sys.argv[3]
        if trim_param == "1":
            trim = True
        elif trim_param != "0":
            print("Error: trim parameter must be '0' or '1'", file=sys.stderr)
            sys.exit(1)
    
    # Load JSON file
    data, error = load_json_file(json_filename)
    if error:
        print(f"Error: {error}", file=sys.stderr)
        sys.exit(1)
    
    # Extract value using path
    value, error = get_value_by_path(data, json_path)
    if error:
        print(f"Error: {error}", file=sys.stderr)
        sys.exit(1)
    
    # Output the result with optional trimming
    print(format_output(value, trim))