import React from 'react';
import { PAYOUT_STATUS_COLORS, PAYOUT_STATUS_LABELS } from './constants';
import EmptyCell from './empty-cell';
import { formatStripeAmount, formatStripeTimestamp } from './utils';
import { __, sprintf } from '@wordpress/i18n';
import Chip from 'wcstripe/components/chip';

/**
 * The list endpoint exposes no sort parameter, so every field pins
 * `enableSorting: false`. Without it DataViews falls back to the field type's
 * default of `true` and renders sort controls that silently do nothing.
 */
const fields = [
	{
		id: 'created',
		label: __( 'Payout date', 'woocommerce-gateway-stripe' ),
		type: 'datetime',
		enableSorting: false,
		enableHiding: true,
		getValue: ( { item } ) => formatStripeTimestamp( item.created ),
	},
	{
		id: 'arrival_date',
		label: __( 'Arrival date', 'woocommerce-gateway-stripe' ),
		type: 'datetime',
		enableSorting: false,
		enableHiding: false,
		getValue: ( { item } ) => formatStripeTimestamp( item.arrival_date ),
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
					text={ PAYOUT_STATUS_LABELS[ item.status ] ?? item.status }
					color={ PAYOUT_STATUS_COLORS[ item.status ] ?? 'gray' }
				/>
			) : (
				<EmptyCell />
			),
	},
	{
		id: 'bank_details',
		label: __( 'Bank details', 'woocommerce-gateway-stripe' ),
		enableSorting: false,
		enableHiding: true,
		getValue: ( { item } ) =>
			sprintf(
				'%s (%s)',
				item.destination?.bank_name ?? '',
				item.destination?.last4 ?? ''
			),
	},
];

export default fields;
