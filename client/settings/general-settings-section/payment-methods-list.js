import React, { useContext } from 'react';
import styled from '@emotion/styled';
import UpeToggleContext from '../upe-toggle/context';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import PaymentMethodCheckbox from './payment-method-checkbox';
import { useGetAvailablePaymentMethodIds } from 'wcstripe/data';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';

const List = styled.ul`
	margin: 0;

	> li {
		margin: 0;
		padding: 16px 24px 14px 24px;

		@media ( min-width: 660px ) {
			padding: 24px 24px 24px 24px;
		}

		&:not( :last-child ) {
			box-shadow: inset 0 -1px 0 #e8eaeb;
		}
	}
`;

const ListElement = styled.li`
	display: flex;
	flex-wrap: nowrap;

	@media ( min-width: 660px ) {
		align-items: center;
	}
`;

const PaymentMethodWrapper = styled.div`
	display: flex;
	flex-direction: column;
	gap: 20px;

	@media ( min-width: 660px ) {
		flex-direction: row;
		flex-wrap: nowrap;
		align-items: center;
	}
`;

const StyledFees = styled( PaymentMethodFeesPill )`
	flex: 1 0 auto;
`;

const GeneralSettingsSection = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();

	return (
		<List>
			{ availablePaymentMethods.map( ( method ) => {
				const { Icon, label, description } = PaymentMethodsMap[
					method
				];

				return (
					<ListElement key={ method }>
						<PaymentMethodCheckbox id={ method } />
						<PaymentMethodWrapper>
							<PaymentMethodDescription
								id={ method }
								Icon={ Icon }
								description={ description }
								label={ label }
							/>
							{ isUpeEnabled && <StyledFees id={ method } /> }
						</PaymentMethodWrapper>
					</ListElement>
				);
			} ) }
		</List>
	);
};

export default GeneralSettingsSection;
