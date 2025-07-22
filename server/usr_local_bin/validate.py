#!/usr/bin/env python3
import sys
import json

def is_valid_json(value):
    """Check if the input is valid JSON with meaningful content"""
    if not value or value.strip() == "":
        return 0
    
    # Handle special cases
    if value.strip() in ["null", "None", "{}", "[]", '""']:
        return 0
    
    try:
        # Try to parse as JSON
        parsed = json.loads(value)
        
        # Check if it's empty after parsing
        if parsed is None:
            return 0
        if isinstance(parsed, str) and parsed.strip() == "":
            return 0
        if isinstance(parsed, dict) and len(parsed) == 0:
            return 0
        if isinstance(parsed, list) and len(parsed) == 0:
            return 0
        # For location objects, check if they have required fields
        if isinstance(parsed, dict):
            if "latLng" in parsed:
                lat_lng = parsed["latLng"]
                if not isinstance(lat_lng, dict):
                    return 0
                if "lat" not in lat_lng or "lng" not in lat_lng:
                    return 0
                if lat_lng["lat"] is None or lat_lng["lng"] is None:
                    return 0
                    
            if "location_type" in parsed:  # Check if key exists before accessing
                location_type = parsed["location_type"]
                if location_type == "APPROXIMATE" or location_type == "GEOMETRIC_CENTER":
                    return -1
                
        return 1
        
    except (json.JSONDecodeError, TypeError):
        # If it's not JSON, treat it as a string and validate normally
        return 1 if value.strip() != "" else 0

def is_valid_string(value):
    """Check if the input is a valid non-empty string"""
    if value is None:
        return 0
    if isinstance(value, str) and value.strip() == "":
        return 0
    return 1

if __name__ == "__main__":
    if len(sys.argv) == 1:
        # Read from stdin
        try:
            value = sys.stdin.read().strip()
        except:
            print(0)
            sys.exit(1)
    elif len(sys.argv) == 2:
        value = sys.argv[1]
    elif len(sys.argv) == 3:
        value = sys.argv[2]
    else:
        print(0)
        sys.exit(1)
    
    # First try JSON validation
    result = is_valid_json(value)
    
    if result == 0:
        # Fall back to string validation
        result = is_valid_string(value)
        
    if result == -1:
        result = 0
        
    print(result)
    sys.exit(0)