#!/bin/bash
# Simple parser for FreePBX config file

CONFIG_FILE="/etc/freepbx.conf"

USER=$(grep 'AMPDBUSER' "$CONFIG_FILE" | sed -E 's/.*"([^"]+)".*/\1/')
PASS=$(grep 'AMPDBPASS' "$CONFIG_FILE" | sed -E 's/.*"([^"]+)".*/\1/')

echo "pretty username is: $USER"
echo "pretty password is: $PASS"
