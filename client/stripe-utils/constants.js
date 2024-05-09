export const PAYMENT_METHOD_NAME_CARD = 'stripe';
export const PAYMENT_METHOD_NAME_GIROPAY = 'stripe_giropay';
export const PAYMENT_METHOD_NAME_EPS = 'stripe_eps';
export const PAYMENT_METHOD_NAME_IDEAL = 'stripe_ideal';
export const PAYMENT_METHOD_NAME_P24 = 'stripe_p24';
export const PAYMENT_METHOD_NAME_SEPA = 'stripe_sepa_debit';
export const PAYMENT_METHOD_NAME_SOFORT = 'stripe_sofort';
export const PAYMENT_METHOD_NAME_BOLETO = 'stripe_boleto';
export const PAYMENT_METHOD_NAME_OXXO = 'stripe_oxxo';
export const PAYMENT_METHOD_NAME_BANCONTACT = 'stripe_bancontact';
export const PAYMENT_METHOD_NAME_ALIPAY = 'stripe_alipay';
export const PAYMENT_METHOD_NAME_KLARNA = 'stripe_klarna';
export const PAYMENT_METHOD_NAME_AFFIRM = 'stripe_affirm';
export const PAYMENT_METHOD_NAME_AFTERPAY_CLEARPAY = 'stripe_afterpay_clearpay';

export function getPaymentMethodsConstants() {
	return {
		card: PAYMENT_METHOD_NAME_CARD,
		giropay: PAYMENT_METHOD_NAME_GIROPAY,
		eps: PAYMENT_METHOD_NAME_EPS,
		ideal: PAYMENT_METHOD_NAME_IDEAL,
		p24: PAYMENT_METHOD_NAME_P24,
		sepa_debit: PAYMENT_METHOD_NAME_SEPA,
		sofort: PAYMENT_METHOD_NAME_SOFORT,
		boleto: PAYMENT_METHOD_NAME_BOLETO,
		oxxo: PAYMENT_METHOD_NAME_OXXO,
		bancontact: PAYMENT_METHOD_NAME_BANCONTACT,
		alipay: PAYMENT_METHOD_NAME_ALIPAY,
		klarna: PAYMENT_METHOD_NAME_KLARNA,
		affirm: PAYMENT_METHOD_NAME_AFFIRM,
		afterpay_clearpay: PAYMENT_METHOD_NAME_AFTERPAY_CLEARPAY,
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
