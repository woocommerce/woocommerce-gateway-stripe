import React from 'react';
import { __, sprintf } from '@wordpress/i18n';

export const getModeLabel = ( mode ) =>
	mode === 'live'
		? __( 'live mode', 'woocommerce-gateway-stripe' )
		: __( 'test mode', 'woocommerce-gateway-stripe' );

const getReason = ( reason, mode ) => {
	switch ( reason ) {
		case 'changed_locally':
			return {
				tone: 'woocommerce',
				text: __(
					'Changed in WooCommerce',
					'woocommerce-gateway-stripe'
				),
			};
		case 'changed_in_stripe':
			return {
				tone: 'stripe',
				text: __( 'Changed in Stripe', 'woocommerce-gateway-stripe' ),
			};
		case 'no_baseline':
			return {
				tone: 'neutral',
				text: __(
					'Kept from WooCommerce',
					'woocommerce-gateway-stripe'
				),
			};
		case 'inherited':
			return {
				tone: 'neutral',
				text: __(
					'Stripe default, kept from WooCommerce',
					'woocommerce-gateway-stripe'
				),
			};
		case 'mode_switched':
			return {
				tone: 'warning',
				text: sprintf(
					/* translators: %s: "test mode" or "live mode". */
					__(
						'Restored from Stripe (%s)',
						'woocommerce-gateway-stripe'
					),
					getModeLabel( mode )
				),
			};
		default:
			return null;
	}
};

export const ReasonBadge = ( { reason, mode } ) => {
	const details = getReason( reason, mode );
	if ( ! details ) {
		return null;
	}
	return (
		<span
			className={ `wc-stripe-pmc-review__reason wc-stripe-pmc-review__reason--${ details.tone }` }
		>
			{ details.text }
		</span>
	);
};

export const StatusBadge = ( { isOn } ) => (
	<span
		className={ `wc-stripe-pmc-review__status wc-stripe-pmc-review__status--${
			isOn ? 'on' : 'off'
		}` }
	>
		{ isOn
			? __( 'On', 'woocommerce-gateway-stripe' )
			: __( 'Off', 'woocommerce-gateway-stripe' ) }
	</span>
);
