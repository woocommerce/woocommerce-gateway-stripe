# WooCommerce Gateway Stripe End to End Tests

We use [Playwright](https://playwright.dev/) as our test runner. 

## Table of contents

- [WooCommerce Gateway Stripe End to End Tests](#woocommerce-gateway-stripe-end-to-end-tests)
  - [Table of contents](#table-of-contents)
  - [Running E2E Tests](#running-e2e-tests)
    - [Pre-requisites](#pre-requisites)
  - [Running tests](#running-tests)
    - [Debugging tests](#debugging-tests)
    - [Running only a few test suites](#running-only-a-few-test-suites)
  - [Guide for writing e2e tests](#guide-for-writing-e2e-tests)
    - [Creating the test structure](#creating-the-test-structure)
    - [Writing the test](#writing-the-test)
  
## Running E2E Tests

We use [Playwright](https://playwright.dev/) as our test runner. 

### Pre-requisites

- Node.js ([Installation instructions](https://nodejs.org/en/download/))
- NVM ([Installation instructions](https://github.com/nvm-sh/nvm))

- Create an admin user and a customer user on the test website.
- Copy the file `/tests/e2e/config/local.env.example` to `/tests/e2e/config/local.env`. Add the user credentials created above to the `local.env` file.

## Running tests

`npm run test:e2e -- --base_url=SOME_URL_HERE`

The default command to run the tests. It'll run the tests in the URL indicated by the `--base_url` parameter.

**Optional parameters**

`--version`

The plugin release version to be tested. By setting this parameter, the release will be downloaded from GitHub and uploaded to the website indicated on `--base_url` before testing starts.

If no version is passed, the tests will use the version already installed on `--base_url`.

**⚠️ All the other parameters are passed to the Playwright CLI**

[Playwright CLI Docs](https://playwright.dev/docs/test-cli)

### Debugging tests

`npm run test:e2e-debug`

[Documentation](https://playwright.dev/docs/debug)

### Running only a few test suites

To run only a few test suites, you can pass the test suite name or number as a parameter.

**Example:** This command would run the test suites `001` and `004`.

 `npm run test:e2e -- 001 004`

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
