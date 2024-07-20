import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import Pill from 'wcstripe/components/pill';
import Tooltip from 'wcstripe/components/tooltip';
import { useGetCapabilities } from 'wcstripe/data/account';

const StyledPill = styled( Pill )`
	background-color: #fcf9e8;
	border: 1px solid #fcf9e8;
	border-radius: 2px;
	color: #674600;
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

	return (
		<>
			{ capabilityStatus === 'pending' && (
				<Tooltip
					content={ __(
						'This payment method is pending approval. Once approved, you will be able to use it.',
						'woocommerce-gateway-stripe'
					) }
				>
					<StyledPill>
						{ __(
							'Pending approval',
							'woocommerce-gateway-stripe'
						) }
					</StyledPill>
				</Tooltip>
			) }

			{ capabilityStatus === 'inactive' && (
				<Tooltip
					content={ interpolateComponents( {
						mixedString: sprintf(
							/* translators: %s: a payment method name. */
							__(
								'%s requires activation in your {{stripeDashboardLink}}Stripe dashboard{{/stripeDashboardLink}}. Follow the instructions there and check back soon.',
								'woocommerce-gateway-stripe'
							),
							label
						),
						components: {
							stripeDashboardLink: (
								<StyledLink
									href="https://dashboard.stripe.com/settings/payment_methods"
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
				>
					<StyledPill>
						{ __(
							'Requires activation',
							'woocommerce-gateway-stripe'
						) }
					</StyledPill>
				</Tooltip>
			) }
		</>
	);
};

export default PaymentMethodCapabilityStatusPill;
