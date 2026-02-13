<?php
/**
 * Product Mapper class for Stripe Agentic Commerce.
 *
 * @package WooCommerce_Stripe
 * @since 10.5.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Enums\ProductStockStatus;
use Automattic\WooCommerce\Enums\ProductType;
use Automattic\WooCommerce\Internal\ProductFeed\Feed\ProductMapperInterface;
use Automattic\WooCommerce\Internal\ProductFeed\Utils\StringHelper;

/**
 * Maps WooCommerce products to Stripe Agentic Commerce feed format.
 *
 * Converts WooCommerce product data into Stripe Product Catalog CSV specification.
 * Uses schema-driven approach to ensure all required fields are mapped correctly.
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_Product_Mapper implements ProductMapperInterface {
	/**
	 * Stripe feed schema definition.
	 *
	 * @var array
	 */
	protected array $schema;

	/**
	 * Cached shipping data to prevent repeated queries.
	 *
	 * @var string|null
	 */
	private ?string $cached_shipping_data = null;

	/**
	 * Cached shipping zones to prevent repeated API calls.
	 *
	 * @var array|null
	 */
	private ?array $cached_shipping_zones = null;

	/**
	 * Initialize mapper with schema.
	 *
	 * @since 10.5.0
	 */
	public function __construct() {
		$this->schema = WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();
	}

	/**
	 * Map WooCommerce product to feed row.
	 *
	 * Main entry point for converting a WooCommerce product into Stripe feed format.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product to map.
	 * @return array Mapped product data array.
	 * @throws RuntimeException If the parent product is not found.
	 */
	public function map_product( \WC_Product $product ): array {
		$row = [];

		$parent_product = null;
		if ( ProductType::VARIATION === $product->get_type() ) {
			$parent_product = wc_get_product( $product->get_parent_id() );
			if ( ! $parent_product ) {
				throw new RuntimeException(
					esc_html(
						sprintf(
							/* translators: %s: product ID */
							__( 'Parent product not found for variation: %s', 'woocommerce-gateway-stripe' ),
							$product->get_id()
						)
					)
				);
			}
		}

		foreach ( $this->schema as $field => $config ) {
			$row[ $field ] = $this->map_field( $product, $field, $config, $parent_product );
		}

		/**
		 * Filter mapped product data before validation.
		 *
		 * @since 10.5.0
		 * @param array            $row             Mapped product data.
		 * @param \WC_Product      $product         Product object.
		 * @param \WC_Product|null $parent_product  Parent product for variations.
		 */
		return apply_filters( 'wc_stripe_agentic_commerce_map_product', $row, $product, $parent_product );
	}

	/**
	 * Map individual field based on configuration.
	 *
	 * @since 10.5.0
	 * @param \WC_Product      $product        Product object.
	 * @param string           $field          Field name to map.
	 * @param array            $config         Field configuration from schema.
	 * @param \WC_Product|null $parent_product Parent product for variations.
	 * @return mixed Mapped field value.
	 */
	protected function map_field( \WC_Product $product, string $field, array $config, ?\WC_Product $parent_product = null ) {
		$mapper_method = 'get_' . $field;

		if ( method_exists( $this, $mapper_method ) ) {
			$value = $this->$mapper_method( $product, $parent_product );
		} else {
			$value = null;
		}

		if ( empty( $value ) && isset( $config['default'] ) ) {
			$value = $config['default'];
		}

		return $this->convert_type( $value, $config );
	}

	/**
	 * Convert value to appropriate type.
	 *
	 * @since 10.5.0
	 * @param mixed $value  Value to convert.
	 * @param array $config Field configuration.
	 * @return mixed Converted value.
	 */
	protected function convert_type( $value, array $config ) {
		if ( null === $value || '' === $value ) {
			return $value;
		}

		switch ( $config['type'] ) {
			case 'integer':
				return (int) $value;

			case 'string':
				$value = (string) $value;
				if ( isset( $config['max_length'] ) ) {
					$value = StringHelper::truncate( $value, $config['max_length'] );
				}
				return $value;

			case 'boolean':
				return $value ? 'true' : 'false';

			default:
				return $value;
		}
	}

	/**
	 * Get product ID.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string Product ID as string.
	 */
	protected function get_id( \WC_Product $product ): string {
		return (string) $product->get_id();
	}

	/**
	 * Get product title.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string Product title with HTML tags stripped.
	 */
	protected function get_title( \WC_Product $product ): string {
		return wp_strip_all_tags( $product->get_name() );
	}

	/**
	 * Get product description.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string Product description with HTML tags stripped.
	 */
	protected function get_description( \WC_Product $product ): string {
		$description = $product->get_description() ? $product->get_description() : $product->get_short_description();
		$description = wp_strip_all_tags( $description );

		// If no description, use the product title as fallback.
		if ( empty( $description ) ) {
			$description = wp_strip_all_tags( $product->get_name() );
		}

		return $description;
	}

	/**
	 * Get product permalink.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string Product permalink URL.
	 */
	protected function get_link( \WC_Product $product ): string {
		return $product->get_permalink();
	}

	/**
	 * Get product GTIN.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product GTIN or null.
	 */
	protected function get_gtin( \WC_Product $product ): ?string {
		$override = $product->get_meta( '_gtin' );
		if ( empty( $override ) ) {
			return $product->get_global_unique_id();
		}
		return $override;
	}

	/**
	 * Get product MPN.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product MPN or null.
	 */
	protected function get_mpn( \WC_Product $product ): ?string {
		$mpn = $product->get_meta( '_mpn' );
		if ( $mpn ) {
			return $mpn;
		}

		if ( ! $product->get_global_unique_id() ) {
			return $this->generate_mpn( $product );
		}

		return null;
	}

	/**
	 * Generate MPN using product ID and trimmed product name.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string Generated MPN.
	 */
	private function generate_mpn( \WC_Product $product ): string {
		$product_id   = $product->get_id();
		$product_name = trim( wp_strip_all_tags( $product->get_name() ) );

		$hash_input = $product_id . '_' . $product_name;
		$hash       = hash( 'crc32', $hash_input );

		return 'MPN-' . str_pad( $hash, 8, '0', STR_PAD_LEFT );
	}

	/**
	 * Get product category path.
	 *
	 * Returns the deepest (most specific) category path with hierarchical structure using " > " separator.
	 * When a product has multiple categories, selects the one with the most levels.
	 * Example: "Apparel & Accessories > Shoes > Running Shoes"
	 *
	 * @since 10.5.0
	 * @param \WC_Product      $product        Product object.
	 * @param \WC_Product|null $parent_product Parent product for variations.
	 * @return string|null Product category path or null.
	 */
	protected function get_product_category( \WC_Product $product, ?\WC_Product $parent_product ): ?string {
		$check_product = $parent_product ?? $product;

		// Find the deepest category by counting ancestors.
		$category_deepest_id  = null;
		$ancestor_deepest_ids = [];
		$max_depth            = -1;

		foreach ( $check_product->get_category_ids() as $category_id ) {
			$ancestor_ids = get_ancestors( $category_id, 'product_cat', 'taxonomy' );
			$depth        = count( $ancestor_ids );

			if ( $depth > $max_depth ) {
				$max_depth            = $depth;
				$category_deepest_id  = $category_id;
				$ancestor_deepest_ids = $ancestor_ids;
			}
		}

		if ( null === $category_deepest_id ) {
			return null;
		}

		// Build up the ID list with correct hierarchical order.
		$ordered_ids   = array_reverse( $ancestor_deepest_ids );
		$ordered_ids[] = $category_deepest_id;

		// Get all ordered category names, and concatenate them.
		$ordered_names = [];
		foreach ( $ordered_ids as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$ordered_names[ $term_id ] = $term->name;
			}
		}

		return empty( $ordered_names ) ? null : implode( ' > ', $ordered_names );
	}

	/**
	 * Get product brand.
	 *
	 * @since 10.5.0
	 * @param \WC_Product      $product        Product object.
	 * @param \WC_Product|null $parent_product Parent product for fallback.
	 * @return string|null Product brand or Generic.
	 */
	protected function get_brand( \WC_Product $product, ?\WC_Product $parent_product = null ): ?string {
		$brand = $product->get_attribute( 'pa_brand' );
		if ( ! $brand && $parent_product ) {
			$brand = $parent_product->get_attribute( 'pa_brand' );
		}

		// Try product_brand taxonomy if it exists.
		if ( ! $brand && taxonomy_exists( 'product_brand' ) ) {
			$terms = get_the_terms( $product->get_id(), 'product_brand' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$brand = $terms[0]->name;
			}
		}

		return $brand ? $brand : 'Generic';
	}

	/**
	 * Get product material.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product material or null.
	 */
	protected function get_material( \WC_Product $product ): ?string {
		return $this->get_attribute_or_return_null( $product, 'pa_material' );
	}

	/**
	 * Get product condition.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string Product condition, defaults to 'new'.
	 */
	protected function get_condition( \WC_Product $product ): string {
		$condition = $product->get_meta( '_condition' );
		return empty( $condition ) ? 'new' : $condition;
	}

	/**
	 * Get product age group.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product age group or null.
	 */
	protected function get_age_group( \WC_Product $product ): ?string {
		return $this->get_attribute_or_return_null( $product, 'pa_age_group' );
	}

	/**
	 * Get product weight with unit.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product weight with unit or null.
	 */
	protected function get_weight( \WC_Product $product ): ?string {
		$weight = $product->get_weight();
		if ( ! $weight ) {
			return null;
		}

		$unit = $this->get_weight_unit();
		return $weight . ' ' . $unit;
	}

	/**
	 * Get product length with unit.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product length with unit or null.
	 */
	protected function get_length( \WC_Product $product ): ?string {
		return $this->format_dimension( $product->get_length() );
	}

	/**
	 * Get product width with unit.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product width with unit or null.
	 */
	protected function get_width( \WC_Product $product ): ?string {
		return $this->format_dimension( $product->get_width() );
	}

	/**
	 * Get product height with unit.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product height with unit or null.
	 */
	protected function get_height( \WC_Product $product ): ?string {
		return $this->format_dimension( $product->get_height() );
	}

	/**
	 * Get product main image link.
	 *
	 * @since 10.5.0
	 * @param \WC_Product      $product        Product object.
	 * @param \WC_Product|null $parent_product Parent product for fallback.
	 * @return string Product image URL.
	 */
	protected function get_image_link( \WC_Product $product, ?\WC_Product $parent_product ): string {
		return $this->get_main_image( $product, $parent_product );
	}

	/**
	 * Get product additional image links.
	 *
	 * @since 10.5.0
	 * @param \WC_Product      $product        Product object.
	 * @param \WC_Product|null $parent_product Parent product for fallback.
	 * @return string|null Comma-separated product gallery image URLs.
	 */
	protected function get_additional_image_link( \WC_Product $product, ?\WC_Product $parent_product ): ?string {
		$gallery_ids = $product->get_gallery_image_ids();
		if ( empty( $gallery_ids ) && $parent_product ) {
			$gallery_ids = $parent_product->get_gallery_image_ids();
		}

		$urls = array_filter( array_map( 'wp_get_attachment_url', $gallery_ids ) );

		// Limit to 10 images per Stripe spec.
		$urls = array_slice( $urls, 0, 10 );

		return empty( $urls ) ? null : implode( ',', $urls );
	}

	/**
	 * Get product price with currency.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product price or null.
	 */
	protected function get_price( \WC_Product $product ): ?string {
		return $this->format_price( $product->get_regular_price(), $this->get_currency_code() );
	}

	/**
	 * Get product sale price with currency.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product sale price or null.
	 */
	protected function get_sale_price( \WC_Product $product ): ?string {
		return $this->format_price( $product->get_sale_price(), $this->get_currency_code() );
	}

	/**
	 * Get product sale price effective date.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product sale price effective date or null.
	 */
	protected function get_sale_price_effective_date( \WC_Product $product ): ?string {
		return $this->get_sale_date_range( $product );
	}

	/**
	 * Get product availability status.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string Product availability status.
	 */
	protected function get_availability( \WC_Product $product ): string {
		$stock_status = $product->get_stock_status();

		switch ( $stock_status ) {
			case ProductStockStatus::IN_STOCK:
				return 'in_stock';
			case ProductStockStatus::OUT_OF_STOCK:
				return 'out_of_stock';
			case ProductStockStatus::ON_BACKORDER:
				return 'backorder';
			default:
				return 'out_of_stock';
		}
	}

	/**
	 * Get product inventory quantity.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return int|null Product inventory quantity or null.
	 */
	protected function get_inventory_quantity( \WC_Product $product ): ?int {
		// When stock is not tracked, inventory_quantity must be blank per Stripe spec.
		if ( ! $product->managing_stock() ) {
			return null;
		}

		return $product->get_stock_quantity();
	}

	/**
	 * Get inventory_not_tracked flag.
	 *
	 * Returns 'true' for products without stock management (virtual, digital, etc.)
	 * and 'false' for products that track inventory.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return bool Whether inventory is not tracked.
	 */
	protected function get_inventory_not_tracked( \WC_Product $product ): bool {
		return ! $product->managing_stock();
	}

	/**
	 * Get product item group ID.
	 *
	 * @since 10.5.0
	 * @param \WC_Product      $product        Product object.
	 * @param \WC_Product|null $parent_product Parent product for group ID.
	 * @return string|null Parent product ID or null.
	 */
	protected function get_item_group_id( \WC_Product $product, ?\WC_Product $parent_product = null ): ?string {
		if ( ! $parent_product ) {
			return null;
		}
		return (string) $parent_product->get_id();
	}

	/**
	 * Get product item group title.
	 *
	 * @since 10.5.0
	 * @param \WC_Product      $product        Product object.
	 * @param \WC_Product|null $parent_product Parent product for title.
	 * @return string|null Parent product title or null.
	 */
	protected function get_item_group_title( \WC_Product $product, ?\WC_Product $parent_product = null ): ?string {
		return $parent_product ? wp_strip_all_tags( $parent_product->get_name() ) : null;
	}

	/**
	 * Get product color.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product color or null.
	 */
	protected function get_color( \WC_Product $product ): ?string {
		return $this->get_attribute_or_return_null( $product, 'pa_color' );
	}

	/**
	 * Get product size.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product size or null.
	 */
	protected function get_size( \WC_Product $product ): ?string {
		return $this->get_attribute_or_return_null( $product, 'pa_size' );
	}

	/**
	 * Get product size system.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product size system or null.
	 */
	protected function get_size_system( \WC_Product $product ): ?string {
		return $this->get_attribute_or_return_null( $product, 'pa_size_system' );
	}

	/**
	 * Get product gender.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Product gender or null.
	 */
	protected function get_gender( \WC_Product $product ): ?string {
		return $this->get_attribute_or_return_null( $product, 'pa_gender' );
	}

	/**
	 * Get Stripe product tax code.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Stripe tax code or null.
	 */
	protected function get_stripe_product_tax_code( \WC_Product $product ): ?string {
		$tax_class = $product->get_tax_class();

		/**
		 * Filter to map WooCommerce tax class to Stripe tax code.
		 *
		 * @since 10.5.0
		 * @param string|null $tax_code  Stripe tax code (format: txcd_99999999) or null.
		 * @param string      $tax_class WooCommerce tax class.
		 * @param \WC_Product $product   Product object.
		 */
		return apply_filters( 'wc_stripe_agentic_commerce_tax_code', null, $tax_class, $product );
	}

	/**
	 * Get tax behavior (inclusive/exclusive).
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Tax behavior or null.
	 */
	protected function get_tax_behavior( \WC_Product $product ): ?string {
		$prices_include_tax = get_option( 'woocommerce_prices_include_tax' );
		return 'yes' === $prices_include_tax ? 'inclusive' : 'exclusive';
	}

	/**
	 * Get product review count.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return int|null Number of reviews or null.
	 */
	protected function get_product_review_count( \WC_Product $product ): ?int {
		$count = $product->get_review_count();
		return $count > 0 ? $count : null;
	}

	/**
	 * Get product review rating.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return float|null Average rating (1-5 scale) or null.
	 */
	protected function get_product_review_rating( \WC_Product $product ): ?float {
		$rating = $product->get_average_rating();
		return $rating > 0 ? (float) $rating : null;
	}

	/**
	 * Get delete flag.
	 *
	 * Always returns null for products being included in the feed.
	 * This field is used to mark products for removal from Stripe's catalog.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return null Always null for active products.
	 */
	protected function get_delete( \WC_Product $product ): ?string {
		return null;
	}

	/**
	 * Get shipping options.
	 *
	 * Format: country:region:service:speed:price
	 * Multiple entries separated by commas.
	 *
	 * @since 10.5.0
	 * @return string|null Shipping data string or null.
	 */
	protected function get_shipping(): ?string {
		if ( null !== $this->cached_shipping_data ) {
			return $this->cached_shipping_data;
		}

		$shipping_data = [];
		$zones         = $this->get_cached_shipping_zones();
		$currency      = $this->get_currency_code();

		foreach ( $zones as $zone ) {
			$locations = $zone['zone_locations'];

			foreach ( $zone['shipping_methods'] as $method ) {
				// Escape colons in method title.
				$method_title = str_replace( ':', '\:', trim( $method->get_title(), ':' ) );

				// Generate the price.
				$price = '';
				if ( 'free_shipping' === $method->id ) {
					$price = '0.00';
				} elseif ( isset( $method->cost ) && is_numeric( $method->cost ) ) {
					$price = number_format( (float) $method->cost, 2, '.', '' );
				}

				if ( '' === $price ) {
					continue;
				}

				foreach ( $locations as $location ) {
					$country = null;
					$region  = null;
					$speed   = ''; // TODO: Extract from method settings if available.

					switch ( $location->type ) {
						case 'country':
							$country = $location->code;
							break;
						case 'state':
							list( $country, $region ) = array_pad( explode( ':', $location->code ), 2, '' );
							break;
						case 'continent':
							$country = $location->code;
							break;
						case 'not_covered':
							$country = '';
							break;
					}

					if ( null === $country ) {
						continue;
					}

					// Stripe spec requires format: country:region:service:speed:price
					// All 5 parts must be present, even if region/speed is empty.
					$parts = [
						trim( $country, ':' ),
						trim( $region ?? '', ':' ),
						$method_title,
						$speed,
						sprintf( '%s %s', $price, $currency ),
					];

					$shipping_data[] = implode( ':', $parts );
				}
			}
		}

		$shipping_data              = array_values( array_unique( $shipping_data ) );
		$this->cached_shipping_data = empty( $shipping_data ) ? null : implode( ',', $shipping_data );

		return $this->cached_shipping_data;
	}

	/**
	 * Get attribute or return null.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product   Product object.
	 * @param string      $attribute Attribute name.
	 * @return string|null Attribute value or null.
	 */
	private function get_attribute_or_return_null( \WC_Product $product, string $attribute ): ?string {
		$value = $product->get_attribute( $attribute );
		return $value ? (string) $value : null;
	}

	/**
	 * Get weight unit.
	 *
	 * @since 10.5.0
	 * @return string Weight unit.
	 */
	private function get_weight_unit(): string {
		static $cached;
		if ( ! isset( $cached ) ) {
			$wc_unit = get_option( 'woocommerce_weight_unit' );

			// Stripe accepts: lb, oz, g, kg. WooCommerce uses 'lbs' for pounds.
			$unit_map = [
				'lbs' => 'lb',
				'lb'  => 'lb',
				'oz'  => 'oz',
				'g'   => 'g',
				'kg'  => 'kg',
			];

			$cached = $unit_map[ $wc_unit ] ?? $wc_unit;
		}
		return $cached;
	}

	/**
	 * Format dimension with unit.
	 *
	 * @since 10.5.0
	 * @param string|null $dimension Dimension value.
	 * @return string|null Formatted dimension or null.
	 */
	private function format_dimension( ?string $dimension ): ?string {
		if ( ! $dimension ) {
			return null;
		}

		return $dimension . ' ' . $this->get_dimension_unit();
	}

	/**
	 * Get dimension unit.
	 *
	 * @since 10.5.0
	 * @return string Dimension unit.
	 */
	private function get_dimension_unit(): string {
		static $cached;
		if ( ! isset( $cached ) ) {
			$cached = get_option( 'woocommerce_dimension_unit' );
		}
		return $cached;
	}

	/**
	 * Get main product image.
	 *
	 * @since 10.5.0
	 * @param \WC_Product      $product        Product object.
	 * @param \WC_Product|null $parent_product Parent product for fallback.
	 * @return string Product image URL or empty string.
	 */
	private function get_main_image( \WC_Product $product, ?\WC_Product $parent_product ): string {
		$image_id = $product->get_image_id();
		if ( ! $image_id && $parent_product ) {
			$image_id = $parent_product->get_image_id();
		}

		if ( $image_id ) {
			$url = wp_get_attachment_url( (int) $image_id );
			if ( $url ) {
				return $url;
			}
		}

		// Return WooCommerce placeholder image as fallback.
		return wc_placeholder_img_src();
	}

	/**
	 * Get currency code.
	 *
	 * @since 10.5.0
	 * @return string Currency code.
	 */
	private function get_currency_code(): string {
		static $cached;
		if ( ! isset( $cached ) ) {
			$cached = get_woocommerce_currency();
		}
		return $cached;
	}

	/**
	 * Format price with currency.
	 *
	 * @since 10.5.0
	 * @param string|null $price    Price value.
	 * @param string      $currency Currency code.
	 * @return string|null Formatted price or null.
	 */
	private function format_price( ?string $price, string $currency ): ?string {
		if ( null === $price || '' === $price ) {
			return null;
		}

		return sprintf( '%s %s', number_format( (float) $price, 2, '.', '' ), $currency );
	}

	/**
	 * Get sale date range.
	 *
	 * @since 10.5.0
	 * @param \WC_Product $product Product object.
	 * @return string|null Sale date range or null.
	 */
	private function get_sale_date_range( \WC_Product $product ): ?string {
		$sale_price = $product->get_sale_price();
		if ( ! $sale_price ) {
			return null;
		}

		$sale_from = $product->get_date_on_sale_from();
		$sale_to   = $product->get_date_on_sale_to();

		if ( ! $sale_from ) {
			$sale_from = new \WC_DateTime();
		}

		if ( ! $sale_to ) {
			$now       = new \WC_DateTime();
			$base_date = max( $sale_from, $now );
			$sale_to   = clone $base_date;
			$sale_to->modify( '+30 days' );
		}

		return $sale_from->date_i18n( 'Y-m-d' ) . '/' . $sale_to->date_i18n( 'Y-m-d' );
	}

	/**
	 * Get cached shipping zones (prevents repeated API calls).
	 *
	 * @since 10.5.0
	 * @return array Shipping zones.
	 */
	private function get_cached_shipping_zones(): array {
		if ( null !== $this->cached_shipping_zones ) {
			return $this->cached_shipping_zones;
		}

		// Get the main zones.
		$this->cached_shipping_zones = \WC_Shipping_Zones::get_zones();

		if ( ! empty( $this->cached_shipping_zones ) ) {
			return $this->cached_shipping_zones;
		}

		// There is the "Locations not covered by other zones" zone.
		$generic_zone = \WC_Shipping_Zones::get_zone( 0 );
		if ( ! $generic_zone || is_wp_error( $generic_zone ) || ! ( $generic_zone instanceof \WC_Shipping_Zone ) ) {
			$this->cached_shipping_zones = [];
			return $this->cached_shipping_zones;
		}

		$this->cached_shipping_zones = [
			[
				'zone_locations'   => [
					(object) [
						'type' => 'not_covered',
						'code' => '',
					],
				],
				'shipping_methods' => $generic_zone->get_shipping_methods(),
			],
		];

		return $this->cached_shipping_zones;
	}
}
