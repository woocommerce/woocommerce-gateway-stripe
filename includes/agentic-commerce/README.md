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
