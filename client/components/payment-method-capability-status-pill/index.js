import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import { Icon, info } from '@wordpress/icons';
import Pill from 'wcstripe/components/pill';
import Popover from 'wcstripe/components/popover';
import { useGetCapabilities } from 'wcstripe/data/account';

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

const PaymentMethodCapabilityStatusPill = ( { id, label } ) => {
	const capabilities = useGetCapabilities();
	const capabilityStatus = capabilities[ `${ id }_payments` ];

	return (
		<>
			{ capabilityStatus === 'pending' && (
				<StyledPill>
					{ __( 'Pending approval', 'woocommerce-gateway-stripe' ) }

					<Popover
						BaseComponent={ IconComponent }
						content={ interpolateComponents( {
							mixedString: sprintf(
								/* translators: %s: a payment method name. */
								__(
									'%s is {{stripeDashboardLink}}pending approval{{/stripeDashboardLink}}. Once approved, you will be able to use it.',
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
					/>
				</StyledPill>
			) }

			{ capabilityStatus === 'inactive' && (
				<StyledPill>
					{ __(
						'Requires activation',
						'woocommerce-gateway-stripe'
					) }

					<Popover
						BaseComponent={ IconComponent }
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
					/>
				</StyledPill>
			) }
		</>
	);
};

export default PaymentMethodCapabilityStatusPill;
