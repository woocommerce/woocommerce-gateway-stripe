#!/usr/bin/env bash

set -e
. ./tests/e2e/bin/common.sh

if [ -z "$WP_PATH" ]; then
  echo "WP_PATH environment variable must be set to your WordPress root."
  exit 1
fi

if [ -z "$STRIPE_PUB_KEY" ] || [ -z "$STRIPE_SECRET_KEY" ]; then
  echo "STRIPE_PUB_KEY and STRIPE_SECRET_KEY must be set in your environment."
  exit 1
fi

redirect_output cli wp option update woocommerce_stripe_settings --format=json "{\"enabled\":\"yes\",\"title\":\"Credit Card (Stripe)\",\"description\":\"Pay with your credit card via Stripe.\",\"api_credentials\":\"\",\"testmode\":\"yes\",\"test_publishable_key\":\"${STRIPE_PUB_KEY}\",\"test_secret_key\":\"${STRIPE_SECRET_KEY}\",\"publishable_key\":\"\",\"secret_key\":\"\",\"webhook\":\"\",\"test_webhook_secret\":\"\",\"webhook_secret\":\"\",\"inline_cc_form\":\"no\",\"statement_descriptor\":\"\",\"short_statement_descriptor\":\"\",\"capture\":\"yes\",\"payment_request\":\"yes\",\"payment_request_button_type\":\"buy\",\"payment_request_button_theme\":\"dark\",\"payment_request_button_locations\":[\"product\",\"cart\",\"checkout\"],\"payment_request_button_size\":\"default\",\"saved_cards\":\"yes\",\"logging\":\"no\",\"upe_checkout_experience_enabled\":\"yes\"}"

