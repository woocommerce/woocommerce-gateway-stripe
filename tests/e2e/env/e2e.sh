#!/usr/bin/env bash

set -e

. ./tests/e2e/env/shared.sh

common_env="NODE_CONFIG_DIR='tests/e2e/test-data'"
test_env="$common_env"

accepted_args=("--base_url" "--version", "--with_woo_setup", "--with_stripe_setup")
additional_args=""
for arg in "$@"; do

    key=$(echo $arg | cut -f1 -d=)
    value=$(echo $arg | cut -f2 -d=)

    # If it's one of the expected parameters, save it in a variable. 
    if [[ ${accepted_args[*]} =~ "${key}" ]]; then
        v="${key/--/}"
        declare $v="${value}"
    else 
        # concatenate to pass along to Playwright
        additional_args="$additional_args $arg"
    fi

done

# Remove the trailing slash from the URL.
base_url=$(echo "$base_url" | sed 's:/*$::')

# Flag for Jurassic Ninja sites. Can be used to set up the site differently.
if [[ $base_url == *".jurassic.ninja" ]]; then
    test_env="$test_env IS_JURASSIC='1'"
fi

if [[ $base_url != "" ]]; then
    test_env="$test_env BASE_URL='${base_url}'"
fi

if [[ $version != "" ]]; then
    test_env="$test_env PLUGIN_VERSION='${version}'"
fi

if [[ $with_woo_setup != "" ]]; then
    test_env="$test_env WOO_SETUP='1'"
fi

if [[ $with_stripe_setup != "" ]]; then
    test_env="$test_env STRIPE_SETUP='1'"
fi

cross-env $test_env playwright test --config=tests/e2e/config/playwright.config.js $additional_args
