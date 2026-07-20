# Agentic Commerce Product Feed Generator

Streaming CSV feed implementation for Stripe's Agentic Commerce feature. Handles large product catalogs (100k+ products, 200MB+ files) without memory issues.

## Architecture

Follows WooCommerce's `FeedInterface` pattern (similar to `JsonFileFeed`):
- Base name passed to constructor (not configuration)
- Columns configured via `set_columns()` method
- Two-stage storage: temp dir → uploads directory
- File naming: `{base-name}-{YYYY-MM-DD}-{hash}.csv`

## Usage

```php
$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'stripe-agentic-commerce' );
$feed->set_columns( ['id', 'title', 'description', 'price', 'image_url'] );

$feed->start();

foreach ( $products as $product ) {
    $feed->add_entry( [
        $product->get_id(),
        $product->get_title(),
        $product->get_description(),
        $product->get_price(),
        $product->get_image_url(),
    ] );
}

$feed->end();
$url = $feed->get_file_url(); // Triggers move from temp to uploads
```

## Data Format Requirements

### Scalar Values Only
All entry values must be scalars (`string`, `int`, `float`, `bool`) or `null`:

```php
// ✅ Correct
$feed->add_entry( ['123', 'Product Name', '19.99 USD', true, null] );

// ❌ Wrong - throws exception
$feed->add_entry( ['123', ['url1.jpg', 'url2.jpg']] );
```

**Type Handling:**
- **Booleans**: Converted to `"true"` or `"false"` (required by Stripe spec)
- **Null**: Converted to empty string `""` (for optional fields)
- **Numbers**: Cast to strings (e.g., `123` → `"123"`, `3.14` → `"3.14"`)
- **Strings**: Passed through as-is

**Important**: For Stripe fields with units (price, weight, dimensions), you must format them as strings yourself:
```php
$feed->add_entry( [
    'id'     => '123',
    'price'  => '15.00 USD',  // Not just 15.00
    'weight' => '2.5 lb',     // Not just 2.5
] );
```

### Complex Data Formatting
Format arrays/objects as strings **before** passing to `add_entry()`:

**Multiple values** - Comma-separated:
```php
$images = ['url1.jpg', 'url2.jpg', 'url3.jpg'];
$feed->add_entry( [
    'id' => '123',
    'images' => implode( ',', $images ), // "url1.jpg,url2.jpg,url3.jpg"
] );
```

**Structured data** - Colon-delimited (per Stripe spec):
```php
// Shipping: Country:State:Method:Time:Cost
$feed->add_entry( [
    'id' => '123',
    'shipping' => 'US:CA:Express:1-2:12.99 USD',
] );
```

