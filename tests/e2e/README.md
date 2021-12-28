# E2E Tests

## Setting up
Add stripe `test_publishable_key`, `test_secret_key` and `test_webhook_secret` api keys to config/default.json for each present country. The keys can be found at the [Stripe developer dashboard](https://dashboard.stripe.com/test/apikeys)
You will need to create a new account for each country present and set the correct country inside [Stripe dashboard](https://dashboard.stripe.com/settings/account)

Execute the following commands:

- `$ composer install`
- `$ npm install`
- `$ npm run test:e2e-up`

Test site will be available at localhost:8084/wp-admin

## Running
- `$ npm run test:e2e` to run in headless mode
- `$ npm run test:e2e-dev` to run in non-headless mode



