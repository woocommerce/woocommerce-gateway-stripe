# WooCommerce Gateway Stripe End to End Tests

We use [Playwright](https://playwright.dev/) as our test runner. 

## Table of contents

- [WooCommerce Gateway Stripe End to End Tests](#woocommerce-gateway-stripe-end-to-end-tests)
  - [Table of contents](#table-of-contents)
  - [Running E2E Tests](#running-e2e-tests)
    - [Pre-requisites](#pre-requisites)
    - [Setup](#setup)
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

### Setup

- Copy the file `/tests/e2e/config/local.env.example` to `/tests/e2e/config/local.env`.
- Edit the variables on the `local.env` file.

### Running tests

**Set up**

`npm run test:e2e-setup -- --base_url=SOME_URL_HERE`

This command will:

- Connect to the test server via SSH using the credentials on `/tests/e2e/config/local.env`.
- Install the latest WooCommerce release from WP.ORG.
- Install the latest WooCommerce Gateway Stripe release from WP.ORG. *The version can be overriden using the `--version` flag, in this case, the release will be downloaded from GitHub instead of WP.ORG*
- Install the StoreFront theme and activate it. 
- Setup WooCommerce on the test site (store address, currency, shipping methods, etc.). 
- Import test products into WooCommerce.
- Create pages for the Cart blocks and Checkout blocks from WooCommerce Blocks.
- Setup the Stripe gateway using the keys on `/tests/e2e/config/local.env`. The webhook endpoint is also set up on Stripe.

The SSH and admin credentials are mandatory (view the parameters `--with_woo_setup` and `--with_stripe_setup` below for more info).

**Test execution**

`npm run test:e2e -- --base_url=SOME_URL_HERE`

The default command to run the tests. It'll run the tests in the URL indicated by the `--base_url` parameter.

**Optional parameters**

`--version`

The plugin release version to be tested. By setting this parameter, **the release will be downloaded from GitHub** and uploaded to the website indicated on `--base_url` before testing starts.

If no version is passed, the tests will use the version already installed on `--base_url`.

`--with_woo_setup`

Add this option in the first use of a test site. It'll get the SSH credentials from `tests/e2e/config/local.env` to setup the WooCommerce plugin with test products, a store address, a store currency, and shipping methods. It'll also install and activate the StoreFront theme.

`--with_stripe_setup`

Add this option in the first use of a test site. It'll get the Stripe keys from `tests/e2e/config/local.env` to setup the plugin, create a webhook endpoint on Stripe, and setup the webhook secret in the Stripe plugin.

**⚠️ All the other parameters are passed to the Playwright CLI**

[Playwright CLI Docs](https://playwright.dev/docs/test-cli)

### Debugging tests

`npm run test:e2e-debug`

[Documentation](https://playwright.dev/docs/debug)

### Running only selected test suites

**Tests with annotations**

There are test annotations for determined parts of the application, e.g: subscriptions, blocks, smoke tests, etc. They are indicated in the test name with the `@` sign in front of it. Example: `Test XYZ @subscriptions`.

To run only tests with a given annotation, use the parameter `--grep @annotation`. For example:

`npm run test:e2e -- --base_url=SOME_URL_HERE --grep @subscriptions`

**Using the test file name**

This command would run the tests with the file name containing `normal-card`. Please note that there might be repeated file names, specially between tests run in the regular checkout and in the blocks checkout.

 `npm run test:e2e -- --base_url=SOME_URL_HERE normal-card`

## Guide for writing e2e tests

Tests should be added to the `/tests/e2e/tests` folder. Tests should be organized in folders by the tested area, e.g. `/tests/e2e/tests/onboarding`, `/tests/e2e/tests/checkout`, `/tests/e2e/tests/payment-methods`.

To help filter the tests, they should be assigned a 3-digit ID in the file name. Example: `000-upload-plugin.spec.js`.

### Creating the test structure

It is a good practice to start working on the test by identifying what needs to be tested on the higher and lower levels. For example, if you are writing a test to verify that merchant can create a virtual product, the overview of the test will be as follows:

- Merchant can create virtual product
  - Merchant can log in
  - Merchant can create virtual product
  - Merchant can verify that virtual product was created

Once you identify the structure of the test, you can move on to writing it.

### Writing the test

The structure of the test serves as a skeleton for the test itself. You can turn it into a test by using `describe()` and `it()` methods of Playwright:

- [`test.describe()`](https://playwright.dev/docs/api/class-test#test-describe) - creates a block that groups together several related tests;
- [`test()`](https://playwright.dev/docs/api/class-test#test-call) - actual method that runs the test.

Based on our example, the test skeleton would look as follows:

```js
test.describe( 'Merchant can create virtual product', () => {
	test( 'merchant can log in', async () => {

	} );

	test( 'merchant can create virtual product', async () => {

	} );

	test( 'merchant can verify that virtual product was created', async () => {

	} );
} );
```
