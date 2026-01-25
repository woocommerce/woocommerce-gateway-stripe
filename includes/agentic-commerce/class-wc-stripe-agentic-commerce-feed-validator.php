<?php
/**
 * Feed Validator class for Stripe Agentic Commerce.
 *
 * @package WooCommerce_Stripe
 * @since 10.4.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedValidatorInterface;

/**
 * Validates product feed entries for Stripe Agentic Commerce.
 *
 * Ensures product data meets Stripe's requirements and format specifications.
 *
 * @since 10.4.0
 */
class WC_Stripe_Agentic_Commerce_Feed_Validator implements FeedValidatorInterface {
	/**
	 * Stripe feed schema definition.
	 *
	 * @var array
	 */
	protected array $schema;

	/**
	 * Initialize validator with schema.
	 *
	 * @since 10.4.0
	 */
	public function __construct() {
		$this->schema = WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();
	}

	/**
	 * Validate product feed entry.
	 *
	 * @since 10.4.0
	 * @param array       $row     Product data row to validate.
	 * @param \WC_Product $product Product object for context.
	 * @return array Array of validation error messages (empty if valid).
	 */
	public function validate_entry( array $row, \WC_Product $product ): array {
		$errors = [];

		// Validate required fields.
		$errors = array_merge( $errors, $this->validate_required_fields( $row ) );

		// Validate field formats.
		$errors = array_merge( $errors, $this->validate_field_formats( $row ) );

		// Validate business rules.
		$errors = array_merge( $errors, $this->validate_business_rules( $row, $product ) );

		/**
		 * Filter validation errors.
		 *
		 * @since 10.4.0
		 * @param array       $errors  Validation error messages.
		 * @param array       $row     Product data row.
		 * @param \WC_Product $product Product object.
		 */
		return apply_filters( 'wc_stripe_agentic_commerce_validation_errors', $errors, $row, $product );
	}

	/**
	 * Validate required fields are present.
	 *
	 * @since 10.4.0
	 * @param array $row Product data row.
	 * @return array Validation errors.
	 */
	protected function validate_required_fields( array $row ): array {
		$errors          = [];
		$required_fields = WC_Stripe_Agentic_Commerce_Feed_Schema::get_required_fields();

		foreach ( $required_fields as $field ) {
			if ( ! isset( $row[ $field ] ) || '' === $row[ $field ] ) {
				$errors[] = sprintf(
					/* translators: %s: field name */
					__( 'Required field "%s" is missing or empty.', 'woocommerce-gateway-stripe' ),
					$field
				);
			}
		}

		// Validate conditionally required fields.
		$errors = array_merge( $errors, $this->validate_conditional_requirements( $row ) );

		return $errors;
	}

	/**
	 * Validate conditionally required fields.
	 *
	 * @since 10.4.0
	 * @param array $row Product data row.
	 * @return array Validation errors.
	 */
	protected function validate_conditional_requirements( array $row ): array {
		$errors = [];

		// GTIN or MPN required (at least one must be present).
		if ( empty( $row['gtin'] ) && empty( $row['mpn'] ) ) {
			$errors[] = __( 'Either GTIN or MPN must be provided.', 'woocommerce-gateway-stripe' );
		}

		// Google Product Category or Product Category required (at least one).
		if ( empty( $row['google_product_category'] ) && empty( $row['product_category'] ) ) {
			$errors[] = __( 'Either google_product_category or product_category must be provided.', 'woocommerce-gateway-stripe' );
		}

		// Availability date required for preorder items.
		if ( isset( $row['availability'] ) && 'preorder' === $row['availability'] ) {
			if ( empty( $row['availability_date'] ) ) {
				$errors[] = __( 'availability_date is required when availability is "preorder".', 'woocommerce-gateway-stripe' );
			}
		}

		// Sale price effective date required when sale_price exists.
		if ( ! empty( $row['sale_price'] ) && empty( $row['sale_price_effective_date'] ) ) {
			$errors[] = __( 'sale_price_effective_date is required when sale_price is provided.', 'woocommerce-gateway-stripe' );
		}

		// Inventory quantity required when inventory is tracked.
		if ( isset( $row['inventory_not_tracked'] ) && 'false' === $row['inventory_not_tracked'] ) {
			if ( ! isset( $row['inventory_quantity'] ) ) {
				$errors[] = __( 'inventory_quantity is required when inventory_not_tracked is false.', 'woocommerce-gateway-stripe' );
			}
		}

		// Product review rating required when reviews exist.
		if ( isset( $row['product_review_count'] ) && (int) $row['product_review_count'] > 0 ) {
			if ( empty( $row['product_review_rating'] ) ) {
				$errors[] = __( 'product_review_rating is required when product_review_count > 0.', 'woocommerce-gateway-stripe' );
			}
		}

		return $errors;
	}

