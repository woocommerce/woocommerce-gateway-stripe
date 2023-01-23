# WooCommerce Gateway Stripe End to End Tests

We use [Playwright](https://playwright.dev/) as our test runner. 

## Table of contents

- [WooCommerce Gateway Stripe End to End Tests](#woocommerce-gateway-stripe-end-to-end-tests)
  - [Table of contents](#table-of-contents)
  - [Running E2E Tests](#running-e2e-tests)
    - [Pre-requisites](#pre-requisites)
    - [Environment Setup](#environment-setup)
    - [Running tests](#running-tests)
    - [Debugging tests](#debugging-tests)
    - [Running only selected test suites](#running-only-selected-test-suites)
  - [Guide for writing e2e tests](#guide-for-writing-e2e-tests)
    - [Creating the test structure](#creating-the-test-structure)
    - [Writing the test](#writing-the-test)
  
## Running E2E Tests

### Pre-requisites

- Node.js ([Installation instructions](https://nodejs.org/en/download/))
- NVM ([Installation instructions](https://github.com/nvm-sh/nvm))
- A test site to run the tests on. **Jurassic Ninja sites are recommended**.
- Admin credentials (`wp-admin`) to the test site.

**For the automated setup (optional)**

- SSH access to the test site.
- WP CLI available on the test site server.

### Environment Setup

- Copy the file `/tests/e2e/config/local.env.example` to `/tests/e2e/config/local.env`.
- Edit the variables on the `local.env` file.

### Running tests

**Test Setup**

To set up the test environment, run the command:

`npm run test:e2e-setup -- --base_url=SOME_URL_HERE`

This command will perform the following actions:

- Connect to the test server using SSH and the credentials in the `/tests/e2e/config/local.env` file.
- Install the latest version of WooCommerce from the official WordPress repository.
- Install the latest version of the WooCommerce Gateway Stripe plugin from the official WordPress repository. 
  **Note:** you can specify a different version to test by using the `--version` flag. In this case, the plugin will be downloaded from GitHub instead.
- Install and activate the StoreFront theme.
- Configure WooCommerce on the test site (e.g. store address, currency, shipping methods).
- Import test products into WooCommerce.
- Create pages for the Cart blocks and Checkout blocks from WooCommerce Blocks.
- Set up the Stripe gateway using the keys from the `/tests/e2e/config/local.env` file and create a webhook endpoint on Stripe.

**Note:** To run this command, SSH and admin credentials are required. 

The SSH and admin credentials are mandatory (view the parameters `--with_woo_setup` and `--with_stripe_setup` below for more info).

**Test execution**

`npm run test:e2e -- --base_url=SOME_URL_HERE`

The default command to run the tests. It'll run the tests in the URL indicated by the `--base_url` parameter.

**Optional Parameters**

`--version`: Allows you to specify a specific plugin version to test. This will download the specified version from GitHub and upload it to the test site before running the tests. If no version is specified, the tests will use the version currently installed on the test site.

`--with_woo_setup`: Use this option when setting up a test site for the first time. It will use the SSH credentials from `tests/e2e/config/local.env` to set up the WooCommerce plugin with test products, store address, currency, and shipping methods, as well as installing and activating the StoreFront theme.

`--with_stripe_setup`: Use this option when setting up a test site for the first time. It will use the Stripe keys from `tests/e2e/config/local.env` to set up the plugin, create a webhook endpoint on Stripe, and set up the webhook secret in the Stripe plugin.

**⚠️ All the other parameters are passed to the Playwright CLI**

[Playwright CLI Docs](https://playwright.dev/docs/test-cli)

### Debugging tests

`npm run test:e2e-debug`

[Documentation](https://playwright.dev/docs/debug)

### Running only selected test suites

**Running Tests by Annotation**

Certain tests are annotated to indicate their specific focus, such as subscriptions, blocks, or smoke tests. These annotations are indicated in the test name with the `@` symbol in front of them, for example `Test XYZ @subscriptions`.

To only run tests with a specific annotation, use the `--grep @annotation` parameter when running the tests. For example:

`npm run test:e2e -- --base_url=SOME_URL_HERE --grep @subscriptions`

**Running Tests by File Name**

You can also run tests by specifying the file name containing the test you want to run. Keep in mind that there may be duplicate file names, especially between tests run in the regular checkout and in the blocks checkout.

 `npm run test:e2e -- --base_url=SOME_URL_HERE normal-card`

 In the above example, the command would run the tests with a file name containing `normal-card`.

## Guide for writing e2e tests
### Creating the test structure

Create a new directory under `/tests/e2e/tests/` with the name of the feature or component being tested.

For example, if we're testing the checkout process, the directory would be `/tests/e2e/tests/checkout/`.

### Writing the test

Make sure to follow the established naming conventions for the test files and directories, and to keep the tests organized and easy to understand.

The test should be self-explanatory and should be easily understood by anyone who reads it.

Make sure to follow best practices for writing e2e tests, such as using descriptive and meaningful test names, and keeping the tests as independent as possible to avoid flaky tests.
