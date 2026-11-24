import React from 'react';
import clsx from 'clsx';
import paymentMethodsMap from '../../payment-methods-map';
import ApplePayIcon from '../../payment-method-icons/apple-pay';
import GooglePayIcon from '../../payment-method-icons/google-pay';
import { __ } from '@wordpress/i18n';

// Wallets are not UPE methods, so they are missing from the payment methods map.
const wallets = {
	apple_pay: {
		label: __( 'Apple Pay', 'woocommerce-gateway-stripe' ),
		Icon: ApplePayIcon,
	},
	google_pay: {
		label: __( 'Google Pay', 'woocommerce-gateway-stripe' ),
		Icon: GooglePayIcon,
	},
};

const getMethod = ( id ) => paymentMethodsMap[ id ] || wallets[ id ] || {};

export const getMethodLabel = ( id ) => getMethod( id ).label || id;

const MethodLabel = ( { id, size = 'medium', className, children } ) => {
	const { Icon } = getMethod( id );
	const label = getMethodLabel( id );

	return (
		<div
			className={ clsx(
				'wc-stripe-pmc-review__method',
				`wc-stripe-pmc-review__method--${ size }`,
				className
			) }
		>
			<span className="wc-stripe-pmc-review__method-icon">
				{ Icon && <Icon size={ size } alt="" /> }
			</span>
			<span className="wc-stripe-pmc-review__method-text">
				<span className="wc-stripe-pmc-review__method-name">
					{ label }
				</span>
				{ children }
			</span>
		</div>
	);
};

export default MethodLabel;