	/**
	 * Validate field formats.
	 *
	 * @since 10.4.0
	 * @param array $row Product data row.
	 * @return array Validation errors.
	 */
	protected function validate_field_formats( array $row ): array {
		$errors = [];

		/**
		 * Fields to check soon:
		 * - exempt_categories
		 * - format
		 * - max_items
		 * - max_length
		 * - range
		 * - values
		 */

		// Validate Stripe tax code format.
		if ( ! empty( $row['stripe_product_tax_code'] ) ) {
			if ( ! preg_match( '/^txcd_\d{8}$/', $row['stripe_product_tax_code'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: tax code value */
					__( 'Invalid stripe_product_tax_code format: "%s". Expected format: txcd_99999999', 'woocommerce-gateway-stripe' ),
					$row['stripe_product_tax_code']
				);
			}
		}

		// Validate price format.
		if ( ! empty( $row['price'] ) ) {
			if ( ! preg_match( '/^\d+\.\d{2}\s+[A-Z]{3}$/', $row['price'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: price value */
					__( 'Invalid price format: "%s". Expected format: "15.00 USD"', 'woocommerce-gateway-stripe' ),
					$row['price']
				);
			}
		}

		// Validate sale price format.
		if ( ! empty( $row['sale_price'] ) ) {
			if ( ! preg_match( '/^\d+\.\d{2}\s+[A-Z]{3}$/', $row['sale_price'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: sale price value */
					__( 'Invalid sale_price format: "%s". Expected format: "12.99 USD"', 'woocommerce-gateway-stripe' ),
					$row['sale_price']
				);
			}
		}

		// Validate availability enum.
		if ( ! empty( $row['availability'] ) ) {
			$valid_values = [ 'in_stock', 'out_of_stock', 'preorder', 'backorder' ];
			if ( ! in_array( $row['availability'], $valid_values, true ) ) {
				$errors[] = sprintf(
					/* translators: 1: availability value, 2: valid values list */
					__( 'Invalid availability value: "%1$s". Must be one of: %2$s', 'woocommerce-gateway-stripe' ),
					$row['availability'],
					implode( ', ', $valid_values )
				);
			}
		}

		// Validate tax behavior enum.
		if ( ! empty( $row['tax_behavior'] ) ) {
			$valid_values = [ 'inclusive', 'exclusive' ];
			if ( ! in_array( $row['tax_behavior'], $valid_values, true ) ) {
				$errors[] = sprintf(
					/* translators: 1: tax_behavior value, 2: valid values list */
					__( 'Invalid tax_behavior value: "%1$s". Must be one of: %2$s', 'woocommerce-gateway-stripe' ),
					$row['tax_behavior'],
					implode( ', ', $valid_values )
				);
			}
		}

		// Validate condition enum.
		if ( ! empty( $row['condition'] ) ) {
			$valid_values = [ 'new', 'refurbished', 'used' ];
			if ( ! in_array( $row['condition'], $valid_values, true ) ) {
				$errors[] = sprintf(
					/* translators: 1: condition value, 2: valid values list */
					__( 'Invalid condition value: "%1$s". Must be one of: %2$s', 'woocommerce-gateway-stripe' ),
					$row['condition'],
					implode( ', ', $valid_values )
				);
			}
		}

		// Validate gender enum.
		if ( ! empty( $row['gender'] ) ) {
			$valid_values = [ 'male', 'female', 'unisex' ];
			if ( ! in_array( $row['gender'], $valid_values, true ) ) {
				$errors[] = sprintf(
					/* translators: 1: gender value, 2: valid values list */
					__( 'Invalid gender value: "%1$s". Must be one of: %2$s', 'woocommerce-gateway-stripe' ),
					$row['gender'],
					implode( ', ', $valid_values )
				);
			}
		}

		// Validate age_group enum.
		if ( ! empty( $row['age_group'] ) ) {
			$valid_values = [ 'newborn', 'infant', 'toddler', 'kids', 'adult' ];
			if ( ! in_array( $row['age_group'], $valid_values, true ) ) {
				$errors[] = sprintf(
					/* translators: 1: age_group value, 2: valid values list */
					__( 'Invalid age_group value: "%1$s". Must be one of: %2$s', 'woocommerce-gateway-stripe' ),
					$row['age_group'],
					implode( ', ', $valid_values )
				);
			}
		}

		return $errors;
	}

	/**
	 * Validate business rules.
	 *
	 * @since 10.4.0
	 * @param array       $row     Product data row.
	 * @param \WC_Product $product Product object.
	 * @return array Validation errors.
	 */
	protected function validate_business_rules( array $row, \WC_Product $product ): array {
		$errors = [];

		// Validate sale price is less than regular price.
		if ( ! empty( $row['sale_price'] ) && ! empty( $row['price'] ) ) {
			$sale_price    = (float) preg_replace( '/[^\d.]/', '', $row['sale_price'] );
			$regular_price = (float) preg_replace( '/[^\d.]/', '', $row['price'] );

			if ( $sale_price >= $regular_price ) {
				$errors[] = sprintf(
					/* translators: 1: sale price, 2: regular price */
					__( 'Sale price (%1$s) must be less than regular price (%2$s).', 'woocommerce-gateway-stripe' ),
					$row['sale_price'],
					$row['price']
				);
			}
		}

		// Validate inventory quantity is non-negative.
		if ( isset( $row['inventory_quantity'] ) ) {
			if ( (int) $row['inventory_quantity'] < 0 ) {
				$errors[] = sprintf(
					/* translators: %d: inventory quantity */
					__( 'inventory_quantity must be non-negative, got: %d', 'woocommerce-gateway-stripe' ),
					$row['inventory_quantity']
				);
			}
		}

		// Validate product review rating is in range 1-5.
		if ( ! empty( $row['product_review_rating'] ) ) {
			$rating = (float) $row['product_review_rating'];
			if ( $rating < 1 || $rating > 5 ) {
				$errors[] = sprintf(
					/* translators: %s: rating value */
					__( 'product_review_rating must be between 1 and 5, got: %s', 'woocommerce-gateway-stripe' ),
					$row['product_review_rating']
				);
			}
		}

		// Validate return rate is percentage (0-100).
		if ( ! empty( $row['return_rate'] ) ) {
			$return_rate = (float) $row['return_rate'];
			if ( $return_rate < 0 || $return_rate > 100 ) {
				$errors[] = sprintf(
					/* translators: %s: return rate value */
					__( 'return_rate must be between 0 and 100, got: %s', 'woocommerce-gateway-stripe' ),
					$row['return_rate']
				);
			}
		}

		// Validate popularity score is in range 0-5.
		if ( ! empty( $row['popularity_score'] ) ) {
			$score = (float) $row['popularity_score'];
			if ( $score < 0 || $score > 5 ) {
				$errors[] = sprintf(
					/* translators: %s: popularity score value */
					__( 'popularity_score must be between 0 and 5, got: %s', 'woocommerce-gateway-stripe' ),
					$row['popularity_score']
				);
			}
		}

		return $errors;
	}
}
