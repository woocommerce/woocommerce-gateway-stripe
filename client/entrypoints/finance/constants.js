import { __ } from '@wordpress/i18n';

export const PAYMENT_INTENTS_PATH = '/payment_intents';
export const PAYOUTS_PATH = '/payouts';

/**
 * Page sizes offered to the user. The REST endpoint caps `limit` at 100.
 */
export const PER_PAGE_SIZES = [ 10, 25, 50, 100 ];

export const DEFAULT_PER_PAGE = 25;

/**
 * Stripe payment intent statuses, matching WC_Stripe_Intent_Status.
 */
export const STATUS_LABELS = {
	succeeded: __( 'Succeeded', 'woocommerce-gateway-stripe' ),
	processing: __( 'Processing', 'woocommerce-gateway-stripe' ),
	requires_capture: __( 'Uncaptured', 'woocommerce-gateway-stripe' ),
	requires_action: __( 'Requires action', 'woocommerce-gateway-stripe' ),
	requires_confirmation: __(
		'Requires confirmation',
		'woocommerce-gateway-stripe'
	),
	requires_payment_method: __( 'Failed', 'woocommerce-gateway-stripe' ),
	canceled: __( 'Canceled', 'woocommerce-gateway-stripe' ),
};

/**
 * Chip colours, limited to the palette the shared Chip component supports.
 */
export const STATUS_COLORS = {
	succeeded: 'green',
	processing: 'blue',
	requires_capture: 'yellow',
	requires_action: 'yellow',
	requires_confirmation: 'yellow',
	requires_payment_method: 'red',
	canceled: 'gray',
};

export const PAYOUT_STATUS_COLORS = {
	paid: 'green',
	pending: 'blue',
	incomplete: 'yellow',
	upcoming: 'yellow',
	canceled: 'gray',
};

export const PAYOUT_STATUS_LABELS = {
	paid: __( 'Paid', 'woocommerce-gateway-stripe' ),
	pending: __( 'Pending', 'woocommerce-gateway-stripe' ),
	incomplete: __( 'Incomplete', 'woocommerce-gateway-stripe' ),
	upcoming: __( 'Upcoming', 'woocommerce-gateway-stripe' ),
	canceled: __( 'Canceled', 'woocommerce-gateway-stripe' ),
};

export const CARD_BRAND_LABELS = {
	amex: __( 'American Express', 'woocommerce-gateway-stripe' ),
	diners: __( 'Diners Club', 'woocommerce-gateway-stripe' ),
	discover: __( 'Discover', 'woocommerce-gateway-stripe' ),
	jcb: __( 'JCB', 'woocommerce-gateway-stripe' ),
	mastercard: __( 'Mastercard', 'woocommerce-gateway-stripe' ),
	unionpay: __( 'UnionPay', 'woocommerce-gateway-stripe' ),
	visa: __( 'Visa', 'woocommerce-gateway-stripe' ),
};

/**
 * Wallets are reported on the card object rather than as a payment method type,
 * but they are what the shopper actually used, so they win the label.
 */
export const WALLET_LABELS = {
	apple_pay: __( 'Apple Pay', 'woocommerce-gateway-stripe' ),
	google_pay: __( 'Google Pay', 'woocommerce-gateway-stripe' ),
	link: __( 'Link', 'woocommerce-gateway-stripe' ),
	samsung_pay: __( 'Samsung Pay', 'woocommerce-gateway-stripe' ),
	amazon_pay: __( 'Amazon Pay', 'woocommerce-gateway-stripe' ),
};

export const PAYMENT_METHOD_LABELS = {
	acss_debit: __( 'Pre-authorized debit', 'woocommerce-gateway-stripe' ),
	affirm: __( 'Affirm', 'woocommerce-gateway-stripe' ),
	afterpay_clearpay: __( 'Afterpay/Clearpay', 'woocommerce-gateway-stripe' ),
	alipay: __( 'Alipay', 'woocommerce-gateway-stripe' ),
	au_becs_debit: __( 'BECS Direct Debit', 'woocommerce-gateway-stripe' ),
	bacs_debit: __( 'Bacs Direct Debit', 'woocommerce-gateway-stripe' ),
	bancontact: __( 'Bancontact', 'woocommerce-gateway-stripe' ),
	blik: __( 'BLIK', 'woocommerce-gateway-stripe' ),
	boleto: __( 'Boleto', 'woocommerce-gateway-stripe' ),
	card: __( 'Card', 'woocommerce-gateway-stripe' ),
	cashapp: __( 'Cash App Pay', 'woocommerce-gateway-stripe' ),
	eps: __( 'EPS', 'woocommerce-gateway-stripe' ),
	ideal: __( 'iDEAL', 'woocommerce-gateway-stripe' ),
	klarna: __( 'Klarna', 'woocommerce-gateway-stripe' ),
	link: __( 'Link', 'woocommerce-gateway-stripe' ),
	multibanco: __( 'Multibanco', 'woocommerce-gateway-stripe' ),
	oxxo: __( 'OXXO', 'woocommerce-gateway-stripe' ),
	p24: __( 'Przelewy24', 'woocommerce-gateway-stripe' ),
	sepa_debit: __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' ),
	sofort: __( 'Sofort', 'woocommerce-gateway-stripe' ),
	us_bank_account: __( 'ACH Direct Debit', 'woocommerce-gateway-stripe' ),
	wechat_pay: __( 'WeChat Pay', 'woocommerce-gateway-stripe' ),
};

/**
 * Column ids. `created` is not hideable so the table always keeps an anchor
 * column the reader can orient on.
 */
export const DEFAULT_PAYMENT_INTENTS_VIEW = {
	type: 'table',
	page: 1,
	perPage: DEFAULT_PER_PAGE,
	fields: [
		'created',
		'amount',
		'status',
		'payment_method',
		'customer',
		'description',
	],
	layout: {
		density: 'balanced',
		enableMoving: false,
		styles: {
			created: { width: '15%' },
			amount: { width: '12%', align: 'end' },
			status: { width: '12%' },
			payment_method: { width: '18%' },
			customer: { width: '18%' },
			description: { width: '25%' },
		},
	},
};

/**
 * Column ids. `arrival_date` is not hideable so the table always keeps an anchor
 * column the reader can orient on.
 */
export const DEFAULT_PAYOUTS_VIEW = {
	type: 'table',
	page: 1,
	perPage: DEFAULT_PER_PAGE,
	fields: [ 'created', 'arrival_date', 'amount', 'status', 'bank_details' ],
	layout: {
		density: 'balanced',
		enableMoving: false,
		styles: {
			created: { width: '15%' },
			arrival_date: { width: '15%' },
			amount: { width: '15%', align: 'end' },
			status: { width: '15%' },
			bank_details: { width: '40%' },
		},
	},
};
