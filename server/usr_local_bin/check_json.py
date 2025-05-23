#!/usr/bin/env python3.9
import json
import sys

def check_json(key, json_string):
    """
    Checks if a key exists in a JSON string and has a valid value.
    
    Args:
        key (str): The key to check for in the JSON
        json_string (str): The JSON string to parse and check
    
    Returns:
        int: 1 if the key exists and has a valid value, 0 otherwise
    """
    try:
        # Try to parse the JSON string
        data = json.loads(json_string)
        
        # Check if the key exists
        if key not in data:
            return 0
        
        # Check if the value is not empty
        value = data[key]
        if value is None:
            return 0
            
        # For string values, check if they're empty or just whitespace
        if isinstance(value, str) and value.strip() == "":
            return 0
            
        # Check for arrays/lists
        if isinstance(value, list) and len(value) == 0:
            return 0
            
        # Check for dictionaries/objects
        if isinstance(value, dict) and len(value) == 0:
            return 0
            
        # If we get here, the key exists and has a non-empty value
        return 1
        
    except (json.JSONDecodeError, TypeError):
        # If the JSON is invalid, return failure
        return 0

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python3.9 check_json.py <key> <json_string>")
        sys.exit(1)
    
    key = sys.argv[1]
    json_string = sys.argv[2]
    
    # Print the result (1 for success, 0 for failure)
    result = check_json(key, json_string)
    print(result)
    sys.exit(0 if result == 1 else 1)  # Exit with appropriate code for shell conditionals
