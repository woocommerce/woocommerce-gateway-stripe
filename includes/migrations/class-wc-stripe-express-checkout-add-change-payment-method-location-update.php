<?php
/**
 * Class WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update
 *
 * Opt merchants who already had every previous express-checkout button
 * location enabled into the new `change_payment_method` location, so the
 * upgrade preserves the "everything on" state instead of silently leaving
 * the new location off.
 *
 * Runs once. Merchants who customized their location set (i.e. didn't have
 * all three of product/cart/checkout enabled) are left alone.
 *
 * @since 10.7.0
 */
class WC_Stripe_Express_Checkout_Add_Change_Payment_Method_Location_Update {
	/**
	 * Option flag used to ensure the migration only runs once. Reading and
	 * writing a top-level option (rather than a gateway sub-option) keeps the
	 * gate independent of the settings array we mutate.
	 */
	private const MIGRATION_FLAG_OPTION = 'wc_stripe_express_checkout_cpm_location_migrated';

	/**
	 * The legacy location set this migration assumes the merchant had fully
	 * enabled before the new location existed.
	 *
	 * @var string[]
	 */
	private const LEGACY_LOCATIONS = [ 'product', 'cart', 'checkout' ];

	/**
	 * The location key being added by this migration.
	 *
	 * @var string
	 */
	private const NEW_LOCATION = 'change_payment_method';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_stripe_updated', [ $this, 'maybe_migrate' ] );
	}

	/**
	 * Append the new `change_payment_method` location when the merchant had
	 * the full pre-PR default set enabled. No-op otherwise.
	 *
	 * @return void
	 */
	public function maybe_migrate() {
		// One-shot guard: never run a second time, so a merchant who later
		// removes the new location doesn't have it added back on the next
		// version bump.
		if ( 'yes' === get_option( self::MIGRATION_FLAG_OPTION ) ) {
			return;
		}

		// The new location only matters for Subscriptions. Leave the flag
		// unset so the migration runs on the next plugin update if the
		// merchant installs Subscriptions later.
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		$gateway   = $this->get_gateway();
		$locations = $gateway->get_option( 'express_checkout_button_locations' );

		// Treat anything other than an array as "not the all-three default" —
		// don't touch it.
		if ( is_array( $locations )
			&& ! in_array( self::NEW_LOCATION, $locations, true )
			&& count( array_intersect( self::LEGACY_LOCATIONS, $locations ) ) === count( self::LEGACY_LOCATIONS )
		) {
			$locations[] = self::NEW_LOCATION;
			$gateway->update_option( 'express_checkout_button_locations', $locations );
		}

		update_option( self::MIGRATION_FLAG_OPTION, 'yes' );
	}

	/**
	 * Returns the main Stripe payment gateway. Kept as a protected seam so
	 * tests can override gateway resolution via a partial mock.
	 *
	 * @return WC_Stripe_Payment_Gateway
	 */
	protected function get_gateway() {
		return woocommerce_gateway_stripe()->get_main_stripe_gateway();
	}

	/**
	 * Whether WooCommerce Subscriptions is installed and active. Protected so
	 * tests can swap the result without loading or unloading the Subscriptions
	 * plugin at runtime.
	 *
	 * @return bool
	 */
	protected function is_subscriptions_enabled() {
		return WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled();
	}
}
