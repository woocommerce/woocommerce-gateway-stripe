import { __, sprintf } from '@wordpress/i18n';
import React, { useContext } from 'react';
import styled from '@emotion/styled';
import PaymentMethodsMap from '../../payment-methods-map';
import Pill from 'wcstripe/components/pill';
import Tooltip from 'wcstripe/components/tooltip';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

const StyledPill = styled( Pill )`
	border: 1px solid #f0b849;
	background-color: #f0b849;
	color: #1e1e1e;
	line-height: 16px;
`;

const PaymentMethodMissingCurrencyPill = ( { id, label } ) => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const paymentMethodCurrencies = PaymentMethodsMap[ id ]?.currencies || [];
	const storeCurrency = window?.wcSettings?.currency?.code;

	if ( ! isUpeEnabled ) {
		return null;
	}

	if (
		id !== 'card' &&
		! paymentMethodCurrencies.includes( storeCurrency )
	) {
		return (
			<Tooltip
				content={ sprintf(
					/* translators: $1: a payment method name. %2: Currency. */
					__(
						"%1$s won't be visible to your customers until you add %2$s to your store."
					),
					label,
					paymentMethodCurrencies[ 0 ]
				) }
			>
				<StyledPill>
					{ __( 'Requires currency', 'woocommerce-gateway-stripe' ) }
				</StyledPill>
			</Tooltip>
		);
	}

	return null;
};

export default PaymentMethodMissingCurrencyPill;
