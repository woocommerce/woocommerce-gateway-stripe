import React from 'react';
import styled from '@emotion/styled';
import PaymentMethodMissingCurrencyPill from '../../components/payment-method-missing-currency-pill';
import PaymentMethodCapabilityStatusPill from 'wcstripe/components/payment-method-capability-status-pill';
import PaymentMethodDeprecationPill from 'wcstripe/components/payment-method-deprecation-pill';

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

const LabelWrapper = styled.div`
	display: inline-flex;
	align-items: center;
	margin-bottom: 4px;
	gap: 8px;
	flex-wrap: wrap;
`;

const Label = styled.span`
	color: #1e1e1e;
	font-size: 14px;
	font-weight: 600;
	line-height: 20px;
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
	id,
	deprecated,
	...restProps
} ) => {
	return (
		<Wrapper { ...restProps }>
			<IconWrapper>
				<Icon size="medium" alt={ label } />
			</IconWrapper>
			<div>
				<LabelWrapper>
					<Label>{ label }</Label>
					{ deprecated && <PaymentMethodDeprecationPill /> }
					{ ! deprecated && (
						<>
							<PaymentMethodMissingCurrencyPill
								id={ id }
								label={ label }
							/>
							<PaymentMethodCapabilityStatusPill
								id={ id }
								label={ label }
							/>
						</>
					) }
				</LabelWrapper>
				<Description>{ description }</Description>
			</div>
		</Wrapper>
	);
};

export default PaymentMethodDescription;
