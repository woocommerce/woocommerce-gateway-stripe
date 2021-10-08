import { __, sprintf } from '@wordpress/i18n';
import React, { useContext } from 'react';
import styled from '@emotion/styled';
import Pill from 'wcstripe/components/pill';
import Tooltip from 'wcstripe/components/tooltip';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import { useGetCapabilities } from 'wcstripe/data/account';

const StyledPill = styled( Pill )`
	border: 1px solid #f0b849;
	background-color: #f0b849;
	color: #1e1e1e;
	line-height: 16px;
`;

const PaymentMethodCapabilityStatusPill = ( { id, label } ) => {
	const capabilities = useGetCapabilities();
	const { isUpeEnabled } = useContext( UpeToggleContext );

	if ( ! isUpeEnabled ) {
		return null;
	}

	const capabilityStatus = capabilities[ `${ id }_payments` ];
	if ( capabilityStatus === 'pending' ) {
		return (
			<Tooltip
				content={ sprintf(
					/* translators: %s: a payment method name. */
					__(
						"%s won't be visible to your customers until you provide the required information. Follow the instructions Stripe has sent to your e-mail address."
					),
					label
				) }
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
