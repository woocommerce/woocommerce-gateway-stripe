<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Stripe_Order_Metas
 *
 * This class contains constants for order meta keys used in the Stripe payment gateway.
 */
class WC_Stripe_Order_Metas {
	/**
	 * Meta key for the Stripe source ID.
	 *
	 * @var string
	 */
	const META_STRIPE_SOURCE_ID = '_stripe_source_id';

	/**
	 * Meta key for the Stripe charge ID.
	 *
	 * @var string
	 */
	const META_STRIPE_CHARGE_ID = '_transaction_id';

	/**
	 * Meta key for the Stripe refund ID.
	 *
	 * @var string
	 */
	const META_STRIPE_REFUND_ID = '_stripe_refund_id';

	/**
	 * Meta key for the Stripe Payment Intent ID.
	 *
	 * @var string
	 */
	const META_STRIPE_INTENT_ID = '_stripe_intent_id';

	/**
	 * Meta key for the Stripe Setup Intent ID.
	 *
	 * @var string
	 */
	const META_STRIPE_SETUP_INTENT = '_stripe_setup_intent';

	/**
	 * Meta key for the Stripe fee amount.
	 */
	const META_STRIPE_FEE = '_stripe_fee';

	/**
	 * Meta key for the Stripe net amount.
	 */
	const META_STRIPE_NET = '_stripe_net';

	/**
	 * Meta key for the Stripe legacy fee amount.
	 *
	 * @var string
	 */
	const LEGACY_META_STRIPE_FEE = 'Stripe Fee';

	/**
	 * Meta key for the Stripe legacy net amount.
	 *
	 * @var string
	 */
	const LEGACY_META_STRIPE_NET = 'Net Revenue From Stripe';

	/**
	 * Meta key for the Stripe currency.
	 *
	 * @var string
	 */
	const META_STRIPE_CURRENCY = '_stripe_currency';

	/**
	 * Meta key for the payment awaiting action flag.
	 *
	 * @var string
	 */
	const META_STRIPE_PAYMENT_AWAITING_ACTION = '_stripe_payment_awaiting_action';

	/**
	 * Meta key for the Stripe card brand.
	 *
	 * @var string
	 */
	const META_STRIPE_CARD_BRAND = '_stripe_card_brand';

	/**
	 * Meta key for the Stripe lock refund.
	 *
	 * @var string
	 */
	const META_STRIPE_LOCK_REFUND = '_stripe_lock_refund';

	/**
	 * Meta key for the Stripe lock payment.
	 *
	 * @var string
	 */
	const META_STRIPE_LOCK_PAYMENT = '_stripe_lock_payment';

	/**
	 * Meta key for the redirect processed flag.
	 *
	 * @var string
	 */
	const META_STRIPE_UPE_REDIRECT_PROCESSED = '_stripe_upe_redirect_processed';

	/**
	 * Meta key for the status before hold.
	 *
	 * @var string
	 */
	const META_STRIPE_STATUS_BEFORE_HOLD = '_stripe_status_before_hold';

	/**
	 * Meta key for the UPE waiting for redirect flag.
	 *
	 * @var string
	 */
	const META_STRIPE_UPE_WAITING_FOR_REDIRECT = '_stripe_upe_waiting_for_redirect';

	/**
	 * Meta key for the mandate ID.
	 *
	 * @var string
	 */
	const META_STRIPE_MANDATE_ID = '_stripe_mandate_id';

	/**
	 * Meta key for the customer ID.
	 *
	 * @var string
	 */
	const META_STRIPE_CUSTOMER_ID = '_stripe_customer_id';

	/**
	 * Meta key for the charge captured flag.
	 *
	 * @var string
	 */
	const META_STRIPE_CHARGE_CAPTURED = '_stripe_charge_captured';

	/**
	 * Meta key to identify whether the status is final.
	 *
	 * @var string
	 */
	const META_STRIPE_STATUS_FINAL = '_stripe_status_final';

	/**
	 * Meta key for the Multibanco data.
	 *
	 * @var string
	 */
	const META_STRIPE_MULTIBANCO = '_stripe_multibanco';

	/**
	 * Meta key for the UPE payment type.
	 *
	 * @var string
	 */
	const META_STRIPE_UPE_PAYMENT_TYPE = '_stripe_upe_payment_type';
}
