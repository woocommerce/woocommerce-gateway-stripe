#!/usr/bin/env bash

# Exit on error
set -e

# Usage function
usage() {
    echo "Usage: $0 -p PR_BODY [-o OUTPUT_FILE]"
    echo "  -p PR_BODY      The PR body content"
    echo "  -o OUTPUT_FILE  Optional: File to write the GitHub Actions output variable (defaults to stdout)"
    exit 1
}

# Parse arguments
while getopts "p:o:" opt; do
    case $opt in
        p) PR_BODY="$OPTARG";;
        o) OUTPUT_FILE="$OPTARG";;
        ?) usage;;
    esac
done

# Validate required arguments
if [ -z "$PR_BODY" ]; then
    usage
fi


# Check if PR doesn't require changelog
DO_NOT_REQUIRE_CHANGELOG=$(grep -E -o '.{0,3} This Pull Request does not require a changelog entry' <<< "$PR_BODY" | awk '{print substr($0,2,1)}')
if [[ $DO_NOT_REQUIRE_CHANGELOG == 'x' ]]; then
    echo "This PR does not require a changelog entry. Exiting."
    exit 0
fi

# Define changelog types
declare -A TYPES=( ["Fix"]="Fix" ["Add"]="Add" ["Update"]="Update" ["Dev"]="Dev" ["Tweak"]="Tweak" )

# Find changelog type
CHANGELOG_TYPE=""
for TYPE in "${!TYPES[@]}"; do
    if [[ $(grep -E -o ".{0,3} $TYPE - " <<< "$PR_BODY" | awk '{print substr($0,2,1)}') == 'x' ]]; then
        CHANGELOG_TYPE=${TYPES[$TYPE]}
        break
    fi
done

if [[ -z $CHANGELOG_TYPE ]]; then
    echo "This PR does not have a valid changelog entry. Exiting."
    exit 0
fi

# Extract changelog message
MESSAGE_SECTION=$(sed -n '/#### Message <!-- Add a changelog message here -->/,/<\/details>/p' <<< "$PR_BODY")
CHANGELOG_CONTENT=$(echo "$MESSAGE_SECTION" | grep -v "^$" | grep -v "#### Message" | grep -v "</details>" | head -n 1)

if [[ -z $CHANGELOG_CONTENT ]]; then
    echo "This PR does not contain a changelog entry. Exiting."
    exit 0
fi

# Format changelog entry
CHANGELOG_ENTRY="* $CHANGELOG_TYPE - $CHANGELOG_CONTENT"

# Output result
if [ -n "$OUTPUT_FILE" ]; then
    echo "CHANGELOG_ENTRY=$CHANGELOG_ENTRY" >> "$OUTPUT_FILE"
else
    echo "$CHANGELOG_ENTRY"
fi