See [Stripe Agentic Commerce documentation](https://docs.stripe.com/agentic-commerce/product-catalog) for full specification.

## Key Features

### Streaming I/O
- Direct `fputcsv()` writes - no memory buffering
- Handles 100k+ products efficiently
- Progress logging every 10k entries

### Two-Stage Storage
1. **Write phase:** Creates file in `get_temp_dir()` (fast, no permission issues)
2. **Finalize phase:** Moves to uploads on `get_file_url()` call

```php
$feed->start();     // Creates file in temp dir
$feed->add_entry(); // Writes to temp
$feed->end();       // Closes file handle
$url = $feed->get_file_url(); // Moves temp → uploads, returns URL
```

### UTF-8 Encoding
- Standard UTF-8 encoding (no BOM)
- Automatic encoding validation and conversion
- Handles emoji, accents, CJK characters

### Error Handling
- Automatic cleanup on exceptions
- Destructor removes incomplete files
- Logging via `WC_Stripe_Logger`

## File Storage

**Temporary:** `get_temp_dir()` (system temp directory)
**Final:** `wp-content/uploads/stripe-agentic-commerce/product-feeds/`

**File naming:** `{base-name}-{YYYY-MM-DD}-{hash}.csv`
- Uses `wp_hash()` for uniqueness (like `JsonFileFeed`)

**Security:**
- `.htaccess` and `index.html` created automatically via `FilesystemUtil::mkdir_p_not_indexable()`
- Prevents directory listing
- Files accessible via direct URL only

## API Reference

### Constructor

```php
__construct( string $base_name )
```

Creates feed with base name identifier. Does not initialize storage.

### set_columns()

```php
set_columns( array $headers ): self
```

Configures CSV column headers. Returns `$this` for chaining.

**Parameters:**
- `$headers` - Array of column names (e.g., `['id', 'title', 'price']`)

**Returns:** `self` (for method chaining)

**Example:**
```php
$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'my-feed' );
$feed->set_columns( ['id', 'name'] );
```

### start()

```php
start(): void
```

Initializes feed generation:
1. Validates headers are set
2. Creates file in temp directory (or uploads fallback)
3. Writes CSV header row

**Throws:** `Exception` if headers not set or file cannot be created

### add_entry()

```php
add_entry( array $entry ): void
```

Streams one product entry to CSV file.

**Parameters:**
- `$entry` - Array of values matching column count (must be scalars or null)

**Throws:**
- `Exception` if not started or already finalized
- `Exception` if column count mismatch
- `Exception` if entry contains arrays/objects

**Example:**
```php
$feed->add_entry( ['123', 'Product Name', '19.99'] );
```

### end()

```php
end(): void
```

Finalizes feed generation:
1. Closes file handle
2. Marks feed as complete
3. Logs statistics (entries, file size)

File remains in temp directory until `get_file_url()` is called.

### get_file_path()

```php
get_file_path(): ?string
```

Returns absolute file path (temp or uploads location).

**Returns:** File path if finalized, `null` otherwise

### get_file_url()

```php
get_file_url(): ?string
```

Moves file from temp to uploads directory and returns public URL.

**Returns:** Public URL if finalized, `null` otherwise

**Throws:** `Exception` if file cannot be moved

**Note:** Only call after `end()`. Triggers file relocation on first call.

### get_stats()

```php
get_stats(): array
```

Returns feed generation statistics.

**Returns:**
```php
[
    'started'         => bool,
    'finalized'       => bool,
    'entry_count'     => int,
    'file_size_bytes' => int,    // Only if finalized
    'file_size_human' => string, // Only if finalized (e.g., "2.3 MB")
    'file_path'       => string, // Only if finalized
    'file_url'        => string, // Only if finalized
]
```

### set_headers()

```php
set_headers(): void
```

Sets HTTP headers for CSV download (not part of FeedInterface).

Use when serving file directly:
```php
$feed->set_headers();
readfile( $feed->get_file_path() );
```

## Integration Example

```php
class My_Integration implements IntegrationInterface {
    public function create_feed(): FeedInterface {
        return new WC_Stripe_Agentic_Commerce_Csv_Feed( 'my-integration' );
    }

    public function get_product_mapper(): ProductMapperInterface {
        return new class implements ProductMapperInterface {
            public function map_product( $product ): array {
                return [
                    $product->get_id(),
                    $product->get_name(),
                    $product->get_price(),
                ];
            }
        };
    }
}

// Usage with ProductWalker
$integration = new My_Integration();
$feed = $integration->create_feed();
$feed->set_columns( ['id', 'name', 'price'] );

$walker = ProductWalker::from_integration( $integration, $feed );
$walker->walk();
```

## Performance Characteristics

- **Memory:** O(1) - constant memory usage regardless of catalog size
- **Disk I/O:** Linear writes only - no seeks
- **CPU:** Minimal - just CSV escaping
- **Time:** ~10k products/second on typical hardware

**Benchmarks** (approximate):
- 10k products: ~1 second, ~2 MB file
- 100k products: ~10 seconds, ~20 MB file
- 1M products: ~100 seconds, ~200 MB file

## Error Scenarios

### File System Issues
```php
try {
    $feed->start();
} catch ( Exception $e ) {
    // Cannot create temp file or uploads directory
    WC_Stripe_Logger::error( 'Feed start failed: ' . $e->getMessage() );
}
```

### Invalid Data
```php
try {
    $feed->add_entry( ['id' => 1, 'images' => ['a.jpg', 'b.jpg']] );
} catch ( Exception $e ) {
    // "CSV entry at index 1 contains an array or object"
}
```

### Cleanup on Failure
```php
$feed = new WC_Stripe_Agentic_Commerce_Csv_Feed( 'test' );
$feed->set_columns( ['id', 'name'] );
$feed->start();
// Exception thrown - the temporary file is automatically cleaned up.
unset( $feed );
```

## Stripe Agentic Commerce Specification

For complete field specifications and data formatting requirements, see:
https://docs.stripe.com/agentic-commerce/product-catalog

Common patterns:
- **Images:** `image1.jpg,image2.jpg,image3.jpg`
- **Categories:** `Electronics > Computers > Laptops`
- **Variants:** `Size:Large,Color:Blue`
- **Shipping:** `US:CA:Express:1-2:12.99 USD`

## Checkout Mode (embedded vs. redirect)

Stripe's feed supports two checkout behaviors per product via the `disable_checkout` field — the same feed and Files API delivery serve both, so this is not a separate ingestion path:

- **Embedded / delegated checkout** (`disable_checkout=false`, the default): the shopper completes the purchase inside the AI agent.
- **Feed-only / redirect** (`disable_checkout=true`): the product is still syndicated for discovery, but the agent sends the shopper to the product's `link` URL to check out on the store.

The store-wide default is set in **Stripe settings → Agentic commerce → "Redirect shoppers to my store to check out"** (option `wc_stripe_agentic_commerce_disable_checkout`). Per-product overrides go through a filter:

```php
add_filter(
    'woocommerce_agentic_commerce_disable_checkout',
    function ( bool $disabled, WC_Product $product, ?WC_Product $parent ): bool {
        // e.g. redirect only for a specific category.
        return has_term( 'made-to-order', 'product_cat', $product->get_id() ) ? true : $disabled;
    },
    10,
    3
);
```

> The Stripe-prefixed `wc_stripe_agentic_commerce_disable_checkout` filter is **deprecated since 10.9.0** in favour of the shareable `woocommerce_agentic_commerce_disable_checkout` above (mirroring the `woocommerce_agentic_commerce_should_sync_product` migration). Existing hooks on the old name still run — they seed the new filter's default — but emit a deprecation notice.

## Shipping diagnostics

Only methods with a static numeric cost (flat rate, free shipping) are written
to the feed's `shipping` column. Live-rate, calculated, and most third-party
methods price at checkout and are omitted. When that leaves a zone with no
shipping in the feed, agents see no shipping for that destination.

This is discoverable two ways:

- **Logs** (WooCommerce → Status → Logs, `wc-stripe` source):
  - *info* — "shipping method has no flat rate and was omitted from the feed"
    (with the zone, method id, and method title), once per sync.
  - *warning* — "shipping zone has no flat-rate method; it contributes no
    shipping options to the feed" (with the zone name).
- **Feed preview** (**Stripe settings → Agentic commerce → Preview feed**): the
  `shipping_warnings` array names every zone with no flat-rate fallback.

**Recommended fix:** add a low-cost or representative flat-rate method to each
live-rate-only zone as a feed fallback, so the catalog advertises a shipping
price while WooCommerce still computes the real live rate at checkout.

## Merchant configuration cookbook

Recipes for onboarding catalogs that mix standard SKUs with configurator
products (WooCommerce Product Add-Ons, TM Extra Product Options, Composite
Products, individually-priced Bundles) — without writing custom mu-plugin code.
For live-rate shipping, see [Shipping diagnostics](#shipping-diagnostics) above.

### Prerequisites (two-step enablement)

Agentic Commerce is gated twice:

1. **Developer feature flag** — `_wcstripe_feature_agentic_commerce` (default
   **off**); also filterable via `wc_stripe_is_agentic_commerce_enabled`. The
   feed, REST endpoints, and settings UI do not exist until this is on.
2. **Merchant toggle** — **Stripe settings → Agentic commerce → "Enable agentic
   commerce"** (option `wc_stripe_agentic_commerce_enabled`). Required for the
   catalog to sync.

The merchant's Stripe account must also be on API version **`2025-12-15.preview`**
or higher (the agentic webhooks use it; see `class-wc-stripe-api.php`).

### Configurator / add-on products: exclude or redirect

Configurator products carry runtime-variable pricing the static feed can't
represent, and the order-creation path rejects any line whose live price drifts
from what Stripe charged. Two opt-in toggles (both default **off**) handle them
without code, under **Stripe settings → Agentic commerce**:

| Toggle | Option key | Effect |
| --- | --- | --- |
| **Exclude products with add-ons or configurators from the feed** | `wc_stripe_agentic_commerce_auto_exclude_addons` | Detected products never enter the catalog. Use when configurator SKUs should not appear in agents at all. |
| **Redirect shoppers to my store for products with add-ons or configurators** | `wc_stripe_agentic_commerce_auto_disable_checkout_addons` | Detected products stay discoverable but agents send shoppers to the store to configure and buy (`disable_checkout=true`). Use to keep discoverability while moving the actual purchase on-site. |

Exclude wins over redirect (an excluded product is never in the feed, so its
checkout mode is moot). Both are **defaults** — a custom filter still wins.

Detection is per-product postmeta (resolved on the parent for variations).
Out of the box it covers `_product_addons`, `tm_meta_cpf_options`,
`composite_data`, and `_wc_pb_priced_individually=yes`. It deliberately does not
key off `class_exists()` of the plugins, since an active plugin says nothing
about whether a given product is configured. Extend the detected set without
forking:

```php
add_filter(
    'woocommerce_agentic_commerce_addon_detection_meta_keys',
    function ( array $meta_keys, WC_Product $product ): array {
        $meta_keys[] = '_my_configurator_options';
        return $meta_keys;
    },
    10,
    2
);
```

### Per-product redirect without the toggles

To redirect specific products regardless of the toggles, hook the
`woocommerce_agentic_commerce_disable_checkout` filter shown above. To exclude
specific products from the feed, hook `woocommerce_agentic_commerce_should_sync_product`:

```php
add_filter(
    'woocommerce_agentic_commerce_should_sync_product',
    function ( bool $should_sync, WC_Product $product ): bool {
        return get_post_meta( $product->get_id(), '_hide_from_agents', true ) ? false : $should_sync;
    },
    10,
    2
);
```

The feed preview's per-product `advisories` list flags excluded products (with
the branch that excluded them), redirect-only products (with the source), and
SKU-less products, so these decisions are self-diagnosable in WooCommerce.
