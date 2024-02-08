#!/bin/bash

# set strict mode for bash
set -euo pipefail
IFS=$'\n\t'

# set environment variables
WC_STRIPE_DIR="$GITHUB_WORKSPACE"

echo 'Updating composer version & Install dependencies...'
composer self-update 2.0.6 && composer install --no-progress

echo 'Starting MySQL service...'
sudo systemctl start mysql.service

# On GitHub actions, set MySQL authentication to mysql_native_password instead of caching_sha2_password
# to prevent DB connection problems with PHP versions less than 7.4
if [[ -n $CI ]]; then
	echo "Configuring MySQL to use mysql_native_password"
	mysql -uroot -proot -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root'; FLUSH PRIVILEGES;"
fi

echo 'Setting up test environment...'
bash bin/install-wp-tests.sh woocommerce_test root root localhost $WP_VERSION $WC_VERSION false

echo 'Running the tests...'
bash bin/phpunit.sh