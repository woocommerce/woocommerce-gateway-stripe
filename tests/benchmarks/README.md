# WooCommerce Stripe Benchmark Fixtures

`fixture-bootstrap.php` prepares deterministic WooCommerce and Stripe state for benchmark workloads that run in a plain WordPress runtime, such as WP Codebox or `wp eval-file`.

Require the helper after WordPress, WooCommerce, and WooCommerce Stripe are loaded:

```php
require_once WP_PLUGIN_DIR . '/woocommerce-gateway-stripe/tests/benchmarks/fixture-bootstrap.php';

$state = WC_Stripe_Benchmark_Fixture_Bootstrap::bootstrap();

// Start benchmark timing after fixture setup and preflight assertions complete.
$start = microtime( true );
```

The helper sets up:

- WooCommerce install tables/roles when available.
- Published cart and checkout pages.
- Store address, currency, and non-coming-soon state.
- A flat-rate shipping zone for checkout availability.
- A purchasable simple product and a cart containing that product.
- Stripe test mode, UPE, saved cards, and express checkout settings.
- Preflight assertions that fail fixture setup before benchmark timing starts.

Stripe keys default to local dummy test values for render-only workloads. WP Codebox workloads that need real Stripe API behavior can provide environment variables:

```sh
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Fixture setup failures throw `RuntimeException` with `Benchmark fixture setup failed:` prefixes so benchmark harnesses can classify them separately from measured regressions.
