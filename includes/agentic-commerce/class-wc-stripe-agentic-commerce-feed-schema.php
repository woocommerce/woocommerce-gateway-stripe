<?php
/**
 * Stripe Agentic Commerce Feed Schema
 *
 * Defines Stripe's product catalog CSV specification.
 *
 * @package WooCommerce_Stripe
 * @since 10.5.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe Agentic Commerce Product Feed Schema Definition
 *
 * Based on https://docs.stripe.com/agentic-commerce/product-catalog
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_Feed_Schema {

	/**
	 * Cached schema to avoid rebuilding on every call.
	 *
	 * @var array|null
	 */
	private static ?array $cached_schema = null;

	/**
	 * Get the complete feed schema definition (cached).
	 *
	 * @since 10.5.0
	 * @return array Complete schema configuration.
	 */
	public static function get_schema(): array {
		if ( null !== self::$cached_schema ) {
			return self::$cached_schema;
		}

		self::$cached_schema = [
			// Basic Product Data - Required.
			'id'                        => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 100,
				'description' => 'Product/variation ID (unique SKU)',
			],
			'title'                     => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 150,
				'description' => 'Product title',
			],
			'description'               => [
				'required'    => true,
				'type'        => 'string',
				'max_length'  => 5000,
				'description' => 'Product description (plain text)',
			],
			'link'                      => [
				'required'    => true,
				'type'        => 'url',
				'description' => 'Product detail page URL',
			],
			'brand'                     => [
				'required'          => true,
				'type'              => 'string',
				'max_length'        => 70,
				'description'       => 'Product brand',
				'exempt_categories' => [ 'movies', 'books', 'music' ],
			],
			'image_link'                => [
				'required'    => true,
				'type'        => 'url',
				'description' => 'Main product image URL (min 800x800px)',
			],
			'availability'              => [
				'required'    => true,
				'type'        => 'enum',
				'values'      => [ 'in_stock', 'out_of_stock', 'preorder', 'backorder' ],
				'description' => 'Product availability status',
			],
			'price'                     => [
				'required'    => true,
				'type'        => 'price',
				'format'      => '15.00 USD',
				'description' => 'Regular price with currency',
			],

			// Conditionally Required - Product Identifiers.
			'gtin'                      => [
				'required'      => false,
				'required_when' => [ 'mpn' => '' ],
				'type'          => 'string',
				'max_length'    => 50,
				'description'   => 'Global Trade Item Number (GTIN, UPC, ISBN)',
			],
			'mpn'                       => [
				'required'      => false,
				'required_when' => [ 'gtin' => '' ],
				'type'          => 'string',
				'max_length'    => 70,
				'description'   => 'Manufacturer Part Number',
			],

			// Conditionally Required - Category (one required).
			'google_product_category'   => [
				'required'      => false,
				'required_when' => [ 'product_category' => '' ],
				'type'          => 'string',
				'description'   => 'Google Product Taxonomy ID or path',
			],
			'product_category'          => [
				'required'      => false,
				'required_when' => [ 'google_product_category' => '' ],
				'type'          => 'string',
				'description'   => 'Category path with > separators',
			],

			// Conditionally Required - Inventory.
			'inventory_quantity'        => [
				'required'      => false,
				'required_when' => [ 'inventory_not_tracked' => 'false' ],
				'type'          => 'integer',
				'description'   => 'Stock quantity (non-negative)',
			],
			'inventory_not_tracked'     => [
				'required'    => false,
				'type'        => 'boolean',
				'description' => 'True for digital/made-to-order products',
			],

			// Conditionally Required - Preorder.
			'availability_date'         => [
				'required'      => false,
				'required_when' => [ 'availability' => 'preorder' ],
				'type'          => 'date',
				'format'        => 'YYYY-MM-DD',
				'description'   => 'Available date for preorder items',
			],

			// Conditionally Required - Sale Price.
			'sale_price_effective_date' => [
				'required'      => false,
				'required_when' => [ 'sale_price' => '!empty' ],
				'type'          => 'string',
				'format'        => 'YYYY-MM-DD/YYYY-MM-DD',
				'description'   => 'Sale date range',
			],

			// Conditionally Required - Reviews.
			'product_review_rating'     => [
				'required'      => false,
				'required_when' => [ 'product_review_count' => '>0' ],
				'type'          => 'number',
				'range'         => '1-5',
				'description'   => 'Average review rating (1-5 scale)',
			],

			// Optional - Additional Images & Media.
			'additional_image_link'     => [
				'required'    => false,
				'type'        => 'string',
				'max_items'   => 10,
				'description' => 'Comma-separated image URLs (max 10)',
			],
			'video_link'                => [
				'required'    => false,
				'type'        => 'url',
				'description' => 'Product video URL (15-60 sec)',
			],
			'model_3d_link'             => [
				'required'    => false,
				'type'        => 'url',
				'description' => '3D model file (GLB or GLTF, <20MB)',
			],

			// Optional - Product Details.
			'condition'                 => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'new', 'refurbished', 'used' ],
				'description' => 'Product condition',
			],
			'age_group'                 => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'newborn', 'infant', 'toddler', 'kids', 'adult' ],
				'description' => 'Target demographic',
			],
			'material'                  => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 100,
				'description' => 'Primary material',
			],
			'length'                    => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product length with unit',
			],
			'width'                     => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product width with unit',
			],
			'height'                    => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product height with unit',
			],
			'weight'                    => [
				'required'    => false,
				'type'        => 'string_with_unit',
				'description' => 'Product weight with unit',
			],

			// Optional - Pricing.
			'sale_price'                => [
				'required'    => false,
				'type'        => 'price',
				'format'      => '12.99 USD',
				'description' => 'Discounted price with currency',
			],

			// Optional - Variants.
			'item_group_id'             => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 70,
				'description' => 'Parent product ID for variations',
			],
			'item_group_title'          => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 150,
				'description' => 'Parent product title',
			],
			'color'                     => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 100,
				'description' => 'Product color',
			],
			'size'                      => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 20,
				'description' => 'Product size',
			],
			'size_system'               => [
				'required'    => false,
				'type'        => 'string',
				'max_length'  => 2,
				'description' => 'Size system (ISO 3166 country code)',
			],
			'gender'                    => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'male', 'female', 'unisex' ],
				'description' => 'Target gender',
			],

			// Optional - Stripe Tax.
			'stripe_product_tax_code'   => [
				'required'    => false,
				'type'        => 'string',
				'format'      => 'txcd_99999999',
				'description' => 'Stripe Tax product classification',
			],
			'tax_behavior'              => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'inclusive', 'exclusive' ],
				'description' => 'Tax pricing treatment',
			],
			'applicable_fees'           => [
				'required'    => false,
				'type'        => 'string',
				'description' => 'Regional per-unit fees (colon-delimited, comma-separated)',
			],

			// Optional - Shipping.
			'shipping'                  => [
				'required'    => false,
				'type'        => 'string',
				'format'      => 'country:region:service:speed:price',
				'description' => 'Shipping options (colon-delimited, comma-separated)',
			],
			'free_shipping_threshold'   => [
				'required'    => false,
				'type'        => 'string',
				'format'      => 'country:region:service:threshold',
				'description' => 'Free shipping minimum order amount',
			],
			'shipping_cost_basis'       => [
				'required'    => false,
				'type'        => 'enum',
				'values'      => [ 'per_order', 'per_item' ],
				'description' => 'Shipping cost calculation method',
			],

			// Optional - Performance Signals.
			'popularity_score'          => [
				'required'    => false,
				'type'        => 'number',
				'range'       => '0-5',
				'description' => 'Aggregate popularity metric (0-5)',
			],
			'return_rate'               => [
				'required'    => false,
				'type'        => 'number',
				'range'       => '0-100',
				'description' => 'Return rate percentage (90-day window)',
			],
			'product_review_count'      => [
				'required'    => false,
				'type'        => 'integer',
				'description' => 'Number of verified reviews',
			],

			// Optional - Product Lifecycle.
			'expiration_date'           => [
				'required'    => false,
				'type'        => 'date',
				'format'      => 'YYYY-MM-DD',
				'description' => 'Product delisting date',
			],
			'delete'                    => [
				'required'    => false,
				'type'        => 'boolean',
				'description' => 'Mark product for permanent removal',
			],
		];

		return self::$cached_schema;
	}

	/**
	 * Get field configuration.
	 *
	 * @since 10.5.0
	 * @param string $field Field name.
	 * @return array|null Field configuration or null if not found.
	 */
	public static function get_field( string $field ): ?array {
		$schema = self::get_schema();
		return $schema[ $field ] ?? null;
	}

	/**
	 * Check if field is required.
	 *
	 * Handles both absolute requirements and conditional requirements.
	 *
	 * @since 10.5.0
	 * @param string $field Field name to check.
	 * @param array  $data  Product data for conditional checks. Not modified, passed by reference.
	 * @return bool True if field is required, false otherwise.
	 */
	public static function is_field_required( string $field, array &$data = [] ): bool {
		$field_config = self::get_field( $field );
		if ( ! $field_config ) {
			return false;
		}

		// Check absolute requirement.
		if ( isset( $field_config['required'] ) && true === $field_config['required'] ) {
			return true;
		}

		// Check conditional requirements.
		if ( isset( $field_config['required_when'] ) ) {
			foreach ( $field_config['required_when'] as $depend_field => $depend_value ) {
				$current_value = $data[ $depend_field ] ?? null;

				// Handle special conditions.
				if ( '!empty' === $depend_value && ! empty( $current_value ) ) {
					return true;
				}

				if ( '>0' === $depend_value && is_numeric( $current_value ) && $current_value > 0 ) {
					return true;
				}

				// Handle empty string condition.
				if ( '' === $depend_value && ( null === $current_value || '' === $current_value ) ) {
					return true;
				}

				// Exact value match.
				if ( $depend_value === $current_value ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get CSV column headers in correct order.
	 *
	 * Order matters for CSV output consistency.
	 *
	 * @since 10.5.0
	 * @return array CSV column headers.
	 */
	public static function get_csv_headers(): array {
		return array_keys( self::get_schema() );
	}

	/**
	 * Get required fields list.
	 *
	 * @since 10.5.0
	 * @return array List of absolutely required field names.
	 */
	public static function get_required_fields(): array {
		$required = [];
		foreach ( self::get_schema() as $field => $config ) {
			if ( isset( $config['required'] ) && true === $config['required'] ) {
				$required[] = $field;
			}
		}
		return $required;
	}

	/**
	 * Get conditionally required fields list.
	 *
	 * @since 10.5.0
	 * @return array List of conditionally required field names.
	 */
	public static function get_conditionally_required_fields(): array {
		$conditional = [];
		foreach ( self::get_schema() as $field => $config ) {
			if ( isset( $config['required_when'] ) ) {
				$conditional[] = $field;
			}
		}
		return $conditional;
	}
}
