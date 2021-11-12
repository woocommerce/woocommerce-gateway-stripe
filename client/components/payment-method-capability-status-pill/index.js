import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import Pill from 'wcstripe/components/pill';
import Tooltip from 'wcstripe/components/tooltip';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';

const StyledPill = styled( Pill )`
	border: 1px solid #f0b849;
	background-color: #f0b849;
	color: #1e1e1e;
	line-height: 16px;
`;

const StyledLink = styled.a`
	&,
	&:hover,
	&:visited {
		color: white;
	}
`;

const PaymentMethodCapabilityStatusPill = ( { id, label } ) => {
	const capabilities = useGetCapabilities();
	const capabilityStatus = capabilities[ `${ id }_payments` ];
	const { refreshAccount } = useAccount();

	if ( capabilityStatus === 'pending' || capabilityStatus === 'inactive' ) {
		return (
			<Tooltip
				content={ interpolateComponents( {
					mixedString: sprintf(
						/* translators: %s: a payment method name. */
						__(
							"%s must be activated from the {{stripeDashboardLink}}Stripe dashboard{{/stripeDashboardLink}}. Once it's activated, click {{refreshPaymentMethods}}here{{/refreshPaymentMethods}} to dismiss this notice.",
							'woocommerce-gateway-stripe'
						),
						label
					),
					components: {
						stripeDashboardLink: (
							<StyledLink
								href="https://dashboard.stripe.com/settings/payments"
								target="_blank"
								rel="noreferrer"
								onClick={ ( ev ) => {
									// Stop propagation is necessary so it doesn't trigger the tooltip click event.
									ev.stopPropagation();
								} }
							/>
						),
						refreshPaymentMethods: (
							<StyledLink href="#" onClick={ refreshAccount } />
						),
					},
				} ) }
			>
				<StyledPill>
					{ __( 'Pending activation', 'woocommerce-gateway-stripe' ) }
				</StyledPill>
			</Tooltip>
		);
	}

	return null;
};

export default PaymentMethodCapabilityStatusPill;
