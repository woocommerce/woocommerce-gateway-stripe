# WooCommerce Gateway Stripe Playwright End to End Tests

## Table of contents

- [WooCommerce Gateway Stripe Playwright End to End Tests](#woocommerce-gateway-stripe-playwright-end-to-end-tests)
  - [Table of contents](#table-of-contents)
  - [Pre-requisites](#pre-requisites)
    - [Introduction](#introduction)
    - [About the environment](#about-the-environment)
    - [Test Variables](#test-variables)
    - [Starting/stopping the environment](#startingstopping-the-environment)
  - [Guide for writing e2e tests](#guide-for-writing-e2e-tests)
    - [Creating test structure](#creating-test-structure)
    - [Writing the test](#writing-the-test)
  - [Debugging tests](#debugging-tests)

## Pre-requisites

- Node.js ([Installation instructions](https://nodejs.org/en/download/))
- NVM ([Installation instructions](https://github.com/nvm-sh/nvm))

Note, that if you are on Mac and you install docker through other methods such as homebrew, for example, your steps to set it up might be different. The commands listed in steps below may also vary.

If you are using Windows, we recommend using [Windows Subsystem for Linux (WSL)](https://docs.microsoft.com/en-us/windows/wsl/) for running E2E tests. Follow the [WSL Setup Instructions](../tests/e2e/WSL_SETUP_INSTRUCTIONS.md) first before proceeding with the steps below.

### Introduction

End-to-end tests are powered by Playwright. The test site is spinned up using `wp-env` (recommended), but we will continue to support `e2e-environment` in the meantime.

**Running tests for the first time:**

- `nvm use`
- `npm install`

To run the test again, re-create the environment to start with a fresh state:

- `pnpm env:destroy --filter=woocommerce`
- `pnpm env:test --filter=woocommerce`

Other ways of running tests:

- `pnpm env:test --filter=woocommerce` (headless)
- `cd plugin/woocommerce && USE_WP_ENV=1 pnpm playwright test --config=tests/e2e/playwright.config.js --headed` (headed)
- `cd plugins/woocommerce && USE_WP_ENV=1 pnpm playwright test --config=tests/e2e/playwright.config.js --debug` (debug)
- `cd plugins/woocommerce && USE_WP_ENV=1 pnpm playwright test --config=tests/e2e/playwright.config.js ./tests/e2e/tests/activate-and-setup/basic-setup.spec.js` (running a single test)

To see all options, run `cd plugins/woocommerce && pnpm playwright test --help`

### About the environment

The default values are:

- Latest stable WordPress version
- PHP 7.4
- MariaDB
- URL: `http://localhost:8086/`
- Admin credentials: `admin/password`

If you want to customize these, check the [Test Variables](#test-variables) section.


For more information how to configure the test environment for `wp-env`, please checkout the [documentation](https://github.com/WordPress/gutenberg/tree/trunk/packages/env) documentation.

### Test Variables

The test environment uses the following test variables:

```json
{ 
  "url": "http://localhost:8086/",
  "users": {
    "admin": {
      "username": "admin",
      "password": "password"
    },
    "customer": {
      "username": "customer",
      "password": "password"
    }
  }
}
```

If you need to modify the port for your local test environment (eg. port is already in use) or use, edit [playwright.config.js](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/tests/e2e/playwright.config.js). Depending on what environment tool you are using, you will need to also edit the respective `.json` file.

**Modiify the port wp-env**

Edit [.wp-env.json](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/.wp-env.json) and [playwright.config.js](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/tests/e2e/playwright.config.js).

**Modiify port for e2e-environment**

Edit [tests/e2e/config/default.json](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/tests/e2e/config/default.json).****

### Starting/stopping the environment

After you run a test, it's best to restart the environment to start from a fresh state. We are currently working to reset the state more efficiently to avoid the restart being needed, but this is a work-in-progress.

- `pnpm env:down --filter=woocommerce` to stop the environment
- `pnpm env:destroy --filter=woocommerce` when you make changes to `.wp-env.json`

## Guide for writing e2e tests

### Creating test structure

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

## Debugging tests

For Playwright debugging, follow [Playwright's documentation](https://playwright.dev/docs/debug).
