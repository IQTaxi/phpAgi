#!/usr/bin/env python3
import sys
import json
import os

CONFIG_PATH = "/usr/local/bin/config.json"

def main():
    if len(sys.argv) != 2:
        print(0)
        return

    exten = sys.argv[1]

    if not os.path.isfile(CONFIG_PATH):
        print(0)
        return

    try:
        with open(CONFIG_PATH, "r", encoding="utf-8") as f:
            config = json.load(f)

        print(1 if exten in config else 0)

    except Exception:
        print(0)

if __name__ == "__main__":
    main()
