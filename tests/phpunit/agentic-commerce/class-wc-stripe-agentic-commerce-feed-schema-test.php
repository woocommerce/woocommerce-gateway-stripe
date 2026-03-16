<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Feed_Schema
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Class WC_Stripe_Agentic_Commerce_Feed_Schema_Test
 *
 * Tests the schema definition for Agentic Commerce product feeds.
 */
class WC_Stripe_Agentic_Commerce_Feed_Schema_Test extends WP_UnitTestCase {
	/**
	 * Setup test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Skip tests if WooCommerce FeedInterface is not available.
		if ( ! interface_exists( 'Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface' ) ) {
			$this->markTestSkipped( 'WooCommerce FeedInterface not available (requires WooCommerce 10.5.0+)' );
		}

		// Skip tests if Schema class is not loaded.
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Feed_Schema' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Feed_Schema class not loaded' );
		}
	}

	/**
	 * Test schema contains all required Stripe fields.
	 *
	 * @return void
	 */
	public function test_schema_contains_required_fields() {
		$schema = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		// Required fields per Stripe spec.
		$required_fields = [
			'id',
			'title',
			'description',
			'link',
			'brand',
			'image_link',
			'availability',
			'price',
		];

		foreach ( $required_fields as $field ) {
			$this->assertArrayHasKey( $field, $schema, "Schema must contain required field: {$field}" );
			$this->assertTrue( $schema[ $field ]['required'], "Field {$field} must be marked as required" );
		}
	}

	/**
	 * Test schema contains conditionally required fields.
	 *
	 * @return void
	 */
	public function test_schema_contains_conditionally_required_fields() {
		$schema = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		$conditional_fields = [
			'gtin',
			'mpn',
			'google_product_category',
			'product_category',
			'inventory_quantity',
			'availability_date',
			'sale_price_effective_date',
			'product_review_rating',
		];

		foreach ( $conditional_fields as $field ) {
			$this->assertArrayHasKey( $field, $schema, "Schema must contain conditional field: {$field}" );
			$this->assertArrayHasKey( 'required_when', $schema[ $field ], "Field {$field} must have required_when condition" );
		}
	}

	/**
	 * Test schema contains optional Stripe-specific fields.
	 *
	 * @return void
	 */
	public function test_schema_contains_stripe_specific_fields() {
		$schema = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		$stripe_fields = [
			'stripe_product_tax_code',
			'tax_behavior',
			'applicable_fees',
			'free_shipping_threshold',
		];

		foreach ( $stripe_fields as $field ) {
			$this->assertArrayHasKey( $field, $schema, "Schema must contain Stripe-specific field: {$field}" );
		}
	}

	/**
	 * Test schema contains variant fields.
	 *
	 * @return void
	 */
	public function test_schema_contains_variant_fields() {
		$schema = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		$variant_fields = [
			'item_group_id',
			'item_group_title',
			'color',
			'size',
			'gender',
		];

		foreach ( $variant_fields as $field ) {
			$this->assertArrayHasKey( $field, $schema, "Schema must contain variant field: {$field}" );
		}
	}

	/**
	 * Test availability field has correct enum values including backorder.
	 *
	 * @return void
	 */
	public function test_availability_includes_backorder() {
		$schema = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		$this->assertArrayHasKey( 'availability', $schema );
		$this->assertEquals( 'enum', $schema['availability']['type'] );
		$this->assertContains( 'backorder', $schema['availability']['values'] );
		$this->assertContains( 'in_stock', $schema['availability']['values'] );
		$this->assertContains( 'out_of_stock', $schema['availability']['values'] );
		$this->assertContains( 'preorder', $schema['availability']['values'] );
	}

	/**
	 * Test get_field method returns correct field config.
	 *
	 * @return void
	 */
	public function test_get_field_returns_config() {
		$field = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_field( 'price' );

		$this->assertIsArray( $field );
		$this->assertTrue( $field['required'] );
		$this->assertEquals( 'price', $field['type'] );
		$this->assertEquals( '15.00 USD', $field['format'] );
	}

	/**
	 * Test get_field returns null for unknown field.
	 *
	 * @return void
	 */
	public function test_get_field_returns_null_for_unknown() {
		$field = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_field( 'nonexistent_field' );

		$this->assertNull( $field );
	}

