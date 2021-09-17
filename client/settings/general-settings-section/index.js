import React, { useContext } from 'react';
import styled from '@emotion/styled';
import { Card, CheckboxControl } from '@wordpress/components';
import CardBody from '../card-body';
import UpeToggleContext from '../upe-toggle/context';
import PaymentMethodsMap from '../../payment-methods-map';
import UpeOptInBanner from './upe-opt-in-banner';
import PaymentMethodDescription from './payment-method-description';
import SectionHeading from './section-heading';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'wcstripe/data';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const PaymentMethodsList = styled.ul`
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

const PaymentMethodWrapper = styled.div`
	display: flex;
	flex-direction: column;
	flex-wrap: nowrap;

	@media ( min-width: 660px ) {
		flex-direction: row;
		align-items: center;
	}
`;

const StyledFees = styled( PaymentMethodFeesPill )`
	flex: 1 0 auto;
	margin-top: 20px;
	margin-left: 32px;

	@media ( min-width: 660px ) {
		margin-top: 0;
		margin-left: 24px;
	}
`;

const PaymentMethodCheckbox = styled( CheckboxControl )`
	.components-base-control__field {
		margin: 0;
		display: flex;

		@media ( min-width: 660px ) {
			align-items: center;
		}
	}
`;

const GeneralSettingsSection = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	const [
		enabledPaymentMethods,
		setEnabledPaymentMethods,
	] = useEnabledPaymentMethodIds();
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();

	const makeCheckboxChangeHandler = ( method ) => ( hasBeenChecked ) => {
		if ( hasBeenChecked ) {
			setEnabledPaymentMethods( [ ...enabledPaymentMethods, method ] );
		} else {
			setEnabledPaymentMethods(
				enabledPaymentMethods.filter( ( m ) => m !== method )
			);
		}
	};

	return (
		<>
			<StyledCard>
				<SectionHeading />
				<CardBody size={ null }>
					<PaymentMethodsList>
						{ availablePaymentMethods.map( ( method ) => {
							const {
								Icon,
								label,
								description,
							} = PaymentMethodsMap[ method ];

							return (
								<li key={ method }>
									<PaymentMethodWrapper>
										{ isUpeEnabled ? (
											<>
												<PaymentMethodCheckbox
													label={
														<PaymentMethodDescription
															id={ method }
															Icon={ Icon }
															description={
																description
															}
															label={ label }
														/>
													}
													onChange={ makeCheckboxChangeHandler(
														method
													) }
													checked={ enabledPaymentMethods.includes(
														method
													) }
												/>
												<StyledFees id={ method } />
											</>
										) : (
											<PaymentMethodDescription
												Icon={ Icon }
												description={ description }
												label={ label }
											/>
										) }
									</PaymentMethodWrapper>
								</li>
							);
						} ) }
					</PaymentMethodsList>
				</CardBody>
			</StyledCard>
			<UpeOptInBanner />
		</>
	);
};

export default GeneralSettingsSection;
