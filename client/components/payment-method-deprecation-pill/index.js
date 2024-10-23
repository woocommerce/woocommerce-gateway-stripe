import { __ } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import { Icon, info } from '@wordpress/icons';
import interpolateComponents from 'interpolate-components';
import Pill from 'wcstripe/components/pill';
import Popover from 'wcstripe/components/popover';

const StyledPill = styled( Pill )`
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

const PaymentMethodDeprecationPill = () => {
	return (
		<StyledPill>
			{ __( 'Deprecated', 'woocommerce-gateway-stripe' ) }
			<Popover
				BaseComponent={ IconComponent }
				content={ interpolateComponents( {
					mixedString:
						/* translators: $1: a payment method name. %2: Currency(ies). */
						__(
							'This payment method is deprecated on the {{currencySettingsLink}}legacy checkout as of Oct 29th, 2024{{/currencySettingsLink}}.',
							'woocommerce-gateway-stripe'
						),
					components: {
						currencySettingsLink: (
							<StyledLink
								href="https://support.stripe.com/topics/shutdown-of-the-legacy-sources-api-for-non-card-payment-methods"
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
};

export default PaymentMethodDeprecationPill;
