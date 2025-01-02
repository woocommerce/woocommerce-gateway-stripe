/**
 * Payment method name constants without the `stripe` prefix
 */
export const PAYMENT_METHOD_CARD = 'card';
export const PAYMENT_METHOD_GIROPAY = 'giropay';
export const PAYMENT_METHOD_EPS = 'eps';
export const PAYMENT_METHOD_IDEAL = 'ideal';
export const PAYMENT_METHOD_P24 = 'p24';
export const PAYMENT_METHOD_SEPA = 'sepa_debit';
export const PAYMENT_METHOD_SOFORT = 'sofort';
export const PAYMENT_METHOD_BOLETO = 'boleto';
export const PAYMENT_METHOD_OXXO = 'oxxo';
export const PAYMENT_METHOD_BANCONTACT = 'bancontact';
export const PAYMENT_METHOD_ALIPAY = 'alipay';
export const PAYMENT_METHOD_MULTIBANCO = 'multibanco';
export const PAYMENT_METHOD_KLARNA = 'klarna';
export const PAYMENT_METHOD_AFFIRM = 'affirm';
export const PAYMENT_METHOD_AFTERPAY_CLEARPAY = 'afterpay_clearpay';
export const PAYMENT_METHOD_AFTERPAY = 'afterpay';
export const PAYMENT_METHOD_CLEARPAY = 'clearpay';
export const PAYMENT_METHOD_WECHAT_PAY = 'wechat_pay';
export const PAYMENT_METHOD_CASHAPP = 'cashapp';
export const PAYMENT_METHOD_LINK = 'link';

/**
 * Payment method names constants with the `stripe` prefix
 */
export const PAYMENT_METHOD_STRIPE_CARD = 'stripe';
export const PAYMENT_METHOD_STRIPE_GIROPAY = 'stripe_giropay';
export const PAYMENT_METHOD_STRIPE_EPS = 'stripe_eps';
export const PAYMENT_METHOD_STRIPE_IDEAL = 'stripe_ideal';
export const PAYMENT_METHOD_STRIPE_P24 = 'stripe_p24';
export const PAYMENT_METHOD_STRIPE_SEPA = 'stripe_sepa_debit';
export const PAYMENT_METHOD_STRIPE_SOFORT = 'stripe_sofort';
export const PAYMENT_METHOD_STRIPE_BOLETO = 'stripe_boleto';
export const PAYMENT_METHOD_STRIPE_OXXO = 'stripe_oxxo';
export const PAYMENT_METHOD_STRIPE_BANCONTACT = 'stripe_bancontact';
export const PAYMENT_METHOD_STRIPE_ALIPAY = 'stripe_alipay';
export const PAYMENT_METHOD_STRIPE_MULTIBANCO = 'stripe_multibanco';
export const PAYMENT_METHOD_STRIPE_KLARNA = 'stripe_klarna';
export const PAYMENT_METHOD_STRIPE_AFFIRM = 'stripe_affirm';
export const PAYMENT_METHOD_STRIPE_AFTERPAY_CLEARPAY =
	'stripe_afterpay_clearpay';
export const PAYMENT_METHOD_STRIPE_WECHAT_PAY = 'stripe_wechat_pay';
export const PAYMENT_METHOD_STRIPE_CASHAPP = 'stripe_cashapp';

export function getPaymentMethodsConstants() {
	return {
		card: PAYMENT_METHOD_STRIPE_CARD,
		giropay: PAYMENT_METHOD_STRIPE_GIROPAY,
		eps: PAYMENT_METHOD_STRIPE_EPS,
		ideal: PAYMENT_METHOD_STRIPE_IDEAL,
		p24: PAYMENT_METHOD_STRIPE_P24,
		sepa_debit: PAYMENT_METHOD_STRIPE_SEPA,
		sofort: PAYMENT_METHOD_STRIPE_SOFORT,
		boleto: PAYMENT_METHOD_STRIPE_BOLETO,
		oxxo: PAYMENT_METHOD_STRIPE_OXXO,
		bancontact: PAYMENT_METHOD_STRIPE_BANCONTACT,
		alipay: PAYMENT_METHOD_STRIPE_ALIPAY,
		multibanco: PAYMENT_METHOD_STRIPE_MULTIBANCO,
		klarna: PAYMENT_METHOD_STRIPE_KLARNA,
		affirm: PAYMENT_METHOD_STRIPE_AFFIRM,
		afterpay_clearpay: PAYMENT_METHOD_STRIPE_AFTERPAY_CLEARPAY,
		wechat_pay: PAYMENT_METHOD_STRIPE_WECHAT_PAY,
		cashapp: PAYMENT_METHOD_STRIPE_CASHAPP,
	};
}

export const errorTypes = {
	INVALID_EMAIL: 'email_invalid',
	INVALID_REQUEST: 'invalid_request_error',
	API_CONNECTION: 'api_connection_error',
	API_ERROR: 'api_error',
	AUTHENTICATION_ERROR: 'authentication_error',
	RATE_LIMIT_ERROR: 'rate_limit_error',
	CARD_ERROR: 'card_error',
	VALIDATION_ERROR: 'validation_error',
};

export const errorCodes = {
	INVALID_NUMBER: 'invalid_number',
	INVALID_EXPIRY_MONTH: 'invalid_expiry_month',
	INVALID_EXPIRY_YEAR: 'invalid_expiry_year',
	INVALID_CVC: 'invalid_cvc',
	INCORRECT_NUMBER: 'incorrect_number',
	INCOMPLETE_NUMBER: 'incomplete_number',
	INCOMPLETE_CVC: 'incomplete_cvc',
	INCOMPLETE_EXPIRY: 'incomplete_expiry',
	EXPIRED_CARD: 'expired_card',
	INCORRECT_CVC: 'incorrect_cvc',
	INCORRECT_ZIP: 'incorrect_zip',
	INVALID_EXPIRY_YEAR_PAST: 'invalid_expiry_year_past',
	CARD_DECLINED: 'card_declined',
	MISSING: 'missing',
	PROCESSING_ERROR: 'processing_error',
};
