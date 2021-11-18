import { __, _n, sprintf } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import PaymentMethodsMap from '../../payment-methods-map';
import Pill from 'wcstripe/components/pill';
import Tooltip from 'wcstripe/components/tooltip';

const StyledPill = styled( Pill )`
	border: 1px solid #f0b849;
	background-color: #f0b849;
	color: #1e1e1e;
	line-height: 16px;
`;

const PaymentMethodMissingCurrencyPill = ( { id, label } ) => {
	const paymentMethodCurrencies = PaymentMethodsMap[ id ]?.currencies || [];
	const storeCurrency = window?.wcSettings?.currency?.code;

	if (
		id !== 'card' &&
		! paymentMethodCurrencies.includes( storeCurrency )
	) {
		return (
			<Tooltip
				content={ sprintf(
					/* translators: $1: a payment method name. %2: Currency(ies). */
					_n(
						"%1$s won't be visible to your customers until you add %2$s to your store.",
						"%1$s won't be visible to your customers until you add one of these currencies to your store: %2$s.",
						paymentMethodCurrencies.length,
						'woocommerce-gateway-stripe'
					),
					label,
					paymentMethodCurrencies.join( ', ' )
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
