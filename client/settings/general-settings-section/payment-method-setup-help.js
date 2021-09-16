/**
 * External dependencies
 */
import React from 'react';
import styled from '@emotion/styled';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Pill from 'wcstripe/components/pill';
import Tooltip from 'wcstripe/components/tooltip';

const StyledPill = styled( Pill )`
	border: 1px solid #f0b849;
	background-color: #f0b849;
	color: #1e1e1e;
	line-height: 16px;
`;

const PaymentMethodSetupHelp = ( { id, label } ) => {
	if ( id !== 'sepa_debit' ) {
		return null;
	}

	return (
		<Tooltip
			content={ sprintf(
				/* translators: %s: a payment method name. */
				__(
					"%s won't be visible to your customers until you provide the required information. Follow the instructions sent by our partner Stripe to your email address."
				),
				label
			) }
		>
			<StyledPill>
				{ __( 'Pending activation', 'woocommerce-gateway-stripe' ) }
			</StyledPill>
		</Tooltip>
	);
};

export default PaymentMethodSetupHelp;
