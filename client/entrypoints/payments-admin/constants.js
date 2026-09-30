import { __ } from '@wordpress/i18n';

export const PAYOUTS_PATH = '/payouts';

/**
 * Page sizes offered to the user. The REST endpoint caps `limit` at 100.
 */
export const PER_PAGE_SIZES = [ 10, 25, 50, 100 ];

export const DEFAULT_PER_PAGE = 25;

/**
 * Badge intents, limited to the values the WordPress UI Badge component supports.
 */
export const PAYOUT_STATUS_BADGE_INTENTS = {
	paid: 'none',
	pending: 'medium',
	in_transit: 'informational',
	canceled: 'draft',
	failed: 'high',
};

export const PAYOUT_STATUS_LABELS = {
	paid: __( 'Paid', 'woocommerce-gateway-stripe' ),
	pending: __( 'Pending', 'woocommerce-gateway-stripe' ),
	in_transit: __( 'In transit', 'woocommerce-gateway-stripe' ),
	canceled: __( 'Canceled', 'woocommerce-gateway-stripe' ),
	failed: __( 'Failed', 'woocommerce-gateway-stripe' ),
};

/**
 * Column ids. `arrival_date` is not hideable so the table always keeps an anchor
 * column the reader can orient on.
 */
export const DEFAULT_PAYOUTS_VIEW = {
	type: 'table',
	page: 1,
	perPage: DEFAULT_PER_PAGE,
	fields: [
		'created',
		'arrival_date',
		'status',
		'bank_details',
		'id',
		'amount',
	],
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
	sort: {
		field: 'created',
		direction: 'desc',
	},
};
