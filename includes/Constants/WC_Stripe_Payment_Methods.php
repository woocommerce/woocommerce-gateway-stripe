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
}
