import { __ } from '@wordpress/i18n';

export const PAYOUTS_PATH = '/payouts';

/**
 * Page sizes offered to the user. The REST endpoint caps `limit` at 100.
 */
export const PER_PAGE_SIZES = [ 10, 25, 50, 100 ];

export const DEFAULT_PER_PAGE = 25;

/**
 * Chip colours, limited to the palette the shared Chip component supports.
 */
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
};
