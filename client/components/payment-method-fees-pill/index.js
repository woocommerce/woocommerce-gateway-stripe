/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import styled from '@emotion/styled';

/**
 * Internal dependencies
 */
import Pill from '../pill';
import Tooltip from '../tooltip';

const FeesList = styled.dl`
	display: flex;
	flex-flow: row wrap;
	margin-bottom: 4px;
	margin-top: 0;

	&:last-child {
		margin-bottom: 0;
	}
`;

const FeeTerm = styled.dt`
	margin-right: auto;
`;

const FeesTooltipContent = () => {
	return (
		<FeesList>
			<FeeTerm>Base fee</FeeTerm>
			<dt>1.4% + $0.30</dt>
			<FeeTerm>International payment method fee</FeeTerm>
			<dt>1.5%</dt>
			<FeeTerm>Foreign exchange fee</FeeTerm>
			<dt>1.0%</dt>
			<FeeTerm>Total per transaction</FeeTerm>
			<dt>
				<strong>3.9% + $0.30</strong>
			</dt>
		</FeesList>
	);
};

// eslint-disable-next-line no-unused-vars
const PaymentMethodFeesPill = ( { id, ...restProps } ) => {
	// get the fees based off on the payment method's id
	// this is obviously hardcoded for testing purposes, since we don't have the fees yet
	const fees = '3.9% + $0.30';

	return (
		<Tooltip maxWidth="300px" content={ <FeesTooltipContent /> }>
			<Pill
				{ ...restProps }
				aria-label={ sprintf(
					/* translators: %s: Transaction fee text. */
					__(
						'Base transaction fees: %s',
						'woocommerce-gateway-stripe'
					),
					fees
				) }
			>
				<span>{ fees }</span>
			</Pill>
		</Tooltip>
	);
};

export default PaymentMethodFeesPill;
