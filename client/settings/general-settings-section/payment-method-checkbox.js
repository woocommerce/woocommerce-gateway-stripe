import React, { useContext, useState } from 'react';
import styled from '@emotion/styled';
import { CheckboxControl, VisuallyHidden } from '@wordpress/components';
import UpeToggleContext from '../upe-toggle/context';
import PaymentMethodsMap from '../../payment-methods-map';
import RemoveMethodConfirmationModal from './remove-method-confirmation-modal';
import { useEnabledPaymentMethodIds } from 'wcstripe/data';

const StyledCheckbox = styled( CheckboxControl )`
	.components-base-control__field {
		margin-bottom: 0;
	}
`;

const PaymentMethodCheckbox = ( { id } ) => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);

	const [
		enabledPaymentMethods,
		setEnabledPaymentMethods,
	] = useEnabledPaymentMethodIds();

	const handleCheckboxChange = ( hasBeenChecked ) => {
		if ( ! hasBeenChecked ) {
			setIsConfirmationModalOpen( true );
			return;
		}

		setEnabledPaymentMethods( [ ...enabledPaymentMethods, id ] );
	};

	const handleRemovalConfirmation = () => {
		setIsConfirmationModalOpen( false );
		setEnabledPaymentMethods(
			enabledPaymentMethods.filter( ( m ) => m !== id )
		);
	};

	const { label } = PaymentMethodsMap[ id ];

	return (
		<>
			{ isUpeEnabled && (
				<StyledCheckbox
					label={ <VisuallyHidden>{ label }</VisuallyHidden> }
					onChange={ handleCheckboxChange }
					checked={ enabledPaymentMethods.includes( id ) }
				/>
			) }
			{ isConfirmationModalOpen && (
				<RemoveMethodConfirmationModal
					method={ id }
					onClose={ () => setIsConfirmationModalOpen( false ) }
					onConfirm={ handleRemovalConfirmation }
				/>
			) }
		</>
	);
};

export default PaymentMethodCheckbox;
