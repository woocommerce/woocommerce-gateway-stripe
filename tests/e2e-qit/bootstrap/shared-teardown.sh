#!/bin/bash

# This is a shared teardown script that runs after all plugins' tests are complete.
# Use it to:
# - Reset WordPress settings to default
# - Clean up shared test data
# - Clear caches and transients
# - Reset environment to known state

# echo "Running shared teardown..."
# wp option list # List options that might need resetting
# wp cache list # List caches that might need clearing

# Add your shared teardown commands here