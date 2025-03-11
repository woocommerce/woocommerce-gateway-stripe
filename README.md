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

## Setup

If you're using the Docker environment see setup instructions here:
https://github.com/woocommerce/woocommerce-gateway-stripe/blob/trunk/docs/DOCKER.md

Install the following plugins:

-   WooCommerce
-   Stripe Dev Tools (clone or download [the GitHub repo](https://github.com/woocommerce/woocommerce-gateway-stripe-dev-tools))

## Debugging

If you are following the Docker setup [here](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/trunk/docs/DOCKER.md), Xdebug is ready to use for debugging.

Install [Xdebug Helper browser extension mentioned here](https://xdebug.org/docs/remote) to enable Xdebug on demand.
