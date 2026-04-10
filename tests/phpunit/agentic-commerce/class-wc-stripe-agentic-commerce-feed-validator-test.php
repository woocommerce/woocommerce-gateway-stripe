<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Feed_Validator
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Commerce_Feed_Validator_Test
 *
 * Tests the feed validator for Agentic Commerce.
 */
class WC_Stripe_Agentic_Commerce_Feed_Validator_Test extends WP_UnitTestCase {
	/**
	 * Setup test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Skip tests if WooCommerce FeedValidatorInterface is not available.
		if ( ! interface_exists( 'Automattic\\WooCommerce\\Internal\\ProductFeed\\Feed\\FeedValidatorInterface' ) ) {
			$this->markTestSkipped( 'WooCommerce FeedValidatorInterface not available (requires WooCommerce 10.5.0+)' );
		}

		// Skip tests if Validator class is not loaded.
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Feed_Validator' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Feed_Validator class not loaded' );
		}
	}

	/**
	 * Test valid entry passes validation.
	 *
	 * @return void
	 */
	public function test_valid_entry_passes() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'brand'            => 'Test Brand',
			'image_link'       => 'https://example.com/image.jpg',
			'availability'     => 'in_stock',
			'price'            => '19.99 USD',
			'gtin'             => '1234567890123',
			'product_category' => 'Test Category',
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertEmpty( $errors, 'Valid entry should have no errors' );

