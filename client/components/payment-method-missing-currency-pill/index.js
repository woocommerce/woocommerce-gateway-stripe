import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import { Icon, info } from '@wordpress/icons';
import Popover from 'wcstripe/components/popover';
import { usePaymentMethodCurrencies } from 'utils/use-payment-method-currencies';
import { PAYMENT_METHOD_CARD } from 'wcstripe/stripe-utils/constants';

const StyledPill = styled.span`
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 4px 8px;
	border: 1px solid #fcf9e8;
	border-radius: 2px;
	background-color: #fcf9e8;
	color: #674600;
	font-size: 12px;
	font-weight: 400;
	line-height: 16px;
	width: fit-content;
`;

const StyledLink = styled.a`
	&:focus,
	&:visited {
		box-shadow: none;
	}
`;

const IconWrapper = styled.span`
	height: 16px;
	cursor: pointer;
`;

const AlertIcon = styled( Icon )`
	fill: #674600;
`;

const IconComponent = ( { children, ...props } ) => (
	<IconWrapper { ...props }>
		<AlertIcon icon={ info } size="16" />
		{ children }
	</IconWrapper>
);

const PaymentMethodMissingCurrencyPill = ( { id, label } ) => {
	const paymentMethodCurrencies = usePaymentMethodCurrencies( id );
	const storeCurrency = window?.wcSettings?.currency?.code;

	if (
		id !== PAYMENT_METHOD_CARD &&
		! paymentMethodCurrencies.includes( storeCurrency )
	) {
		return (
			<StyledPill>
				{ __( 'Requires currency', 'woocommerce-gateway-stripe' ) }
				<Popover
					BaseComponent={ IconComponent }
					content={ interpolateComponents( {
						mixedString: sprintf(
							/* translators: $1: a payment method name. %2: Currency(ies). */
							__(
								'%1$s requires store currency to be set to %2$s. {{currencySettingsLink}}Set currency{{/currencySettingsLink}}',
								'woocommerce-gateway-stripe'
							),
							label,
							paymentMethodCurrencies.join( ', ' )
						),
						components: {
							currencySettingsLink: (
								<StyledLink
									href="/wp-admin/admin.php?page=wc-settings&tab=general"
									target="_blank"
									rel="noreferrer"
									onClick={ ( ev ) => {
										// Stop propagation is necessary so it doesn't trigger the tooltip click event.
										ev.stopPropagation();
									} }
								/>
							),
						},
					} ) }
				/>
			</StyledPill>
		);
	}

	return null;
};

export default PaymentMethodMissingCurrencyPill;
