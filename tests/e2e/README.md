# WooCommerce Gateway Stripe End to End Tests

We use [QIT Custom E2E tests](https://qit.woo.com/docs/custom-tests/introduction) to run our end to end tests. 

## Table of contents

- [WooCommerce Gateway Stripe End to End Tests](#woocommerce-gateway-stripe-end-to-end-tests)
  - [Table of contents](#table-of-contents)
  - [Running E2E Tests](#running-e2e-tests)
    - [Pre-requisites](#pre-requisites)
    - [Environment Setup](#environment-setup)
    - [Running tests](#running-tests)
    - [Compatibility Tests](#compatibility-tests)
    - [Running only selected test suites](#running-only-selected-test-suites)
  - [Guide for writing e2e tests](#guide-for-writing-e2e-tests)
    - [Creating the test structure](#creating-the-test-structure)
    - [Writing the test](#writing-the-test)
  
## Running E2E Tests

### Pre-requisites

- Node.js ([Installation instructions](https://nodejs.org/en/download/))
- NVM ([Installation instructions](https://github.com/nvm-sh/nvm))
- QIT CLI
- Docker
- Test keys for a Stripe account.

### Environment Setup

- Copy the file `/tests/e2e/config/local.env.example` to `/tests/e2e/config/local.env`.
- Edit the variables on the `local.env` file.

### Running tests
To set up the test environment and run test, run following commands:

To run tests locally:
- **Default tests:** `npm run test:e2e-local`
- **Legacy tests:** `npm run test:e2e-legacy-local`
- **All tests:** `npm run test:e2e-all-local`

To run tests in CI:
- **Default tests:** `npm run test:e2e -- --env_file ./PATH_TO_ENV_FILE`
- **Legacy tests:** `npm run test:e2e-legacy -- --env_file ./PATH_TO_ENV_FILE`

### Compatibility tests
QIT Custom E2E tests provide a way to run tests for other plugins. To test plugin compatibility with other Woo extensions you have access to, you can use the `--plugin` flag. You can find more information on this [here](https://qit.woo.com/docs/custom-tests/running-other-plugins-tests):

`npm run test:e2e -- --plugin woocommerce-paypal-payments:test`

### Running only selected test suites

**Running Tests by Annotation**

Certain tests are annotated to indicate their specific focus, such as subscriptions, blocks, or smoke tests. These annotations are indicated in the test name with the `@` symbol in front of them, for example `Test XYZ @subscriptions`.

To only run tests with a specific annotation, use the `--grep @annotation` in the `pw_options` parameter when running the tests. For example:

`npm run test:e2e-all-local -- --pw_options="--grep @subscriptions"`

**Running Tests by File Name**

You can also run tests by specifying the file name containing the test you want to run. Keep in mind that there may be duplicate file names, especially between tests run in the regular checkout and in the blocks checkout.

`npm run test:e2e-all-local -- --pw_options="normal-card"`

 In the above example, the command would run the tests with a file name containing `normal-card`.

## Guide for writing e2e tests
### Creating the test structure

Create a new directory under `/tests/e2e/tests/` with the name of the feature or component being tested.

For example, if we're testing the checkout process, the directory would be `/tests/e2e/tests/checkout/`.

### Writing the test

Make sure to follow the established naming conventions for the test files and directories, and to keep the tests organized and easy to understand.

The test should be self-explanatory and should be easily understood by anyone who reads it.

Make sure to follow best practices for writing e2e tests, such as using descriptive and meaningful test names, and keeping the tests as independent as possible to avoid flaky tests.
