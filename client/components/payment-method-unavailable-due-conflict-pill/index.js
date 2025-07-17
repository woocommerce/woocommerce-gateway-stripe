/* global wc_stripe_settings_params */
import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import { Icon, info } from '@wordpress/icons';
import Popover from 'wcstripe/components/popover';
import {
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_KLARNA,
} from 'wcstripe/stripe-utils/constants';

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

const PaymentMethodUnavailableDueConflictPill = ( { id, label } ) => {
	if (
		( id === PAYMENT_METHOD_AFFIRM &&
			// eslint-disable-next-line camelcase
			wc_stripe_settings_params.has_affirm_gateway_plugin ) ||
		( id === PAYMENT_METHOD_KLARNA &&
			// eslint-disable-next-line camelcase
			wc_stripe_settings_params.has_klarna_gateway_plugin )
	) {
		return (
			<StyledPill>
				{ __( 'Has plugin conflict', 'woocommerce-gateway-stripe' ) }
				<Popover
					BaseComponent={ IconComponent }
					content={ interpolateComponents( {
						mixedString: sprintf(
							/* translators: $1: a payment method name */
							__(
								'%1$s is unavailable due to another official plugin being active.',
								'woocommerce-gateway-stripe'
							),
							label
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

export default PaymentMethodUnavailableDueConflictPill;
