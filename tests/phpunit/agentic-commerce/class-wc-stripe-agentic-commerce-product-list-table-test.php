<?php
/**
 * Tests for WC_Stripe_Agentic_Commerce_Product_List_Table
 *
 * @package WooCommerce\Stripe\Tests
 */

/**
 * Covers the Products list-table surface for the exclude flag: bulk actions,
 * quick edit save, status column, and the sync-status filter.
 *
 * The flag storage and should-sync filter are covered in
 * {@see WC_Stripe_Agentic_Commerce_Product_Exclusion_Test}; the product editor
 * checkbox in {@see WC_Stripe_Agentic_Commerce_Product_Meta_Box_Test}.
 */
class WC_Stripe_Agentic_Commerce_Product_List_Table_Test extends WP_UnitTestCase {

	/**
	 * Skip if the class isn't autoloaded; default the feature on (gate tests flip it off).
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Stripe_Agentic_Commerce_Product_List_Table' ) ) {
			$this->markTestSkipped( 'WC_Stripe_Agentic_Commerce_Product_List_Table class not loaded' );
		}

		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'yes' );
	}

	public function tearDown(): void {
		delete_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION );
		$_POST = [];
		$_GET  = [];
		parent::tearDown();
	}

	/**
	 * Resolve a private constant of the list-table class.
	 *
	 * @param string $name Constant name.
	 * @return string
	 */
	private function class_const( string $name ): string {
		return WC_Stripe_Test_Helper::get_class_const_value(
			WC_Stripe_Agentic_Commerce_Product_List_Table::class,
			$name,
			'string'
		);
	}

	/**
	 * Resolve the protected immediate-resync action name for scheduling assertions.
	 */
	private function immediate_action(): string {
		return WC_Stripe_Test_Helper::get_class_const_value(
			WC_Stripe_Agentic_Commerce_Integration::class,
			'IMMEDIATE_SYNC_ACTION',
			'string'
		);
	}

	/**
	 * Log in a user who can edit products.
	 */
	private function login_as_shop_admin(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Both bulk actions register under their own keys.
	 */
	public function test_register_bulk_actions_adds_both_actions(): void {
		$actions = ( new WC_Stripe_Agentic_Commerce_Product_List_Table() )->register_bulk_actions( [ 'trash' => 'Move to Trash' ] );

		$this->assertArrayHasKey( 'trash', $actions );
		$this->assertArrayHasKey( $this->class_const( 'ACTION_EXCLUDE' ), $actions );
		$this->assertArrayHasKey( $this->class_const( 'ACTION_INCLUDE' ), $actions );
	}

	/**
	 * A foreign bulk action passes the redirect URL through untouched.
	 */
	public function test_handle_bulk_actions_ignores_other_actions(): void {
		$this->login_as_shop_admin();
		$product = WC_Helper_Product::create_simple_product();

		$redirect = ( new WC_Stripe_Agentic_Commerce_Product_List_Table() )->handle_bulk_actions(
			'http://example.org/wp-admin/edit.php?post_type=product',
			'trash',
			[ $product->get_id() ]
		);

		$this->assertSame( 'http://example.org/wp-admin/edit.php?post_type=product', $redirect );
		$this->assertSame( '', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), true ) );

