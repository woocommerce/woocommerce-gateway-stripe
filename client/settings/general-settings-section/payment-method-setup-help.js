/**
 * External dependencies
 */
import React from 'react';
import styled from '@emotion/styled';
import { __ } from '@wordpress/i18n';
import { Icon, info } from '@wordpress/icons';

const Wrapper = styled.div`
	display: flex;
	flex-wrap: nowrap;
	margin-top: 20px;
	align-items: center;
`;

const StyledIcon = styled( Icon )`
	fill: #949494;
	margin-right: 12px;
	flex: 0 0 24px;

	@media ( min-width: 600px ) {
		flex-basis: 20px;
	}
`;

const Text = styled.div`
	color: #757575;
	font-size: 13px;
	line-height: 16px;
`;

const PaymentMethodSetupHelp = ( { id } ) => {
	if ( id !== 'sepa_debit' ) {
		return null;
	}

	return (
		<Wrapper>
			<StyledIcon icon={ info } />
			<Text>
				{ __(
					'You must provide more information to enable Direct debit payment (SEPA).',
					'woocommerce-gateway-stripe'
				) }
			</Text>
		</Wrapper>
	);
};

export default PaymentMethodSetupHelp;
