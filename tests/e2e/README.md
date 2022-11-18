# E2E Tests

## Setting up
Copy the file `tests/e2e/config/template.env` to `tests/e2e/config/local.env`
Add stripe `test_publishable_key`, `test_secret_key` and `test_webhook_secret` api keys to config/test-e2e.json. The keys can be found at the [Stripe developer dashboard](https://dashboard.stripe.com/test/apikeys)

Execute the following commands:

- `$ composer install`
- `$ npm install`
- `$ npm run test:e2e-up`

Test site will be available at localhost:8084/wp-admin

## Running
- `$ npm run test:e2e` to run in headless mode
- `$ npm run test:e2e-dev` to run in non-headless mode