		$product->delete( true );
	}

	/**
	 * Test missing required field produces error.
	 *
	 * @return void
	 */
	public function test_missing_required_field() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'    => '123',
			'title' => 'Test Product',
			// Missing 'description', 'link', 'brand', 'image_link', 'availability', 'price'.
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertNotEmpty( $errors, 'Missing required fields should produce errors' );
		$this->assertGreaterThan( 1, count( $errors ), 'Should have multiple errors for missing fields' );

		$product->delete( true );
	}

	/**
	 * Test missing GTIN and MPN produces error.
	 *
	 * @return void
	 */
	public function test_missing_gtin_and_mpn() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'brand'            => 'Test Brand',
			'image_link'       => 'https://example.com/image.jpg',
			'availability'     => 'in_stock',
			'price'            => '19.99 USD',
			'product_category' => 'Test Category',
			// Missing both 'gtin' and 'mpn'.
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertContains( 'Either GTIN or MPN must be provided.', $errors );

		$product->delete( true );
	}

	/**
	 * Test missing categories produces error.
	 *
	 * @return void
	 */
	public function test_missing_both_categories() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'           => '123',
			'title'        => 'Test Product',
			'description'  => 'Test Description',
			'link'         => 'https://example.com/product',
			'brand'        => 'Test Brand',
			'image_link'   => 'https://example.com/image.jpg',
			'availability' => 'in_stock',
			'price'        => '19.99 USD',
			'gtin'         => '1234567890123',
			// Missing both 'google_product_category' and 'product_category'.
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertContains( 'Either google_product_category or product_category must be provided.', $errors );

		$product->delete( true );
	}

	/**
	 * Test preorder without availability_date produces error.
	 *
	 * @return void
	 */
	public function test_preorder_without_availability_date() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'brand'            => 'Test Brand',
			'image_link'       => 'https://example.com/image.jpg',
			'availability'     => 'preorder',
			'price'            => '19.99 USD',
			'gtin'             => '1234567890123',
			'product_category' => 'Test Category',
			// Missing 'availability_date'.
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertContains( 'availability_date is required when availability is "preorder".', $errors );

		$product->delete( true );
	}

	/**
	 * Test sale price without effective date produces error.
	 *
	 * @return void
	 */
	public function test_sale_price_without_effective_date() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'brand'            => 'Test Brand',
			'image_link'       => 'https://example.com/image.jpg',
			'availability'     => 'in_stock',
			'price'            => '19.99 USD',
			'sale_price'       => '15.99 USD',
			'gtin'             => '1234567890123',
			'product_category' => 'Test Category',
			// Missing 'sale_price_effective_date'.
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertContains( 'sale_price_effective_date is required when sale_price is provided.', $errors );

		$product->delete( true );
	}

	/**
	 * Test invalid tax code format produces error.
	 *
	 * @return void
	 */
	public function test_invalid_tax_code_format() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'                      => '123',
			'title'                   => 'Test Product',
			'description'             => 'Test Description',
			'link'                    => 'https://example.com/product',
			'brand'                   => 'Test Brand',
			'image_link'              => 'https://example.com/image.jpg',
			'availability'            => 'in_stock',
			'price'                   => '19.99 USD',
			'gtin'                    => '1234567890123',
			'product_category'        => 'Test Category',
			'stripe_product_tax_code' => 'invalid-format',
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Invalid stripe_product_tax_code format', $errors[0] );

		$product->delete( true );
	}

	/**
	 * Test valid tax code format passes.
	 *
	 * @return void
	 */
	public function test_valid_tax_code_format() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'                      => '123',
			'title'                   => 'Test Product',
			'description'             => 'Test Description',
			'link'                    => 'https://example.com/product',
			'brand'                   => 'Test Brand',
			'image_link'              => 'https://example.com/image.jpg',
			'availability'            => 'in_stock',
			'price'                   => '19.99 USD',
			'gtin'                    => '1234567890123',
			'product_category'        => 'Test Category',
			'stripe_product_tax_code' => 'txcd_12345678',
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertEmpty( $errors, 'Valid tax code should not produce errors' );

		$product->delete( true );
	}

	/**
	 * Test invalid price format produces error.
	 *
	 * @return void
	 */
	public function test_invalid_price_format() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'brand'            => 'Test Brand',
			'image_link'       => 'https://example.com/image.jpg',
			'availability'     => 'in_stock',
			'price'            => '19.99',  // Missing currency.
			'gtin'             => '1234567890123',
			'product_category' => 'Test Category',
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Invalid price format', $errors[0] );

		$product->delete( true );
	}

	/**
	 * Test invalid availability enum produces error.
	 *
	 * @return void
	 */
	public function test_invalid_availability_enum() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'               => '123',
			'title'            => 'Test Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'brand'            => 'Test Brand',
			'image_link'       => 'https://example.com/image.jpg',
			'availability'     => 'invalid_value',
			'price'            => '19.99 USD',
			'gtin'             => '1234567890123',
			'product_category' => 'Test Category',
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Invalid availability value', $errors[0] );

		$product->delete( true );
	}

	/**
	 * Test sale price greater than regular price produces error.
	 *
	 * @return void
	 */
	public function test_sale_price_greater_than_regular() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'                        => '123',
			'title'                     => 'Test Product',
			'description'               => 'Test Description',
			'link'                      => 'https://example.com/product',
			'brand'                     => 'Test Brand',
			'image_link'                => 'https://example.com/image.jpg',
			'availability'              => 'in_stock',
			'price'                     => '15.99 USD',
			'sale_price'                => '19.99 USD',
			'sale_price_effective_date' => '2026-01-01/2026-12-31',
			'gtin'                      => '1234567890123',
			'product_category'          => 'Test Category',
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Sale price', $errors[0] );
		$this->assertStringContainsString( 'must be less than', $errors[0] );

		$product->delete( true );
	}

	/**
	 * Test negative inventory quantity produces error.
	 *
	 * @return void
	 */
	public function test_negative_inventory_quantity() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'                    => '123',
			'title'                 => 'Test Product',
			'description'           => 'Test Description',
			'link'                  => 'https://example.com/product',
			'brand'                 => 'Test Brand',
			'image_link'            => 'https://example.com/image.jpg',
			'availability'          => 'in_stock',
			'price'                 => '19.99 USD',
			'gtin'                  => '1234567890123',
			'product_category'      => 'Test Category',
			'inventory_quantity'    => -5,
			'inventory_not_tracked' => 'false',
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'inventory_quantity must be non-negative', $errors[0] );

		$product->delete( true );
	}

	/**
	 * Test invalid review rating range produces error.
	 *
	 * @return void
	 */
	public function test_invalid_review_rating_range() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'                    => '123',
			'title'                 => 'Test Product',
			'description'           => 'Test Description',
			'link'                  => 'https://example.com/product',
			'brand'                 => 'Test Brand',
			'image_link'            => 'https://example.com/image.jpg',
			'availability'          => 'in_stock',
			'price'                 => '19.99 USD',
			'gtin'                  => '1234567890123',
			'product_category'      => 'Test Category',
			'product_review_rating' => 6,  // Out of range (max 5).
			'product_review_count'  => 10,
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'product_review_rating must be between 1 and 5', $errors[0] );

		$product->delete( true );
	}

	/**
	 * Test consistent variant attributes pass validation.
	 *
	 * @return void
	 */
	public function test_consistent_variant_attributes_pass() {
		$product = WC_Helper_Product::create_simple_product();

		$base_row = [
			'id'               => '101',
			'title'            => 'T-Shirt - Red Large',
			'description'      => 'A red t-shirt',
			'link'             => 'https://example.com/product',
			'brand'            => 'Test Brand',
			'image_link'       => 'https://example.com/image.jpg',
			'availability'     => 'in_stock',
			'price'            => '19.99 USD',
			'gtin'             => '1234567890123',
			'product_category' => 'Apparel',
			'item_group_id'    => '100',
			'color'            => 'Red',
			'size'             => 'Large',
		];

		$variant_2 = array_merge(
			$base_row,
			[
				'id'    => '102',
				'title' => 'T-Shirt - Blue Large',
				'color' => 'Blue',
				'size'  => 'Large',
			]
		);

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();

		$errors1 = $validator->validate_entry( $base_row, $product );
		$errors2 = $validator->validate_entry( $variant_2, $product );

		$this->assertEmpty( $errors1, 'First variant should pass' );
		$this->assertEmpty( $errors2, 'Second variant with same attributes should pass' );

		$product->delete( true );
	}

	/**
	 * Test inconsistent variant attributes produce error.
	 *
	 * @return void
	 */
	public function test_inconsistent_variant_attributes_produce_error() {
		$product = WC_Helper_Product::create_simple_product();

		$variant_1 = [
			'id'               => '101',
			'title'            => 'T-Shirt - Red Large',
			'description'      => 'A red t-shirt',
			'link'             => 'https://example.com/product',
			'brand'            => 'Test Brand',
			'image_link'       => 'https://example.com/image.jpg',
			'availability'     => 'in_stock',
			'price'            => '19.99 USD',
			'gtin'             => '1234567890123',
			'product_category' => 'Apparel',
			'item_group_id'    => '200',
			'color'            => 'Red',
			'size'             => 'Large',
		];

		// Second variant has color but NOT size - mismatched attributes.
		$variant_2 = [
			'id'               => '102',
			'title'            => 'T-Shirt - Blue',
			'description'      => 'A blue t-shirt',
			'link'             => 'https://example.com/product',
			'brand'            => 'Test Brand',
			'image_link'       => 'https://example.com/image.jpg',
			'availability'     => 'in_stock',
			'price'            => '19.99 USD',
			'gtin'             => '1234567890124',
			'product_category' => 'Apparel',
			'item_group_id'    => '200',
			'color'            => 'Blue',
			// 'size' is missing.
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();

		$errors1 = $validator->validate_entry( $variant_1, $product );
		$errors2 = $validator->validate_entry( $variant_2, $product );

		$this->assertEmpty( $errors1, 'First variant should pass' );
		$this->assertNotEmpty( $errors2, 'Second variant with different attributes should fail' );
		$this->assertStringContainsString( 'Variant attribute mismatch', $errors2[0] );
		$this->assertStringContainsString( '200', $errors2[0] );

		$product->delete( true );
	}

	/**
	 * Test non-variant products skip consistency check.
	 *
	 * @return void
	 */
	public function test_non_variant_skips_consistency_check() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'               => '123',
			'title'            => 'Simple Product',
			'description'      => 'Test Description',
			'link'             => 'https://example.com/product',
			'brand'            => 'Test Brand',
			'image_link'       => 'https://example.com/image.jpg',
			'availability'     => 'in_stock',
			'price'            => '19.99 USD',
			'gtin'             => '1234567890123',
			'product_category' => 'Test Category',
			'color'            => 'Red',
			// No item_group_id - not a variant.
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertEmpty( $errors, 'Non-variant product should not trigger consistency check' );

		$product->delete( true );
	}

	/**
	 * Test invalid shipping_cost_basis enum produces error.
	 *
	 * @return void
	 */
	public function test_invalid_shipping_cost_basis_enum() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'                  => '123',
			'title'               => 'Test Product',
			'description'         => 'Test Description',
			'link'                => 'https://example.com/product',
			'brand'               => 'Test Brand',
			'image_link'          => 'https://example.com/image.jpg',
			'availability'        => 'in_stock',
			'price'               => '19.99 USD',
			'gtin'                => '1234567890123',
			'product_category'    => 'Test Category',
			'shipping_cost_basis' => 'invalid_value',
		];

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Invalid shipping_cost_basis value', $errors[0] );

		$product->delete( true );
	}

	/**
	 * Test validation errors can be filtered.
	 *
	 * @return void
	 */
	public function test_validation_errors_filter() {
		$product = WC_Helper_Product::create_simple_product();

		$row = [
			'id'    => '123',
			'title' => 'Test Product',
			// Missing required fields.
		];

		add_filter(
			'wc_stripe_agentic_commerce_validation_errors',
			function ( $errors ) {
				$errors[] = 'Custom validation error';
				return $errors;
			}
		);

		$validator = new \WC_Stripe_Agentic_Commerce_Feed_Validator();
		$errors    = $validator->validate_entry( $row, $product );

		$this->assertContains( 'Custom validation error', $errors );

		remove_all_filters( 'wc_stripe_agentic_commerce_validation_errors' );

		$product->delete( true );
	}
}
