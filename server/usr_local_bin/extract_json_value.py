#!/usr/bin/env python3
import json
import sys

def get_nested_value(data, path):
    try:
        for key in path.split('.'):
            if isinstance(data, dict):
                data = data.get(key, "")
            else:
                return ""
        return data
    except Exception:
        return ""

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("")  # Empty output if usage is incorrect
        sys.exit(0)

    json_input = sys.argv[1]
    key_path = sys.argv[2]  # e.g. "name", "pickup", "pickupLocation.latLng.lat"

    try:
        data = json.loads(json_input)
        value = get_nested_value(data, key_path)
        print(value)
    except Exception:
        print("")

