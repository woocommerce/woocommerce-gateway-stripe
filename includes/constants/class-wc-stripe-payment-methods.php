<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Stripe_Payment_Methods
 */
class WC_Stripe_Payment_Methods {
	// Standard payment method constants
	const ACH               = 'us_bank_account';
	const ACSS_DEBIT        = 'acss_debit';
	const AFFIRM            = 'affirm';
	const AFTERPAY_CLEARPAY = 'afterpay_clearpay';
	const ALIPAY            = 'alipay';
	const BACS_DEBIT        = 'bacs_debit';
	const BECS_DEBIT        = 'au_becs_debit';
	const BANCONTACT        = 'bancontact';
	const BLIK              = 'blik';
	const BOLETO            = 'boleto';
	const CARD              = 'card';
	const CARD_PRESENT      = 'card_present';
	const CASHAPP_PAY       = 'cashapp';
	const EPS               = 'eps';
	const GIROPAY           = 'giropay';
	const IDEAL             = 'ideal';
	const KLARNA            = 'klarna';
	const MULTIBANCO        = 'multibanco';
	const OXXO              = 'oxxo';
	const P24               = 'p24';
	const SEPA              = 'sepa';
	const SEPA_DEBIT        = 'sepa_debit';
	const SOFORT            = 'sofort';
	const WECHAT_PAY        = 'wechat_pay';
	const OC                = 'card'; // This is a special case for the Optimized Checkout

	public const LEGACY_SEPA       = 'stripe_sepa'; // Sepa method identifier for the legacy checkout (now removed)

	// Express method constants
	const AMAZON_PAY = 'amazon_pay';
	const GOOGLE_PAY = 'google_pay';
	const APPLE_PAY  = 'apple_pay';
	const LINK       = 'link';

	// Payment method labels
	const BACS_DEBIT_LABEL      = 'Bacs Direct Debit';
	const GOOGLE_PAY_LABEL      = 'Google Pay';
	const APPLE_PAY_LABEL       = 'Apple Pay';
	const LINK_LABEL            = 'Link';
	const PAYMENT_REQUEST_LABEL = 'Payment Request';

	/**
	 * Payment methods that are considered as express payment methods.
	 *
	 * @var array
	 */
	const EXPRESS_PAYMENT_METHODS = [
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
	const VOUCHER_PAYMENT_METHODS = [
		self::BOLETO,
		self::MULTIBANCO,
		self::OXXO,
	];

	/**
	 * Payment methods that are considered as BNPL (Buy Now, Pay Later) payment methods.
	 *
	 * @var array
	 */
	const BNPL_PAYMENT_METHODS = [
		self::AFFIRM,
		self::AFTERPAY_CLEARPAY,
		self::KLARNA,
	];

	/**
	 * Payment methods that are considered as wallet payment methods.
	 *
	 * @var array
	 */
	const WALLET_PAYMENT_METHODS = [
		self::CASHAPP_PAY,
		self::WECHAT_PAY,
	];

	/**
	 * List of express payment methods labels. Amazon Pay and Link are not included,
	 * as they have their own payment method classes.
	 */
	const EXPRESS_METHODS_LABELS = [
		'google_pay' => self::GOOGLE_PAY_LABEL,
		'apple_pay'  => self::APPLE_PAY_LABEL,
	];

	/**
	 * Payment method types that can be excluded via Stripe's 'excludedPaymentMethodTypes' parameter.
	 * Note: 'Link', 'Apple Pay', and 'Google Pay' are not supported in this parameter, so they are not included.
	 *
	 * @var array
	 */
	const EXCLUDABLE_PAYMENT_METHOD_TYPES = [
		'billey',
		'bizum',
		'capchase_pay',
		'kriya',
		'mondu',
		'ng_wallet',
		'paypay',
		'samsung_pay',
		'satispay',
		'sequra',
		'scalapay',
		'vipps',
		'amazon_pay',
		'alipay',
		'alma',
		'affirm',
		'afterpay_clearpay',
		'au_becs_debit',
		'acss_debit',
		'bacs_debit',
		'bancontact',
		'blik',
		'boleto',
		'card',
		'cashapp',
		'crypto',
		'custom',
		'customer_balance',
		'eps',
		'fpx',
		'giropay',
		'gopay',
		'grabpay',
		'ideal',
		'klarna',
		'konbini',
		'mb_way',
		'mobilepay',
		'multibanco',
		'ng_bank',
		'ng_bank_transfer',
		'ng_card',
		'ng_market',
		'ng_ussd',
		'nz_bank_account',
		'oxxo',
		'p24',
		'pay_by_bank',
		'paypal',
		'payto',
		'qris',
		'rechnung',
		'sepa_debit',
		'sofort',
		'south_korea_market',
		'kr_card',
		'kr_market',
		'kakao_pay',
		'naver_pay',
		'payco',
		'shopeepay',
		'swish',
		'twint',
		'wero',
		'upi',
		'us_bank_account',
		'wechat_pay',
		'paynow',
		'pix',
		'promptpay',
		'revolut_pay',
		'sunbit',
		'netbanking',
		'id_bank_transfer',
		'demo_pay',
		'shop_pay',
		'zip',
	];
}
