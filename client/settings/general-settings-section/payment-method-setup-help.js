import { __ } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import Pill from 'wcstripe/components/pill';

const StyledPill = styled( Pill )`
	border: 1px solid #f0b849;
	background-color: #f0b849;
	color: #1e1e1e;
	line-height: 16px;
`;

const PaymentMethodSetupHelp = ( { id } ) => {
	if ( id !== 'sepa_debit' ) {
		return null;
	}

	return (
		<StyledPill>
			{ __( 'Pending activation', 'woocommerce-gateway-stripe' ) }
		</StyledPill>
	);
};

export default PaymentMethodSetupHelp;