	/**
	 * Test is_field_required returns true for required fields.
	 *
	 * @return void
	 */
	public function test_is_field_required_for_required_fields() {
		$this->assertTrue( \WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'id' ) );
		$this->assertTrue( \WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'title' ) );
		$this->assertTrue( \WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'price' ) );
	}

	/**
	 * Test is_field_required returns false for optional fields.
	 *
	 * @return void
	 */
	public function test_is_field_required_for_optional_fields() {
		$this->assertFalse( \WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'color' ) );
		$this->assertFalse( \WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'size' ) );
		$this->assertFalse( \WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'weight' ) );
	}

	/**
	 * Test conditional requirement: gtin required when mpn is empty.
	 *
	 * @return void
	 */
	public function test_conditional_requirement_gtin_when_mpn_empty() {
		$data_without_mpn = [ 'mpn' => '' ];
		$data_with_mpn    = [ 'mpn' => 'ABC123' ];

		$this->assertTrue(
			\WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'gtin', $data_without_mpn ),
			'GTIN should be required when MPN is empty'
		);

		$this->assertFalse(
			\WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'gtin', $data_with_mpn ),
			'GTIN should not be required when MPN is provided'
		);
	}

	/**
	 * Test conditional requirement: availability_date required when availability is preorder.
	 *
	 * @return void
	 */
	public function test_conditional_requirement_availability_date_for_preorder() {
		$preorder_data = [ 'availability' => 'preorder' ];
		$instock_data  = [ 'availability' => 'in_stock' ];

		$this->assertTrue(
			\WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'availability_date', $preorder_data ),
			'availability_date should be required for preorder items'
		);

		$this->assertFalse(
			\WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'availability_date', $instock_data ),
			'availability_date should not be required for in_stock items'
		);
	}

	/**
	 * Test conditional requirement: sale_price_effective_date required when sale_price exists.
	 *
	 * @return void
	 */
	public function test_conditional_requirement_sale_date_when_sale_price() {
		$with_sale    = [ 'sale_price' => '10.00 USD' ];
		$without_sale = [ 'sale_price' => '' ];

		$this->assertTrue(
			\WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'sale_price_effective_date', $with_sale ),
			'sale_price_effective_date should be required when sale_price exists'
		);

		$this->assertFalse(
			\WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'sale_price_effective_date', $without_sale ),
			'sale_price_effective_date should not be required without sale_price'
		);
	}

	/**
	 * Test conditional requirement: inventory_quantity required when inventory_not_tracked is false.
	 *
	 * @return void
	 */
	public function test_conditional_requirement_inventory_quantity() {
		$tracked     = [ 'inventory_not_tracked' => 'false' ];
		$not_tracked = [ 'inventory_not_tracked' => 'true' ];

		$this->assertTrue(
			\WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'inventory_quantity', $tracked ),
			'inventory_quantity should be required when inventory is tracked'
		);

		$this->assertFalse(
			\WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'inventory_quantity', $not_tracked ),
			'inventory_quantity should not be required when inventory is not tracked'
		);
	}

	/**
	 * Test conditional requirement: product_review_rating required when review_count > 0.
	 *
	 * @return void
	 */
	public function test_conditional_requirement_rating_when_reviews_exist() {
		$with_reviews    = [ 'product_review_count' => 10 ];
		$without_reviews = [ 'product_review_count' => 0 ];

		$this->assertTrue(
			\WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'product_review_rating', $with_reviews ),
			'product_review_rating should be required when reviews exist'
		);

		$this->assertFalse(
			\WC_Stripe_Agentic_Commerce_Feed_Schema::is_field_required( 'product_review_rating', $without_reviews ),
			'product_review_rating should not be required without reviews'
		);
	}

	/**
	 * Test get_csv_headers returns all fields in order.
	 *
	 * @return void
	 */
	public function test_get_csv_headers_returns_all_fields() {
		$headers = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_csv_headers();

		$this->assertIsArray( $headers );
		$this->assertNotEmpty( $headers );
		$this->assertContains( 'id', $headers );
		$this->assertContains( 'price', $headers );
		$this->assertContains( 'availability', $headers );
	}

	/**
	 * Test CSV headers order is consistent.
	 *
	 * @return void
	 */
	public function test_csv_headers_order_is_consistent() {
		$headers1 = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_csv_headers();
		$headers2 = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_csv_headers();

		$this->assertEquals( $headers1, $headers2, 'CSV headers order must be consistent across calls' );
	}

	/**
	 * Test get_required_fields returns only absolutely required fields.
	 *
	 * @return void
	 */
	public function test_get_required_fields() {
		$required = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_required_fields();

		$this->assertIsArray( $required );
		$this->assertContains( 'id', $required );
		$this->assertContains( 'title', $required );
		$this->assertContains( 'price', $required );
		$this->assertNotContains( 'gtin', $required, 'Conditionally required fields should not be in required list' );
	}

	/**
	 * Test get_conditionally_required_fields returns fields with conditions.
	 *
	 * @return void
	 */
	public function test_get_conditionally_required_fields() {
		$conditional = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_conditionally_required_fields();

		$this->assertIsArray( $conditional );
		$this->assertContains( 'gtin', $conditional );
		$this->assertContains( 'mpn', $conditional );
		$this->assertContains( 'availability_date', $conditional );
		$this->assertNotContains( 'id', $conditional, 'Absolutely required fields should not be in conditional list' );
	}

	/**
	 * Test schema caching works correctly.
	 *
	 * @return void
	 */
	public function test_schema_is_cached() {
		$schema1 = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();
		$schema2 = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		// Same instance means caching is working.
		$this->assertSame( $schema1, $schema2, 'Schema should be cached and return same instance' );
	}

	/**
	 * Test max_length constraints are defined for string fields.
	 *
	 * @return void
	 */
	public function test_string_fields_have_max_length() {
		$schema = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		// Fields that should have max_length.
		$fields_with_length = [
			'id'               => 100,
			'title'            => 150,
			'description'      => 5000,
			'brand'            => 70,
			'mpn'              => 70,
			'item_group_id'    => 70,
			'item_group_title' => 150,
			'color'            => 100,
			'size'             => 20,
			'size_system'      => 2,
		];

		foreach ( $fields_with_length as $field => $expected_length ) {
			$this->assertArrayHasKey( 'max_length', $schema[ $field ], "Field {$field} should have max_length" );
			$this->assertEquals( $expected_length, $schema[ $field ]['max_length'], "Field {$field} should have correct max_length" );
		}
	}

	/**
	 * Test enum fields have valid values defined.
	 *
	 * @return void
	 */
	public function test_enum_fields_have_values() {
		$schema = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		$enum_fields = [
			'availability',
			'condition',
			'age_group',
			'gender',
			'tax_behavior',
			'shipping_cost_basis',
		];

		foreach ( $enum_fields as $field ) {
			$this->assertEquals( 'enum', $schema[ $field ]['type'], "Field {$field} should be enum type" );
			$this->assertArrayHasKey( 'values', $schema[ $field ], "Field {$field} should have allowed values" );
			$this->assertNotEmpty( $schema[ $field ]['values'], "Field {$field} should have non-empty values array" );
		}
	}

	/**
	 * Test tax_behavior enum has correct values.
	 *
	 * @return void
	 */
	public function test_tax_behavior_enum_values() {
		$schema = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		$this->assertArrayHasKey( 'tax_behavior', $schema );
		$this->assertEquals( [ 'inclusive', 'exclusive' ], $schema['tax_behavior']['values'] );
	}

	/**
	 * Test stripe_product_tax_code has correct format specification.
	 *
	 * @return void
	 */
	public function test_stripe_tax_code_format() {
		$schema = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		$this->assertArrayHasKey( 'stripe_product_tax_code', $schema );
		$this->assertEquals( 'txcd_99999999', $schema['stripe_product_tax_code']['format'] );
	}

	/**
	 * Test shipping_cost_basis enum has correct values.
	 *
	 * @return void
	 */
	public function test_shipping_cost_basis_enum_values() {
		$schema = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_schema();

		$this->assertArrayHasKey( 'shipping_cost_basis', $schema );
		$this->assertEquals( 'enum', $schema['shipping_cost_basis']['type'] );
		$this->assertEquals( [ 'per_order', 'per_item' ], $schema['shipping_cost_basis']['values'] );
	}
}
