#!/usr/bin/env bash

echo "Start uploading QIT Custom E2E tests tags";

# Create a temporary directory to copy tests to upload.
tmp_tests_dir="./qit-tests"
rm -rf "$tmp_tests_dir"
mkdir -p "$tmp_tests_dir"

cp -r ./tests/e2e/ $tmp_tests_dir

echo "Uploading default tests tag";
# Remove the legacy tests from the default tests
rm -rf "$tmp_tests_dir/tests/_legacy-experience"
./vendor/bin/qit tag:upload woocommerce-gateway-stripe:default "$tmp_tests_dir"

echo "Uploading legacy tests tag";
# Only include the legacy tests
rm -rf "$tmp_tests_dir/tests/"
mkdir -p "$tmp_tests_dir/tests/_legacy-experience"
cp -r ./tests/e2e/tests/_legacy-experience/ $tmp_tests_dir/tests/_legacy-experience
./vendor/bin/qit tag:upload woocommerce-gateway-stripe:legacy "$tmp_tests_dir"

echo "Uploading subscriptions tests tag";
# Only include the subscriptions tests
rm -rf "$tmp_tests_dir/tests/"
mkdir -p "$tmp_tests_dir/tests/subscriptions"
cp -r ./tests/e2e/tests/subscriptions/ $tmp_tests_dir/tests/subscriptions
./vendor/bin/qit tag:upload woocommerce-gateway-stripe:subscriptions-tests "$tmp_tests_dir"

# Remove the temporary directory
rm -rf "$tmp_tests_dir"

echo "Finished uploading QIT Custom E2E tests tags";
