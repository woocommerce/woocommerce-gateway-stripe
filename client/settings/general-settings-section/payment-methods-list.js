import React, { useContext, useState } from 'react';
import styled from '@emotion/styled';
import { CheckboxControl, VisuallyHidden } from '@wordpress/components';
import UpeToggleContext from '../upe-toggle/context';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import RemoveMethodConfirmationModal from './remove-method-confirmation-modal';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from 'wcstripe/data';
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
	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);
	const [ modalOpenForMethod, setModalOpenForMethod ] = useState( null );

	const [
		enabledPaymentMethods,
		setEnabledPaymentMethods,
	] = useEnabledPaymentMethodIds();
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();

	const makeHandleCheckboxChange = ( method ) => ( hasBeenChecked ) => {
		if ( hasBeenChecked ) {
			setEnabledPaymentMethods( [ ...enabledPaymentMethods, method ] );
		} else {
			setIsConfirmationModalOpen( true );
			setModalOpenForMethod( method );
		}
	};

	const handleRemoveMethod = ( method ) => {
		setIsConfirmationModalOpen( false );
		setModalOpenForMethod( null );
		setEnabledPaymentMethods(
			enabledPaymentMethods.filter( ( m ) => m !== method )
		);
	};

	return (
		<List>
			{ availablePaymentMethods.map( ( method ) => {
				const { Icon, label, description } = PaymentMethodsMap[
					method
				];

				return (
					<ListElement key={ method }>
						{ isUpeEnabled && (
							<CheckboxControl
								label={
									<VisuallyHidden>{ label }</VisuallyHidden>
								}
								onChange={ makeHandleCheckboxChange( method ) }
								checked={ enabledPaymentMethods.includes(
									method
								) }
							/>
						) }
						<PaymentMethodWrapper>
							<PaymentMethodDescription
								id={ isUpeEnabled ? method : null }
								Icon={ Icon }
								description={ description }
								label={ label }
							/>
							{ isUpeEnabled && <StyledFees id={ method } /> }
						</PaymentMethodWrapper>
					</ListElement>
				);
			} ) }
			{ isConfirmationModalOpen && (
				<RemoveMethodConfirmationModal
					method={ modalOpenForMethod }
					onConfirm={ () => setIsConfirmationModalOpen( false ) }
					handleRemove={ () =>
						handleRemoveMethod( modalOpenForMethod )
					}
				/>
			) }
		</List>
	);
};

export default GeneralSettingsSection;