		$product->delete( true );
	}

	/**
	 * Excluding then re-including flips the stored flag both ways and reports
	 * the processed count in the redirect.
	 */
	public function test_handle_bulk_actions_toggles_flag_both_ways(): void {
		$this->login_as_shop_admin();
		$meta_key  = WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key();
		$product_a = WC_Helper_Product::create_simple_product();
		$product_b = WC_Helper_Product::create_simple_product();
		$ids       = [ $product_a->get_id(), $product_b->get_id() ];
		$table     = new WC_Stripe_Agentic_Commerce_Product_List_Table();

		$redirect = $table->handle_bulk_actions( 'http://example.org/wp-admin/edit.php?post_type=product', $this->class_const( 'ACTION_EXCLUDE' ), $ids );

		$this->assertSame( 'yes', get_post_meta( $product_a->get_id(), $meta_key, true ) );
		$this->assertSame( 'yes', get_post_meta( $product_b->get_id(), $meta_key, true ) );
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
		$this->assertSame( '2', $query[ $this->class_const( 'NOTICE_ARG_EXCLUDED' ) ] );

		$redirect = $table->handle_bulk_actions( $redirect, $this->class_const( 'ACTION_INCLUDE' ), $ids );

		$this->assertSame( 'no', get_post_meta( $product_a->get_id(), $meta_key, true ) );
		$this->assertSame( 'no', get_post_meta( $product_b->get_id(), $meta_key, true ) );
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
		$this->assertSame( '2', $query[ $this->class_const( 'NOTICE_ARG_INCLUDED' ) ] );
		// The exclude count from the first redirect must not survive into the
		// include redirect, or the screen would show two contradictory notices.
		$this->assertArrayNotHasKey( $this->class_const( 'NOTICE_ARG_EXCLUDED' ), $query );

		$product_a->delete( true );
		$product_b->delete( true );
	}

	/**
	 * Unsupported product types are skipped: meta untouched and not counted.
	 */
	public function test_handle_bulk_actions_skips_unsupported_types(): void {
		$this->login_as_shop_admin();
		$meta_key = WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key();
		$simple   = WC_Helper_Product::create_simple_product();
		$grouped  = WC_Helper_Product::create_grouped_product();

		$redirect = ( new WC_Stripe_Agentic_Commerce_Product_List_Table() )->handle_bulk_actions(
			'http://example.org/wp-admin/edit.php?post_type=product',
			$this->class_const( 'ACTION_EXCLUDE' ),
			[ $simple->get_id(), $grouped->get_id() ]
		);

		$this->assertSame( 'yes', get_post_meta( $simple->get_id(), $meta_key, true ) );
		$this->assertSame( '', get_post_meta( $grouped->get_id(), $meta_key, true ), 'Unsupported types must not be written to.' );
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
		$this->assertSame( '1', $query[ $this->class_const( 'NOTICE_ARG_EXCLUDED' ) ], 'Skipped products must not inflate the notice count.' );

		$simple->delete( true );
		$grouped->delete( true );
	}

	/**
	 * A user without edit_product on the targets changes nothing.
	 */
	public function test_handle_bulk_actions_requires_capability(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );
		$product = WC_Helper_Product::create_simple_product();

		$redirect = ( new WC_Stripe_Agentic_Commerce_Product_List_Table() )->handle_bulk_actions(
			'http://example.org/wp-admin/edit.php?post_type=product',
			$this->class_const( 'ACTION_EXCLUDE' ),
			[ $product->get_id() ]
		);

		$this->assertSame( '', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), true ) );
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
		$this->assertSame( '0', $query[ $this->class_const( 'NOTICE_ARG_EXCLUDED' ) ] );

		$product->delete( true );
	}

	/**
	 * The feature-off gate blocks the bulk handler even if it's somehow invoked.
	 */
	public function test_handle_bulk_actions_skips_when_merchant_disabled(): void {
		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, 'no' );
		$this->login_as_shop_admin();
		$product = WC_Helper_Product::create_simple_product();

		$redirect = ( new WC_Stripe_Agentic_Commerce_Product_List_Table() )->handle_bulk_actions(
			'http://example.org/wp-admin/edit.php?post_type=product',
			$this->class_const( 'ACTION_EXCLUDE' ),
			[ $product->get_id() ]
		);

		$this->assertSame( 'http://example.org/wp-admin/edit.php?post_type=product', $redirect );
		$this->assertSame( '', get_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), true ) );

		$product->delete( true );
	}

	/**
	 * A batch with real changes schedules exactly one immediate resync; a no-op
	 * batch schedules none.
	 */
	public function test_handle_bulk_actions_schedules_single_resync_only_on_change(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		$this->login_as_shop_admin();
		$immediate = $this->immediate_action();
		as_unschedule_all_actions( $immediate, [], 'wc-stripe' );

		$product_a = WC_Helper_Product::create_simple_product();
		$product_b = WC_Helper_Product::create_simple_product();
		$ids       = [ $product_a->get_id(), $product_b->get_id() ];
		$table     = new WC_Stripe_Agentic_Commerce_Product_List_Table();

		$table->handle_bulk_actions( 'http://example.org/', $this->class_const( 'ACTION_EXCLUDE' ), $ids );
		$this->assertNotFalse( as_has_scheduled_action( $immediate ), 'A batch with changes must enqueue a resync.' );

		// Re-excluding the same products is a no-op batch → no new resync.
		as_unschedule_all_actions( $immediate, [], 'wc-stripe' );
		$table->handle_bulk_actions( 'http://example.org/', $this->class_const( 'ACTION_EXCLUDE' ), $ids );
		$this->assertFalse( as_has_scheduled_action( $immediate ), 'A no-op batch must not enqueue a resync.' );

		as_unschedule_all_actions( $immediate, [], 'wc-stripe' );
		$product_a->delete( true );
		$product_b->delete( true );
	}

	/**
	 * Quick-edit save scenarios: posted values, the rendered-field marker, the
	 * type gate, and the feature gate.
	 *
	 * @dataProvider provide_quick_edit_save_scenarios
	 *
	 * @param string      $product_type   'simple', 'variable', or 'grouped'.
	 * @param string      $stored_value   Pre-existing meta value ('' = unset).
	 * @param string|null $posted_value   Posted checkbox value (null = absent key).
	 * @param bool        $marker_present Whether the rendered-field marker was submitted.
	 * @param bool        $enabled        Whether the merchant feature is on.
	 * @param string      $expected_meta  Meta value expected after the save.
	 */
	public function test_save_quick_edit( string $product_type, string $stored_value, ?string $posted_value, bool $marker_present, bool $enabled, string $expected_meta ): void {
		update_option( WC_Stripe_Agentic_Commerce_Integration::ENABLED_OPTION, $enabled ? 'yes' : 'no' );

		$meta_key = WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key();
		switch ( $product_type ) {
			case 'grouped':
				$product = WC_Helper_Product::create_grouped_product();
				break;
			case 'variable':
				$product = WC_Helper_Product::create_variation_product();
				break;
			default:
				$product = WC_Helper_Product::create_simple_product();
		}

		if ( '' !== $stored_value ) {
			update_post_meta( $product->get_id(), $meta_key, $stored_value );
		}

		$_POST = null === $posted_value ? [] : [ $meta_key => $posted_value ];
		if ( $marker_present ) {
			$_POST[ $meta_key . '_present' ] = '1';
		}

		( new WC_Stripe_Agentic_Commerce_Product_List_Table() )->save_quick_edit( $product );

		$this->assertSame( $expected_meta, get_post_meta( $product->get_id(), $meta_key, true ) );

		$product->delete( true );
	}

	/**
	 * Scenarios for {@see self::test_save_quick_edit()}.
	 */
	public function provide_quick_edit_save_scenarios(): array {
		return [
			'checked checkbox persists yes'         => [ 'simple', '', 'yes', true, true, 'yes' ],
			'variable type passes the gate'         => [ 'variable', '', 'yes', true, true, 'yes' ],
			'absent checkbox collapses to no'       => [ 'simple', 'yes', null, true, true, 'no' ],
			'tampered value collapses to no'        => [ 'simple', 'yes', 'forged', true, true, 'no' ],
			'missing marker leaves stored value'    => [ 'simple', 'yes', null, false, true, 'yes' ],
			'unsupported type is left untouched'    => [ 'grouped', 'yes', null, true, true, 'yes' ],
			'feature off leaves stored value alone' => [ 'simple', 'yes', null, true, false, 'yes' ],
		];
	}

	/**
	 * A real quick-edit change enqueues an immediate resync; a no-op save does not.
	 */
	public function test_save_quick_edit_schedules_resync_only_on_real_change(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not available.' );
		}

		$immediate = $this->immediate_action();
		as_unschedule_all_actions( $immediate, [], 'wc-stripe' );

		$meta_key = WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key();
		$product  = WC_Helper_Product::create_simple_product();
		$table    = new WC_Stripe_Agentic_Commerce_Product_List_Table();

		$_POST = [
			$meta_key              => 'yes',
			$meta_key . '_present' => '1',
		];
		$table->save_quick_edit( $product );
		$this->assertNotFalse( as_has_scheduled_action( $immediate ), 'A real change must enqueue an immediate resync.' );

		as_unschedule_all_actions( $immediate, [], 'wc-stripe' );
		$table->save_quick_edit( $product );
		$this->assertFalse( as_has_scheduled_action( $immediate ), 'A no-op save must not enqueue a resync.' );

		as_unschedule_all_actions( $immediate, [], 'wc-stripe' );
		$product->delete( true );
	}

	/**
	 * The column renders the flag state for supported types and a dash for others.
	 */
	public function test_render_column_states(): void {
		$column  = $this->class_const( 'COLUMN_KEY' );
		$table   = new WC_Stripe_Agentic_Commerce_Product_List_Table();
		$product = WC_Helper_Product::create_simple_product();

		ob_start();
		$table->render_column( $column, $product->get_id() );
		$this->assertStringContainsString( 'Synced', ob_get_clean() );

		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), 'yes' );
		ob_start();
		$table->render_column( $column, $product->get_id() );
		$this->assertStringContainsString( 'Excluded', ob_get_clean() );

		$grouped = WC_Helper_Product::create_grouped_product();
		ob_start();
		$table->render_column( $column, $grouped->get_id() );
		$this->assertStringContainsString( '&mdash;', ob_get_clean(), 'Unsupported types must render the dash.' );

		// A foreign column must produce no output.
		ob_start();
		$table->render_column( 'sku', $product->get_id() );
		$this->assertSame( '', ob_get_clean() );

		$product->delete( true );
		$grouped->delete( true );
	}

	/**
	 * The inline-data div carries the exclude state for supported product rows
	 * only — its absence is the quick-edit script's cue to hide the checkbox.
	 */
	public function test_render_inline_data_states(): void {
		$table        = new WC_Stripe_Agentic_Commerce_Product_List_Table();
		$product_type = get_post_type_object( 'product' );
		$product      = WC_Helper_Product::create_simple_product();
		$post         = get_post( $product->get_id() );

		ob_start();
		$table->render_inline_data( $post, $product_type );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'wc_stripe_agentic_excluded', $output );
		$this->assertStringContainsString( '>no<', $output );

		update_post_meta( $product->get_id(), WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), 'yes' );
		ob_start();
		$table->render_inline_data( $post, $product_type );
		$this->assertStringContainsString( '>yes<', ob_get_clean() );

		$grouped = WC_Helper_Product::create_grouped_product();
		ob_start();
		$table->render_inline_data( get_post( $grouped->get_id() ), $product_type );
		$this->assertSame( '', ob_get_clean(), 'Unsupported types must not expose a toggleable state.' );

		ob_start();
		$table->render_inline_data( $post, get_post_type_object( 'post' ) );
		$this->assertSame( '', ob_get_clean(), 'Other post types must produce no output.' );

		$product->delete( true );
		$grouped->delete( true );
	}

	/**
	 * The status filter narrows the main products query via meta_query.
	 *
	 * @dataProvider provide_sync_status_filter_scenarios
	 *
	 * @param string $status              Filter dropdown value.
	 * @param bool   $expects_clause      Whether a meta_query clause should be added.
	 * @param bool   $expects_constraints Whether the synced-only type/status constraints should be added.
	 */
	public function test_filter_products_by_sync_status( string $status, bool $expects_clause, bool $expects_constraints ): void {
		set_current_screen( 'edit-product' );
		$_GET[ $this->class_const( 'FILTER_QUERY_VAR' ) ] = $status;

		$query = new WP_Query();
		$query->set( 'post_type', 'product' );
		// is_main_query() compares against $wp_the_query; adopt this one as it.
		$GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		( new WC_Stripe_Agentic_Commerce_Product_List_Table() )->filter_products_by_sync_status( $query );

		$meta_query = $query->get( 'meta_query' );
		if ( $expects_clause ) {
			$this->assertIsArray( $meta_query );
			$this->assertCount( 1, $meta_query );
		} else {
			$this->assertEmpty( $meta_query );
		}

		if ( $expects_constraints ) {
			$this->assertCount( 1, $query->get( 'tax_query' ), 'Synced must constrain to supported product types.' );
			$this->assertSame( 'publish', $query->get( 'post_status' ), 'Synced must constrain to published products.' );
		} else {
			$this->assertEmpty( $query->get( 'tax_query' ) );
		}

		unset( $GLOBALS['wp_the_query'], $GLOBALS['current_screen'] );
	}

	/**
	 * Scenarios for {@see self::test_filter_products_by_sync_status()}.
	 */
	public function provide_sync_status_filter_scenarios(): array {
		return [
			'excluded filter adds clause'    => [ 'excluded', true, false ],
			'synced filter adds constraints' => [ 'synced', true, true ],
			'no filter leaves query alone'   => [ '', false, false ],
			'unknown value is ignored'       => [ 'bogus', false, false ],
		];
	}

	/**
	 * The excluded filter clause matches only flagged products; the synced
	 * clause matches unset and 'no' alike.
	 */
	public function test_filter_products_by_sync_status_query_results(): void {
		set_current_screen( 'edit-product' );

		$excluded  = WC_Helper_Product::create_simple_product();
		$included  = WC_Helper_Product::create_simple_product();
		$untouched = WC_Helper_Product::create_simple_product();
		$draft     = WC_Helper_Product::create_simple_product();
		$grouped   = WC_Helper_Product::create_grouped_product();
		$meta_key  = WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key();
		update_post_meta( $excluded->get_id(), $meta_key, 'yes' );
		update_post_meta( $included->get_id(), $meta_key, 'no' );
		$draft->set_status( 'draft' );
		$draft->save();

		// Scope the query to this test's fixtures — the suite pre-seeds other
		// products, and the clause under test must compose with existing vars.
		$fixture_ids = [ $excluded->get_id(), $included->get_id(), $untouched->get_id(), $draft->get_id(), $grouped->get_id() ];

		$_GET[ $this->class_const( 'FILTER_QUERY_VAR' ) ] = 'excluded';
		$this->assertSame(
			[ $excluded->get_id() ],
			$this->run_filtered_product_query( $fixture_ids ),
			'The excluded filter must return only flagged products.'
		);

		$_GET[ $this->class_const( 'FILTER_QUERY_VAR' ) ] = 'synced';
		$this->assertSame(
			[ $included->get_id(), $untouched->get_id() ],
			$this->run_filtered_product_query( $fixture_ids ),
			'The synced filter must match unset meta and a stored no, but never drafts or unsupported types.'
		);

		unset( $GLOBALS['current_screen'] );
		$excluded->delete( true );
		$included->delete( true );
		$untouched->delete( true );
		$draft->delete( true );
		$grouped->delete( true );
	}

	/**
	 * Run a products main query through the filter callback and return the IDs.
	 *
	 * @param int[] $fixture_ids Products the query is limited to.
	 * @return int[]
	 */
	private function run_filtered_product_query( array $fixture_ids ): array {
		$query = new WP_Query();
		// is_main_query() compares against $wp_the_query; adopt this one as it.
		$GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$table = new WC_Stripe_Agentic_Commerce_Product_List_Table();
		add_action( 'pre_get_posts', [ $table, 'filter_products_by_sync_status' ] );
		$ids = $query->query(
			[
				'post_type'      => 'product',
				// 'any' so the synced filter's publish constraint is what
				// keeps drafts out, not this fixture query's own default.
				'post_status'    => 'any',
				'post__in'       => $fixture_ids,
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);
		remove_action( 'pre_get_posts', [ $table, 'filter_products_by_sync_status' ] );
		unset( $GLOBALS['wp_the_query'] );

		return array_map( 'intval', $ids );
	}

	/**
	 * The quick-edit box renders the checkbox for our column on products only.
	 */
	public function test_render_quick_edit_field(): void {
		$column = $this->class_const( 'COLUMN_KEY' );
		$table  = new WC_Stripe_Agentic_Commerce_Product_List_Table();

		ob_start();
		$table->render_quick_edit_field( $column, 'product' );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key(), $output );
		$this->assertStringContainsString( WC_Stripe_Agentic_Commerce_Product_Exclusion::get_meta_key() . '_present', $output, 'The rendered-field marker must travel with the checkbox.' );

		ob_start();
		$table->render_quick_edit_field( $column, 'post' );
		$this->assertSame( '', ob_get_clean(), 'The field must not render for other post types.' );

		ob_start();
		$table->render_quick_edit_field( 'sku', 'product' );
		$this->assertSame( '', ob_get_clean(), 'The field must not render for other columns.' );
	}

	/**
	 * The bulk-action notice renders the count for either direction.
	 */
	public function test_render_bulk_action_notice(): void {
		$table = new WC_Stripe_Agentic_Commerce_Product_List_Table();

		$_GET = [ $this->class_const( 'NOTICE_ARG_EXCLUDED' ) => '3' ];
		ob_start();
		$table->render_bulk_action_notice();
		$output = ob_get_clean();
		$this->assertStringContainsString( '3 products excluded from the Agentic Commerce catalog sync.', $output );

		$_GET = [ $this->class_const( 'NOTICE_ARG_INCLUDED' ) => '1' ];
		ob_start();
		$table->render_bulk_action_notice();
		$output = ob_get_clean();
		$this->assertStringContainsString( '1 product included in the Agentic Commerce catalog sync.', $output );

		$_GET = [];
		ob_start();
		$table->render_bulk_action_notice();
		$this->assertSame( '', ob_get_clean(), 'No notice without a result arg.' );
	}
}
