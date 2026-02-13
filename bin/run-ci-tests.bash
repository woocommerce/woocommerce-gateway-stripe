#!/bin/bash

# set strict mode for bash
set -euo pipefail
IFS=$'\n\t'

# set environment variables
WC_STRIPE_DIR="$GITHUB_WORKSPACE"

echo 'Install composer dependencies...'
composer install --no-progress

echo 'Starting MySQL service...'
sudo systemctl start mysql.service

echo 'Setting up test environment...'
bash bin/install-wp-tests.sh woocommerce_test root root localhost $WP_VERSION $WC_VERSION false

echo 'Running the tests...'
bash bin/phpunit.sh
