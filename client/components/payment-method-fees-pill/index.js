import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import Pill from '../pill';

// eslint-disable-next-line no-unused-vars, @typescript-eslint/no-unused-vars
const PaymentMethodFeesPill = ( { id, ...restProps } ) => {
	if ( __PAYMENT_METHOD_FEES_ENABLED !== true ) {
		return null;
	}

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
