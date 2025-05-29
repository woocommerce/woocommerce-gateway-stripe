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

redirect_output  cli wp option patch update woocommerce_stripe_settings test_publishable_key "${STRIPE_PUB_KEY}"
redirect_output  cli wp option patch update woocommerce_stripe_settings test_secret_key "${STRIPE_SECRET_KEY}"

