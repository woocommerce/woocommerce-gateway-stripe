import { __, sprintf } from '@wordpress/i18n';
import React, { useContext, useState } from 'react';
import styled from '@emotion/styled';
import { CheckboxControl, VisuallyHidden } from '@wordpress/components';
import { Icon, info } from '@wordpress/icons';
import UpeToggleContext from '../upe-toggle/context';
import RemoveMethodConfirmationModal from './remove-method-confirmation-modal';
import { useEnabledPaymentMethodIds, useManualCapture } from 'wcstripe/data';
import Tooltip from 'wcstripe/components/tooltip';

const StyledCheckbox = styled( CheckboxControl )`
	.components-base-control__field {
		margin-bottom: 0;
	}
`;

const AlertIcon = styled( Icon )`
	fill: #ffc83f;
`;

const IconWrapper = styled.span`
	margin-right: 12px;
	flex-shrink: 0;
`;

const PaymentMethodCheckbox = ( { id, label, isAllowingManualCapture } ) => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const [ isManualCaptureEnabled ] = useManualCapture();
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

	if ( ! isUpeEnabled ) {
		return null;
	}

	return (
		<>
			{ isManualCaptureEnabled && ! isAllowingManualCapture ? (
				<Tooltip
					content={ sprintf(
						/* translators: %s: a payment method name. */
						__(
							'%s is not available to your customers when the "manual capture" setting is enabled.',
							'woocommerce-gateway-stripe'
						),
						label
					) }
				>
					{ /* a span element is added here to ensure the tooltip can get the correct content to position itself */ }
					<IconWrapper>
						<AlertIcon icon={ info } />
						<VisuallyHidden>
							{ sprintf(
								/* translators: %s: a payment method name. */
								__(
									'%s cannot be enabled at checkout. Click to expand.'
								),
								label
							) }
						</VisuallyHidden>
					</IconWrapper>
				</Tooltip>
			) : (
				<StyledCheckbox
					label={ <VisuallyHidden>{ label }</VisuallyHidden> }
					onChange={ handleCheckboxChange }
					checked={ enabledPaymentMethods.includes( id ) }
					name={ id }
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
