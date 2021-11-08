import { __, sprintf } from '@wordpress/i18n';
import React, { useContext, useMemo } from 'react';
import styled from '@emotion/styled';
import PaymentMethodsMap from '../../payment-methods-map';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import UpeToggleContext from '../../settings/upe-toggle/context';
import Pill from 'wcstripe/components/pill';
import Tooltip from 'wcstripe/components/tooltip';

const StyledPill = styled( Pill )`
	border: 1px solid #f0b849;
	background-color: #f0b849;
	color: #1e1e1e;
	line-height: 16px;
`;

const PaymentMethodMissingCurrencyPill = ( { id, label } ) => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const { multiCurrency } = useContext( WCPaySettingsContext );
	const paymentMethodCurrencies = useMemo(
		() => PaymentMethodsMap[ id ]?.currencies || [],
		[ id ]
	);
	const storeCurrency = window?.wcSettings?.currency?.code;

	// adding support to multi-currency in case WooCommerce Payments is installed
	const enabledCurrencies = useMemo(
		() => Object.keys( multiCurrency?.enabled || {} ),
		[ multiCurrency ]
	);
	const isMultiCurrencyEnabled = enabledCurrencies.length > 0;
	// intersect all enabled currencies with the payment method currencies
	const multiCurrencyIntersection = useMemo( () => {
		if ( isMultiCurrencyEnabled ) {
			return enabledCurrencies.filter( ( currency ) =>
				paymentMethodCurrencies.includes( currency )
			);
		}
		return [];
	}, [ isMultiCurrencyEnabled, enabledCurrencies, paymentMethodCurrencies ] );

	if ( ! isUpeEnabled ) {
		return null;
	}

	if (
		id !== 'card' &&
		! (
			paymentMethodCurrencies.includes( storeCurrency ) ||
			multiCurrencyIntersection.length
		)
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
