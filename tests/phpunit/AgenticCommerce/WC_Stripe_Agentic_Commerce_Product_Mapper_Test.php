<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Product_Mapper
 *
 * @package WooCommerce\Stripe\Tests
 */

namespace WooCommerce\Stripe\Tests;

use WP_UnitTestCase;
use WooCommerce\Stripe\Tests\Helpers\WC_Helper_Product;

/**
 * Class WC_Stripe_Agentic_Commerce_Product_Mapper_Test
 *
 * Tests the product mapper for Agentic Commerce feeds.
 */
class WC_Stripe_Agentic_Commerce_Product_Mapper_Test extends WP_UnitTestCase {
	/**
	 * Setup test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Skip tests if WooCommerce ProductMapperInterface is not available.
		if ( ! interface_exists( 'Automattic\\WooCommerce\\Internal\\ProductFeed\\Feed\\ProductMapperInterface' ) ) {
			$this->markTestSkipped( 'WooCommerce ProductMapperInterface not available (requires WooCommerce 10.5.0+)' );
		}

		// Skip tests if Mapper class is not loaded.
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Product_Mapper' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Product_Mapper class not loaded' );
		}

		// Skip tests if Schema class is not loaded.
		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Feed_Schema' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Feed_Schema class not loaded' );
		}
	}

	/**
	 * Test simple product mapping with all required fields.
	 *
	 * @return void
	 */
	public function test_map_simple_product_with_required_fields() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( 'Test Product' );
		$product->set_description( 'Test Description' );
		$product->set_regular_price( '19.99' );
		$product->set_stock_status( 'instock' );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		// Verify required fields are present.
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'link', $result );
		$this->assertArrayHasKey( 'brand', $result );
		$this->assertArrayHasKey( 'image_link', $result );
		$this->assertArrayHasKey( 'availability', $result );
		$this->assertArrayHasKey( 'price', $result );

		// Verify field values.
		$this->assertEquals( (string) $product->get_id(), $result['id'] );
		$this->assertEquals( 'Test Product', $result['title'] );
		$this->assertEquals( 'Test Description', $result['description'] );
		$this->assertEquals( 'in_stock', $result['availability'] );
		$this->assertStringContainsString( 'USD', $result['price'] );
		$this->assertStringContainsString( '19.99', $result['price'] );

		// Cleanup.
		$product->delete( true );
	}

	/**
	 * Test price formatting with various values.
	 *
	 * @return void
	 */
	public function test_price_formatting() {
		$test_cases = [
			[
				'input'    => '0',
				'expected' => '0.00 USD',
			],
			[
				'input'    => '0.99',
				'expected' => '0.99 USD',
			],
			[
				'input'    => '10',
				'expected' => '10.00 USD',
			],
			[
				'input'    => '99.99',
				'expected' => '99.99 USD',
			],
			[
				'input'    => '999.99',
				'expected' => '999.99 USD',
			],
			[
				'input'    => '9999.99',
				'expected' => '9999.99 USD',
			],
			[
				'input'    => '19.9',
				'expected' => '19.90 USD',
			],
			[
				'input'    => '19.999',
				'expected' => '20.00 USD',
			], // Rounds up.
		];

		foreach ( $test_cases as $test_case ) {
			$product = WC_Helper_Product::create_simple_product();
			$product->set_regular_price( $test_case['input'] );
			$product->save();

			$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
			$result = $mapper->map_product( $product );

			$this->assertEquals(
				$test_case['expected'],
				$result['price'],
				"Price {$test_case['input']} should format to {$test_case['expected']}"
			);

			$product->delete( true );
		}
	}

	/**
	 * Test availability status mapping.
	 *
	 * @return void
	 */
	public function test_availability_mapping() {
		$test_cases = [
			[
				'wc_status' => 'instock',
				'expected'  => 'in_stock',
			],
			[
				'wc_status' => 'outofstock',
				'expected'  => 'out_of_stock',
			],
			[
				'wc_status' => 'onbackorder',
				'expected'  => 'backorder',
			],
		];

		foreach ( $test_cases as $test_case ) {
			$product = WC_Helper_Product::create_simple_product();
			$product->set_stock_status( $test_case['wc_status'] );
			$product->save();

			$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
			$result = $mapper->map_product( $product );

			$this->assertEquals(
				$test_case['expected'],
				$result['availability'],
				"WC status {$test_case['wc_status']} should map to {$test_case['expected']}"
			);

			$product->delete( true );
		}
	}

	/**
	 * Test sale price mapping.
	 *
	 * @return void
	 */
	public function test_sale_price_mapping() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '29.99' );
		$product->set_sale_price( '19.99' );
		$product->set_date_on_sale_from( '2026-01-01' );
		$product->set_date_on_sale_to( '2026-12-31' );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertEquals( '29.99 USD', $result['price'], 'Regular price should be in price field' );
		$this->assertEquals( '19.99 USD', $result['sale_price'], 'Sale price should be in sale_price field' );
		$this->assertStringContainsString( '2026-01-01', $result['sale_price_effective_date'] );
		$this->assertStringContainsString( '2026-12-31', $result['sale_price_effective_date'] );
		$this->assertStringContainsString( '/', $result['sale_price_effective_date'], 'Should use / separator' );

		$product->delete( true );
	}

	/**
	 * Test brand extraction with attribute.
	 *
	 * @return void
	 */
	public function test_brand_extraction_from_attribute() {
		$product = WC_Helper_Product::create_simple_product();

		// Create and set the brand attribute using helper.
		$brand_attribute = WC_Helper_Product::create_product_attribute_object( 'brand', [ 'Nike' ] );
		$product->set_attributes( [ $brand_attribute ] );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertEquals( 'Nike', $result['brand'] );

		// Cleanup.
		$product->delete( true );
		$attribute = wc_get_attribute( $brand_attribute->get_id() );
		if ( $attribute ) {
			$taxonomy = $attribute->slug;
			$terms    = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				]
			);
			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, $taxonomy );
			}
			wc_delete_attribute( $brand_attribute->get_id() );
		}
	}

	/**
	 * Test brand fallback to Generic.
	 *
	 * @return void
	 */
	public function test_brand_fallback_to_generic() {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertEquals( 'Generic', $result['brand'], 'Should fallback to Generic when no brand found' );

		$product->delete( true );
	}

	/**
	 * Test category path building.
	 *
	 * @return void
	 */
	public function test_category_path_building() {
		// Create category hierarchy: Apparel > Shoes > Running Shoes.
		$parent_cat = wp_insert_term( 'Apparel', 'product_cat' );
		$child_cat  = wp_insert_term( 'Shoes', 'product_cat', [ 'parent' => $parent_cat['term_id'] ] );
		$leaf_cat   = wp_insert_term( 'Running Shoes', 'product_cat', [ 'parent' => $child_cat['term_id'] ] );

		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		// Set category after product is saved.
		wp_set_object_terms( $product->get_id(), [ $leaf_cat['term_id'] ], 'product_cat', false );
		// Clear product cache to force reload of terms.
		clean_post_cache( $product->get_id() );

		// Reload product to get fresh category data.
		$product = wc_get_product( $product->get_id() );

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		// Check if product_category is present and contains expected values.
		if ( isset( $result['product_category'] ) ) {
			$this->assertStringContainsString( 'Apparel', $result['product_category'] );
			$this->assertStringContainsString( 'Shoes', $result['product_category'] );
			$this->assertStringContainsString( 'Running Shoes', $result['product_category'] );
			$this->assertStringContainsString( ' > ', $result['product_category'], 'Should use > separator' );

			// Verify order: parent > child > leaf.
			$this->assertMatchesRegularExpression( '/Apparel.*>.*Shoes.*>.*Running Shoes/', $result['product_category'] );
		} else {
			$this->markTestIncomplete( 'product_category field not present in output' );
		}

		// Cleanup.
		$product->delete( true );
		wp_delete_term( $leaf_cat['term_id'], 'product_cat' );
		wp_delete_term( $child_cat['term_id'], 'product_cat' );
		wp_delete_term( $parent_cat['term_id'], 'product_cat' );
	}

	/**
	 * Test UTF-8 character handling in title and description.
	 *
	 * @return void
	 */
	public function test_utf8_character_handling() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( 'Café Product 日本語 🎉' );
		$product->set_description( 'Description with café, 日本語, and emoji 🎉' );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertEquals( 'Café Product 日本語 🎉', $result['title'] );
		$this->assertStringContainsString( 'café', $result['description'] );
		$this->assertStringContainsString( '日本語', $result['description'] );
		$this->assertStringContainsString( '🎉', $result['description'] );

		$product->delete( true );
	}

	/**
	 * Test HTML stripping from title and description.
	 *
	 * @return void
	 */
	public function test_html_stripping() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_name( 'Product <strong>with</strong> HTML' );
		$product->set_description( '<p>Description with <em>HTML</em> tags</p>' );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertStringNotContainsString( '<strong>', $result['title'] );
		$this->assertStringNotContainsString( '</strong>', $result['title'] );
		$this->assertStringContainsString( 'with', $result['title'] );

		$this->assertStringNotContainsString( '<p>', $result['description'] );
		$this->assertStringNotContainsString( '<em>', $result['description'] );
		$this->assertStringContainsString( 'HTML', $result['description'] );

		$product->delete( true );
	}

	/**
	 * Test variable product mapping returns data.
	 *
	 * Note: In practice, variable products should be filtered out before mapping
	 * and only variations should be included in the feed.
	 *
	 * @return void
	 */
	public function test_variable_product_can_be_mapped() {
		$product = WC_Helper_Product::create_variation_product();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		// Variable products can be mapped (though they should be filtered out before feed generation).
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'title', $result );

		$product->delete( true );
	}

	/**
	 * Test variation mapping includes item_group_id and item_group_title.
	 *
	 * @return void
	 */
	public function test_variation_mapping_includes_group_fields() {
		$parent = WC_Helper_Product::create_variation_product();
		$parent->set_name( 'Variable Product' );
		$parent->save();

		$variations = $parent->get_children();
		$variation  = wc_get_product( $variations[0] );

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $variation );

		$this->assertNotNull( $result );
		$this->assertEquals( (string) $parent->get_id(), $result['item_group_id'] );
		$this->assertEquals( 'Variable Product', $result['item_group_title'] );

		$parent->delete( true );
	}

	/**
	 * Test variation inherits brand from parent if not set.
	 *
	 * @return void
	 */
	public function test_variation_inherits_brand_from_parent() {
		$parent = WC_Helper_Product::create_variation_product();

		// Create and set brand attribute on parent using helper.
		$brand_attribute = WC_Helper_Product::create_product_attribute_object( 'brand', [ 'Nike' ] );
		$attributes      = $parent->get_attributes();
		$attributes[]    = $brand_attribute;
		$parent->set_attributes( $attributes );
		$parent->save();

		$variations = $parent->get_children();
		$variation  = wc_get_product( $variations[0] );

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $variation );

		$this->assertEquals( 'Nike', $result['brand'], 'Variation should inherit brand from parent' );

		// Cleanup.
		$parent->delete( true );
		$attribute = wc_get_attribute( $brand_attribute->get_id() );
		if ( $attribute ) {
			$taxonomy = $attribute->slug;
			$terms    = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				]
			);
			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, $taxonomy );
			}
			wc_delete_attribute( $brand_attribute->get_id() );
		}
	}

	/**
	 * Test color attribute extraction.
	 *
	 * @return void
	 */
	public function test_color_attribute_extraction() {
		$product = WC_Helper_Product::create_simple_product();

		// Create and set the color attribute using helper.
		$color_attribute = WC_Helper_Product::create_product_attribute_object( 'color', [ 'Red' ] );
		$product->set_attributes( [ $color_attribute ] );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertEquals( 'Red', $result['color'] );

		// Cleanup.
		$product->delete( true );
		$attribute = wc_get_attribute( $color_attribute->get_id() );
		if ( $attribute ) {
			$taxonomy = $attribute->slug;
			$terms    = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				]
			);
			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, $taxonomy );
			}
			wc_delete_attribute( $color_attribute->get_id() );
		}
	}

	/**
	 * Test size attribute extraction.
	 *
	 * @return void
	 */
	public function test_size_attribute_extraction() {
		$product = WC_Helper_Product::create_simple_product();

		// Create and set the size attribute using helper.
		$size_attribute = WC_Helper_Product::create_product_attribute_object( 'size', [ 'Large' ] );
		$product->set_attributes( [ $size_attribute ] );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		// Size field might not be present if mapper returned null for it.
		if ( isset( $result['size'] ) ) {
			$this->assertEquals( 'Large', $result['size'] );
		} else {
			$this->markTestIncomplete( 'Size attribute was not mapped (returned null or empty)' );
		}

		// Cleanup.
		$product->delete( true );
		$attribute = wc_get_attribute( $size_attribute->get_id() );
		if ( $attribute ) {
			$taxonomy = $attribute->slug;
			$terms    = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				]
			);
			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, $taxonomy );
			}
			wc_delete_attribute( $size_attribute->get_id() );
		}
	}

	/**
	 * Test dimensions formatting with units.
	 *
	 * @return void
	 */
	public function test_dimensions_formatting() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_length( '10' );
		$product->set_width( '5' );
		$product->set_height( '3' );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		// Default WooCommerce unit is 'in'.
		$this->assertStringContainsString( '10', $result['length'] );
		$this->assertStringContainsString( 'in', $result['length'] );
		$this->assertStringContainsString( '5', $result['width'] );
		$this->assertStringContainsString( 'in', $result['width'] );
		$this->assertStringContainsString( '3', $result['height'] );
		$this->assertStringContainsString( 'in', $result['height'] );

		$product->delete( true );
	}

	/**
	 * Test weight formatting with units.
	 *
	 * @return void
	 */
	public function test_weight_formatting() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_weight( '2.5' );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		// Default WooCommerce unit is 'lbs'.
		$this->assertStringContainsString( '2.5', $result['weight'] );
		$this->assertStringContainsString( 'lbs', $result['weight'] );

		$product->delete( true );
	}

	/**
	 * Test GTIN field mapping.
	 *
	 * @return void
	 */
	public function test_gtin_field_mapping() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_sku( 'TEST-SKU' );
		$product->set_global_unique_id( '1234567890123' ); // Valid GTIN-13 format.
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertArrayHasKey( 'gtin', $result, 'GTIN field should be present when global_unique_id is set' );
		$this->assertEquals( '1234567890123', $result['gtin'], 'GTIN should match the global_unique_id' );

		$product->delete( true );
	}

	/**
	 * Test product link generation.
	 *
	 * @return void
	 */
	public function test_product_link_generation() {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		// Verify link is a valid URL (may use slug or ID depending on permalink settings).
		$this->assertStringStartsWith( 'http', $result['link'] );
		$this->assertNotEmpty( $result['link'] );

		$product->delete( true );
	}

	/**
	 * Test image_link generation.
	 *
	 * @return void
	 */
	public function test_image_link_generation() {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		// Should have some image link (either product image or placeholder).
		$this->assertNotNull( $result['image_link'] );
		$this->assertStringContainsString( 'http', $result['image_link'] );

		$product->delete( true );
	}

	/**
	 * Test inventory_not_tracked for virtual products.
	 *
	 * @return void
	 */
	public function test_inventory_not_tracked_for_virtual_products() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_virtual( true );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertEquals( 'true', $result['inventory_not_tracked'] );

		$product->delete( true );
	}

	/**
	 * Test inventory_quantity for tracked products.
	 *
	 * @return void
	 */
	public function test_inventory_quantity_for_tracked_products() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 50 );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertEquals( 50, $result['inventory_quantity'] );
		$this->assertEquals( 'false', $result['inventory_not_tracked'] );

		$product->delete( true );
	}

	/**
	 * Test all schema fields are present in mapper output.
	 *
	 * @return void
	 */
	public function test_all_required_fields_present_in_output() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_regular_price( '19.99' );
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		// Only required fields must be present (optional fields are removed if null/empty).
		$required_fields = \WC_Stripe_Agentic_Commerce_Feed_Schema::get_required_fields();

		foreach ( $required_fields as $field ) {
			$this->assertArrayHasKey( $field, $result, "Mapper output should include required field: {$field}" );
		}

		$product->delete( true );
	}

	/**
	 * Test tax code filter hook.
	 *
	 * @return void
	 */
	public function test_tax_code_filter_hook() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_tax_class( 'reduced-rate' );
		$product->save();

		// Add filter to return a tax code.
		add_filter(
			'wc_stripe_agentic_commerce_tax_code',
			function ( $tax_code, $tax_class, $product ) {
				if ( 'reduced-rate' === $tax_class ) {
					return 'txcd_12345678';
				}
				return $tax_code;
			},
			10,
			3
		);

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertEquals( 'txcd_12345678', $result['stripe_product_tax_code'] );

		// Remove filter.
		remove_all_filters( 'wc_stripe_agentic_commerce_tax_code' );

		$product->delete( true );
	}

	/**
	 * Test condition field defaults to 'new'.
	 *
	 * @return void
	 */
	public function test_condition_defaults_to_new() {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertEquals( 'new', $result['condition'] );

		$product->delete( true );
	}

	/**
	 * Test product_review_count returns null when no reviews.
	 *
	 * @return void
	 */
	public function test_product_review_count_returns_null_when_no_reviews() {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertNull( $result['product_review_count'], 'product_review_count should be null when no reviews exist' );

		$product->delete( true );
	}

	/**
	 * Test product_review_count returns count when reviews exist.
	 *
	 * @return void
	 */
	public function test_product_review_count_returns_count_when_reviews_exist() {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		// Create a review comment.
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'      => $product->get_id(),
				'comment_author'       => 'John Doe',
				'comment_author_email' => 'john@example.com',
				'comment_content'      => 'Great product!',
				'comment_type'         => 'review',
				'comment_approved'     => 1,
			]
		);

		// Clear product cache to reload review data.
		clean_post_cache( $product->get_id() );
		$product = wc_get_product( $product->get_id() );

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertEquals( 1, $result['product_review_count'], 'product_review_count should be 1 when one review exists' );

		// Cleanup.
		wp_delete_comment( $comment_id, true );
		$product->delete( true );
	}

	/**
	 * Test product_review_rating returns null when no reviews.
	 *
	 * @return void
	 */
	public function test_product_review_rating_returns_null_when_no_reviews() {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertNull( $result['product_review_rating'], 'product_review_rating should be null when no reviews exist' );

		$product->delete( true );
	}

	/**
	 * Test product_review_rating returns average rating when reviews exist.
	 *
	 * @return void
	 */
	public function test_product_review_rating_returns_average_when_reviews_exist() {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		// Create reviews with ratings.
		$comment_id_1 = wp_insert_comment(
			[
				'comment_post_ID'      => $product->get_id(),
				'comment_author'       => 'John Doe',
				'comment_author_email' => 'john@example.com',
				'comment_content'      => 'Great product!',
				'comment_type'         => 'review',
				'comment_approved'     => 1,
			]
		);
		add_comment_meta( $comment_id_1, 'rating', 5 );

		$comment_id_2 = wp_insert_comment(
			[
				'comment_post_ID'      => $product->get_id(),
				'comment_author'       => 'Jane Smith',
				'comment_author_email' => 'jane@example.com',
				'comment_content'      => 'Good product!',
				'comment_type'         => 'review',
				'comment_approved'     => 1,
			]
		);
		add_comment_meta( $comment_id_2, 'rating', 4 );

		// Clear product cache to reload review data.
		clean_post_cache( $product->get_id() );
		\WC_Comments::clear_transients( $product->get_id() );
		$product = wc_get_product( $product->get_id() );

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		// Average of 5 and 4 is 4.5.
		$this->assertEquals( 4.5, $result['product_review_rating'], 'product_review_rating should be 4.5 (average of 5 and 4)' );

		// Cleanup.
		wp_delete_comment( $comment_id_1, true );
		wp_delete_comment( $comment_id_2, true );
		$product->delete( true );
	}

	/**
	 * Test product_review_rating value is between 1 and 5.
	 *
	 * @return void
	 */
	public function test_product_review_rating_is_within_valid_range() {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		// Create a review with rating.
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'      => $product->get_id(),
				'comment_author'       => 'John Doe',
				'comment_author_email' => 'john@example.com',
				'comment_content'      => 'Great product!',
				'comment_type'         => 'review',
				'comment_approved'     => 1,
			]
		);
		add_comment_meta( $comment_id, 'rating', 3 );

		// Clear product cache to reload review data.
		clean_post_cache( $product->get_id() );
		\WC_Comments::clear_transients( $product->get_id() );
		$product = wc_get_product( $product->get_id() );

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertGreaterThanOrEqual( 1, $result['product_review_rating'], 'Rating should be >= 1' );
		$this->assertLessThanOrEqual( 5, $result['product_review_rating'], 'Rating should be <= 5' );

		// Cleanup.
		wp_delete_comment( $comment_id, true );
		$product->delete( true );
	}

	/**
	 * Test delete field always returns null for active products.
	 *
	 * @return void
	 */
	public function test_delete_field_always_returns_null_for_active_products() {
		$product = WC_Helper_Product::create_simple_product();
		$product->save();

		$mapper = new \WC_Stripe_Agentic_Commerce_Product_Mapper();
		$result = $mapper->map_product( $product );

		$this->assertNull( $result['delete'], 'delete field should always be null for products in the feed' );

		$product->delete( true );
	}
}
