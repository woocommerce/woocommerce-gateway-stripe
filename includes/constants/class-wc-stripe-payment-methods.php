<?php

/**
 * Class WC_Stripe_Payment_Methods
 */
class WC_Stripe_Payment_Methods {

	const ACH               = 'us_bank_account';
	const ACSS_DEBIT        = 'acss_debit';
	const AFFIRM            = 'affirm';
	const AFTERPAY_CLEARPAY = 'afterpay_clearpay';
	const ALIPAY            = 'alipay';
	const AMAZON_PAY        = 'amazon_pay';
	const BACS_DEBIT        = 'bacs_debit';
	const BANCONTACT        = 'bancontact';
	const BOLETO            = 'boleto';
	const CARD              = 'card';
	const CARD_PRESENT      = 'card_present';
	const CASHAPP_PAY       = 'cashapp';
	const EPS               = 'eps';
	const GIROPAY           = 'giropay';
	const IDEAL             = 'ideal';
	const KLARNA            = 'klarna';
	const LINK              = 'link';
	const MULTIBANCO        = 'multibanco';
	const OXXO              = 'oxxo';
	const P24               = 'p24';
	const SEPA              = 'sepa';
	const SEPA_DEBIT        = 'sepa_debit';
	const SOFORT            = 'sofort';
	const WECHAT_PAY        = 'wechat_pay';

	// Payment method labels
	const AMAZON_PAY_LABEL = 'Amazon Pay (Stripe)';
	const BACS_DEBIT_LABEL = 'Bacs Direct Debit';

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
	 * Payment methods we need to hide the action buttons from the order page.
	 */
	const PAYMENT_METHODS_WITH_DELAYED_VERIFICATION = [
		self::AMAZON_PAY_LABEL,
		self::BACS_DEBIT_LABEL,
	];
}
