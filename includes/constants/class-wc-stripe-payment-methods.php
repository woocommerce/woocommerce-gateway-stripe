<?php

/**
 * Class WC_Stripe_Payment_Methods
 */
class WC_Stripe_Payment_Methods {
	const AFFIRM            = 'affirm';
	const AFTERPAY_CLEARPAY = 'afterpay_clearpay';
	const ALIPAY            = 'alipay';
	const BANCONTACT        = 'bancontact';
	const BOLETO            = 'boleto';
	const CARD              = 'card';
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
}
