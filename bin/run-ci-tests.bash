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
PARATEST=true bash bin/install-wp-tests.sh woocommerce_test root root localhost $WP_VERSION $WC_VERSION false

# Export DB connection details so paratest workers can create per-worker databases
# in the bootstrap safety net (tests/phpunit/bootstrap.php). These are the same
# credentials already passed to install-wp-tests.sh above — the MySQL instance is
# ephemeral, runs only inside this GitHub Actions runner, and is bound to
# localhost, so `root/root` is safe here (and is the WP/WC test-suite convention).
export WP_TESTS_DB_NAME=woocommerce_test
export WORDPRESS_DB_HOST=localhost
export MYSQL_ROOT_PASSWORD=root

echo 'Running the tests in parallel...'
bash bin/phpunit.sh
