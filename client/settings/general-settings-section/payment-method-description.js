/**
 * External dependencies
 */
import React from 'react';
import styled from '@emotion/styled';

const Wrapper = styled.div`
	display: flex;
	align-items: center;
`;

const IconWrapper = styled.div`
	display: none;
	margin-right: 14px;

	@media ( min-width: 660px ) {
		display: block;
	}
`;

const Label = styled.div`
	color: #1e1e1e;
	display: inline-block;
	font-size: 14px;
	font-weight: 600;
	line-height: 20px;
	margin-bottom: 4px;
`;

const Description = styled.div`
	color: #757575;
	font-size: 13px;
	line-height: 16px;
`;

const PaymentMethodDescription = ( {
	Icon = () => null,
	label,
	description,
	...restProps
} ) => {
	return (
		<Wrapper { ...restProps }>
			<IconWrapper>
				<Icon size="medium" />
			</IconWrapper>
			<div>
				<Label>{ label }</Label>
				<Description>{ description }</Description>
			</div>
		</Wrapper>
	);
};

export default PaymentMethodDescription;
