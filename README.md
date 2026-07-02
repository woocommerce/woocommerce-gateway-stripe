# WooCommerce Stripe Payment Gateway

This is the official feature plugin for accepting payments via the Stripe payment gateway on a WooCommerce store.

## Dependencies

-   WooCommerce

## Version Support Policy

We adopt the L-2 version support policy for WordPress core strictly, and a loose L-2 policy for WooCommerce. See [more details](./docs/version-support-policy.md).

## Development

### Install Dependencies & Build

-   `npm install`
-   `composer install`
-   `npm run build:webpack`, or if you're developing the client you can have it auto-update when changes are made: `npm start`

If you run into errors with `npm install` it may be due to node version, try `nvm install` followed by `nvm use` then try again.

When running the `composer install/update`, Composer may prompt you for a GitHub OAuth token before it can fetch the `subscriptions` and `pre-orders` extensions from GitHub.

### PHPStan workflow

We are using [PHPStan](https://phpstan.org/) to perform static analysis of our code. Every PR will be analysed to check for issues via a GitHub workflow, which may include the following:
 * Legitimate issues that you should resolve before merging, like missing null check or type checks.
 * Error messages that are less clear if they need to be resolved, like array types not specifying a type for their contents.
 * Error messages that indicate an error doesn't exist or could not be found.

**NOTE:** We are still working out which error types need to be fixed, and which can be safely ignored. For now, you can use your best judgement, knowing that most PHPStan errors don't need to be addressed in your PR. However, you SHOULD update the baseline file using the commands below, otherwise your errors will drive noise in `develop` and other developers' PRs.

For the latter two situations, you will need to update the PHPStan baseline file, which you can do by running either of the following commands:
```
npm run phpstan:baseline
```
or
```
composer run-script phpstan:baseline
```

You can also run PHPStan against your code before the automated GitHub workflow runs by using either of the following commands:
```
npm run phpstan
```
or
```
composer run-script phpstan
```

Our goal is to remove our dependence on the need for a baseline file, but we are not there yet.

## Unit Tests

PHP unit tests use PHPUnit and require the Docker environment to be running (`npm run up`).

Run all PHP unit tests:

```
npm run test:php
```

Run tests in parallel using [paratest](https://github.com/paratestphp/paratest) for faster execution:

```
npm run test:php:parallel
```

To collect coverage data during a parallel run, set the `XDEBUG_MODE_PHPUNIT` environment variable:

```
XDEBUG_MODE_PHPUNIT=coverage npm run test:php:parallel
```

## Setup

If you're using the Docker environment see setup instructions here:
https://github.com/woocommerce/woocommerce-gateway-stripe/blob/trunk/docs/DOCKER.md

Install the following plugins:

-   WooCommerce
-   Stripe Dev Tools (clone or download [the GitHub repo](https://github.com/woocommerce/woocommerce-gateway-stripe-dev-tools))

## Debugging

If you are following the Docker setup [here](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/trunk/docs/DOCKER.md), Xdebug is ready to use for debugging.

Install [Xdebug Helper browser extension mentioned here](https://xdebug.org/docs/remote) to enable Xdebug on demand.
