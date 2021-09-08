/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Pill from '../pill';

// eslint-disable-next-line no-unused-vars
const PaymentMethodFeesPill = ( { id, ...restProps } ) => {
	// get the fees based off on the payment method's id
	// this is obviously hardcoded for testing purposes, since we don't have the fees yet
	const fees = '3.9% + $0.30';

	return (
		<Pill
			{ ...restProps }
			aria-label={ sprintf(
				/* translators: %s: Transaction fee text. */
				__( 'Base transaction fees: %s', 'woocommerce-gateway-stripe' ),
				fees
			) }
		>
			<span>{ fees }</span>
		</Pill>
	);
};

export default PaymentMethodFeesPill;
