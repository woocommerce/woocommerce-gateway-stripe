<?php

/**
 * A minimal consumer of WC_Stripe_Subscriptions_Trait that is NOT a UPE payment gateway.
 *
 * Used by the subscription hook-registration tests to assert that the
 * plugin-level subscription hooks are only registered for
 * `WC_Stripe_UPE_Payment_Gateway` instances, while the payment-method hooks are
 * still registered for any consumer.
 */
class WC_Stripe_Subscriptions_Trait_Non_UPE_Stub {
	use WC_Stripe_Subscriptions_Trait;

	/**
	 * The payment method ID.
	 *
	 * @var string
	 */
	public $id = 'stripe_test_non_upe';

	/**
	 * The supported features, merged into by `maybe_init_subscriptions()`.
	 *
	 * @var array
	 */
	public $supports = [];
}
