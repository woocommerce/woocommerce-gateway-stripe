import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'wcstripe/data';
import methodsConfiguration from 'wcstripe/payment-methods-map';

const PaymentMethodsDisabledList = () => {
	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();

	// countToDisplay is the number of icons to display.
	const countToDisplay = 3;
	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();
	const disabledMethodIds = availablePaymentMethodIds.filter(
		( methodId ) => ! enabledMethodIds.includes( methodId )
	);
	const disabledMethods = disabledMethodIds
		.filter( ( methodId, idx ) => idx < countToDisplay )
		.map( ( methodId ) => methodsConfiguration[ methodId ] );

	return (
		<ul className="payment-methods__available-methods">
			{ disabledMethods.map( ( { id, label, Icon } ) => (
				<li
					key={ id }
					className="payment-methods__available-method"
					aria-label={ label }
				>
					<Icon height="24" width="38" />
				</li>
			) ) }
			{ disabledMethodIds.length > countToDisplay && (
				<li style={ { margin: '0', lineHeight: '1.5rem' } }>
					{ sprintf(
						/* translators: %d: Number of icons to display. */
						__( '+ %d more', 'woocommerce-gateway-stripe' ),
						disabledMethodIds.length - countToDisplay
					) }
				</li>
			) }
		</ul>
	);
};

export default PaymentMethodsDisabledList;
