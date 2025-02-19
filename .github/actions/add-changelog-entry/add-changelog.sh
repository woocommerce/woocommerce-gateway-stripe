#!/bin/bash

# Exit on error
set -e

# Usage function
usage() {
    echo "Usage: $0 -e CHANGELOG_ENTRY -f FILENAME"
    echo "  -e CHANGELOG_ENTRY   The changelog entry to add"
    echo "  -f FILENAME         The file to update"
    exit 1
}

# Parse arguments
while getopts "e:f:" opt; do
    case $opt in
        e) CHANGELOG_ENTRY="$OPTARG";;
        f) FILENAME="$OPTARG";;
        ?) usage;;
    esac
done

# Validate required arguments
if [ -z "$CHANGELOG_ENTRY" ] || [ -z "$FILENAME" ]; then
    usage
fi

# Trim whitespace from changelog entry
CHANGELOG_ENTRY=$(echo "$CHANGELOG_ENTRY" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')

# Check if file exists
if [ ! -f "$FILENAME" ]; then
    echo "Error: File $FILENAME does not exist"
    exit 1
fi

# Find the token position and insert the changelog entry
TOKEN="xxxx-xx-xx ="
if ! grep -q "$TOKEN" "$FILENAME"; then
    echo "Error: Could not find changelog token in file"
    exit 1
fi

# Create a temporary file
TEMP_FILE=$(mktemp)

# Process the file
awk -v token="$TOKEN" -v entry="$CHANGELOG_ENTRY" '
    {
        if ($0 ~ token) {
            print $0
            print entry
        } else {
            print $0
        }
    }
' "$FILENAME" > "$TEMP_FILE"

# Move temporary file to original
mv "$TEMP_FILE" "$FILENAME" 