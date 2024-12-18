import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import { useGetAvailablePaymentMethodIds } from 'wcstripe/data';
import { useGetCapabilities } from 'wcstripe/data/account';
import methodsConfiguration from 'wcstripe/payment-methods-map';
import { PAYMENT_METHOD_LINK } from 'wcstripe/stripe-utils/constants';

const PaymentMethodsUnavailableList = () => {
	const countIconsToDisplay = 3;
	const capabilities = useGetCapabilities();
	const upePaymentMethodIds = useGetAvailablePaymentMethodIds();
	const unavailablePaymentMethodIds = upePaymentMethodIds
		.filter(
			( methodId ) =>
				! capabilities.hasOwnProperty( `${ methodId }_payments` )
		)
		.filter( ( id ) => id !== PAYMENT_METHOD_LINK );
	const unavailablePaymentMethods = unavailablePaymentMethodIds
		.filter( ( methodId, idx ) => idx < countIconsToDisplay )
		.map( ( methodId ) => methodsConfiguration[ methodId ] );

	return (
		<ul
			className="payment-methods__unavailable-methods"
			data-testid="unavailable-payment-methods-list"
		>
			{ unavailablePaymentMethods.map( ( { id, label, Icon } ) => (
				<li
					key={ id }
					className="payment-methods__unavailable-method"
					aria-label={ label }
				>
					<Icon height="24" width="38" alt={ label } />
				</li>
			) ) }
			{ unavailablePaymentMethodIds.length > countIconsToDisplay && (
				<li
					style={ { margin: '0', lineHeight: '1.5rem' } }
					data-testid="unavailable-payment-methods-more"
				>
					{ sprintf(
						/* translators: %d: Number of unavailable payment methods not displayed. */
						__( '+ %d more', 'woocommerce-gateway-stripe' ),
						unavailablePaymentMethodIds.length - countIconsToDisplay
					) }
				</li>
			) }
		</ul>
	);
};

export default PaymentMethodsUnavailableList;
