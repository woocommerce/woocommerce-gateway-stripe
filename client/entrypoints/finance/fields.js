import React from 'react';
import { STATUS_COLORS, STATUS_LABELS } from './constants';
import {
	formatStripeAmount,
	getPaymentMethodIconKey,
	getPaymentMethodLabel,
} from './utils';
import { __ } from '@wordpress/i18n';
import Chip from 'wcstripe/components/chip';
import paymentMethodIcons from 'wcstripe/payment-method-icons';

const EmptyCell = () => (
	<span aria-hidden="true" className="wc-stripe-payment-details__empty">
		&mdash;
	</span>
);

const PaymentMethodCell = ( { item } ) => {
	const label = getPaymentMethodLabel( item.latest_charge );

	if ( ! label ) {
		return <EmptyCell />;
	}

	const Icon =
		paymentMethodIcons[ getPaymentMethodIconKey( item.latest_charge ) ];

	return (
		<span className="wc-stripe-payment-details__payment-method">
			{ Icon && <Icon /> }
			{ label }
		</span>
	);
};

/**
 * The list endpoint exposes no sort parameter, so every field pins
 * `enableSorting: false`. Without it DataViews falls back to the field type's
 * default of `true` and renders sort controls that silently do nothing.
 */
const fields = [
	{
		id: 'created',
		label: __( 'Date', 'woocommerce-gateway-stripe' ),
		type: 'datetime',
		enableSorting: false,
		enableHiding: false,
		// Stripe reports seconds; the datetime field type needs something
		// getDate() can parse, and formats it in the site's timezone and format.
		getValue: ( { item } ) =>
			item.created ? new Date( item.created * 1000 ).toISOString() : '',
	},
	{
		id: 'amount',
		label: __( 'Amount', 'woocommerce-gateway-stripe' ),
		enableSorting: false,
		enableHiding: false,
		getValue: ( { item } ) => item.amount,
		render: ( { item } ) =>
			formatStripeAmount( item.amount, item.currency ),
	},
	{
		id: 'status',
		label: __( 'Status', 'woocommerce-gateway-stripe' ),
		enableSorting: false,
		enableHiding: true,
		getValue: ( { item } ) => item.status ?? '',
		render: ( { item } ) =>
			item.status ? (
				<Chip
					text={ STATUS_LABELS[ item.status ] ?? item.status }
					color={ STATUS_COLORS[ item.status ] ?? 'gray' }
				/>
			) : (
				<EmptyCell />
			),
	},
	{
		id: 'payment_method',
		label: __( 'Payment method', 'woocommerce-gateway-stripe' ),
		enableSorting: false,
		enableHiding: true,
		getValue: ( { item } ) =>
			item.latest_charge?.payment_method_details?.type ?? '',
		render: PaymentMethodCell,
	},
	{
		id: 'customer',
		label: __( 'Customer', 'woocommerce-gateway-stripe' ),
		enableSorting: false,
		enableHiding: true,
		getValue: ( { item } ) =>
			item.latest_charge?.billing_details?.name ?? '',
		render: ( { item } ) =>
			item.latest_charge?.billing_details?.name || <EmptyCell />,
	},
	{
		id: 'description',
		label: __( 'Description', 'woocommerce-gateway-stripe' ),
		enableSorting: false,
		enableHiding: true,
		getValue: ( { item } ) => item.description ?? '',
		render: ( { item } ) => item.description || <EmptyCell />,
	},
];

export default fields;
