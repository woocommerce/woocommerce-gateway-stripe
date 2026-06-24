<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Stripe_Payment_Methods
 */
class WC_Stripe_Payment_Methods {
	// Standard payment method constants
	public const ACH               = 'us_bank_account';
	public const ACSS_DEBIT        = 'acss_debit';
	public const AFFIRM            = 'affirm';
	public const AFTERPAY_CLEARPAY = 'afterpay_clearpay';
	public const ALIPAY            = 'alipay';
	public const BACS_DEBIT        = 'bacs_debit';
	public const BECS_DEBIT        = 'au_becs_debit';
	public const BANCONTACT        = 'bancontact';
	public const BLIK              = 'blik';
	public const BOLETO            = 'boleto';
	public const CARD              = 'card';
	public const CARD_PRESENT      = 'card_present';
	public const CASHAPP_PAY       = 'cashapp';
	public const EPS               = 'eps';
	public const GIROPAY           = 'giropay';
	public const IDEAL             = 'ideal';
	public const KLARNA            = 'klarna';
	public const MULTIBANCO        = 'multibanco';
	public const OXXO              = 'oxxo';
	public const P24               = 'p24';
	public const SEPA              = 'sepa';
	public const SEPA_DEBIT        = 'sepa_debit';
	public const SOFORT            = 'sofort';
	public const WECHAT_PAY        = 'wechat_pay';
	public const OC                = 'card'; // This is a special case for the Optimized Checkout

	public const LEGACY_SEPA = 'stripe_sepa'; // Sepa method identifier for the legacy checkout (now removed)

	// Express method constants
	public const AMAZON_PAY = 'amazon_pay';
	public const GOOGLE_PAY = 'google_pay';
	public const APPLE_PAY  = 'apple_pay';
	public const LINK       = 'link';

	// Payment method labels
	public const BACS_DEBIT_LABEL      = 'Bacs Direct Debit';
	public const GOOGLE_PAY_LABEL      = 'Google Pay';
	public const APPLE_PAY_LABEL       = 'Apple Pay';
	public const AMAZON_PAY_LABEL      = 'Amazon Pay';
	public const LINK_LABEL            = 'Link';
	public const PAYMENT_REQUEST_LABEL = 'Payment Request';

	/**
	 * Payment methods that are considered as express payment methods.
	 *
	 * @var array
	 */
	public const EXPRESS_PAYMENT_METHODS = [
		self::AMAZON_PAY,
		self::GOOGLE_PAY,
		self::APPLE_PAY,
		self::LINK,
	];

	/**
	 * Payment methods that are considered as voucher payment methods.
	 *
	 * @var array
	 */
	public const VOUCHER_PAYMENT_METHODS = [
		self::BOLETO,
		self::MULTIBANCO,
		self::OXXO,
	];

	/**
	 * Payment methods that are considered as BNPL (Buy Now, Pay Later) payment methods.
	 *
	 * @var array
	 */
	public const BNPL_PAYMENT_METHODS = [
		self::AFFIRM,
		self::AFTERPAY_CLEARPAY,
		self::KLARNA,
	];

	/**
	 * Payment methods that are considered as wallet payment methods.
	 *
	 * @var array
	 */
	public const WALLET_PAYMENT_METHODS = [
		self::CASHAPP_PAY,
		self::WECHAT_PAY,
	];

	/**
	 * List of express payment methods labels. Amazon Pay and Link are not included,
	 * as they have their own payment method classes.
	 */
	public const EXPRESS_METHODS_LABELS = [
		'google_pay' => self::GOOGLE_PAY_LABEL,
		'apple_pay'  => self::APPLE_PAY_LABEL,
	];

	/**
	 * Payment method types that can not be excluded via Stripe's 'excludedPaymentMethodTypes' parameter.
	 * These values are not supported in the 'excludedPaymentMethodTypes' parameter and causes an error when trying to render the Payment Element excluding them.
	 *
	 * The list is inferred by comparing the currently unsupported types of the accepted arguments to excluded_payment_method_types (https://docs.stripe.com/api/payment_intents/update#update_payment_intent-excluded_payment_method_types)
	 * against the possible elements that are present in the Payment Method Configuration object (https://docs.stripe.com/api/payment_method_configurations/object).
	 * see https://github.com/woocommerce/woocommerce-gateway-stripe/pull/4922#discussion_r2770707821
	 *
	 * @var array
	 */
	public const NON_EXCLUDABLE_PAYMENT_METHOD_TYPES = [
		self::APPLE_PAY,
		self::GOOGLE_PAY,
		self::LINK,
		'cartes_bancaires',
		'jcb',
	];
}
