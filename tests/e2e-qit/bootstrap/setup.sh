#!/bin/bash

# This is an isolated setup script that runs before your plugin's tests.
# Use it to:
# - Create test data specific to your plugin
# - Set up plugin settings
# - Create temporary files
# - Set up test users or other resources needed for the tests

# The WordPress installation is at "/var/www/html"
# This file is at: "/qit/tests/e2e/<your-plugin-slug>/<your-test-tag>/bootstrap/setup.sh"
# You can use relative paths to access files in your test.
# WP CLI is already configured, there's no need to use "--path /var/www/html"

# echo "Running isolated setup for the plugin..."
# wp post create --post_title="Test Post" # Create a test post
# wp user create testuser test@example.com # Create a test user

# Add your setup commands here
. ./common.sh

step "Update Stripe Payment Gateway settings"
output_if_error wp option set woocommerce_stripe_settings --format=json "{\"enabled\":\"yes\",\"title\":\"Credit Card (Stripe)\",\"description\":\"Pay with your credit card via Stripe.\",\"api_credentials\":\"\",\"testmode\":\"yes\",\"test_publishable_key\":\"${STRIPE_PUB_KEY}\",\"test_secret_key\":\"${STRIPE_SECRET_KEY}\",\"publishable_key\":\"\",\"secret_key\":\"\",\"webhook\":\"\",\"test_webhook_secret\":\"\",\"webhook_secret\":\"\",\"inline_cc_form\":\"no\",\"statement_descriptor\":\"\",\"short_statement_descriptor\":\"\",\"capture\":\"yes\",\"payment_request\":\"yes\",\"payment_request_button_type\":\"buy\",\"payment_request_button_theme\":\"dark\",\"payment_request_button_locations\":[\"product\",\"cart\",\"checkout\"],\"payment_request_button_size\":\"default\",\"saved_cards\":\"yes\",\"logging\":\"no\",\"upe_checkout_experience_enabled\":\"yes\"}"

step "Enable feature flags: ACH"
output_if_error wp option update _wcstripe_feature_lpm_ach 'yes'
