import { __ } from '@wordpress/i18n';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import React, { useContext, useState } from 'react';
import { CheckboxControl, Button } from '@wordpress/components';
import { Icon, info } from '@wordpress/icons';
import { useManualCapture } from 'wcstripe/data';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

const AlertIcon = styled( Icon )`
	fill: #afafaf;
	position: absolute;
	left: -2.2em;
	width: 1.8em;
	height: auto;
`;

const WarningList = styled.ul`
	padding-left: 2.5em;

	> li {
		position: relative;

		&:first-of-type svg {
			fill: #ffc83f;
		}
	}
`;

const WarningListElement = ( { children } ) => (
	<li>
		<AlertIcon icon={ info } />
		{ children }
	</li>
);

const ManualCaptureControl = () => {
	const [
		isManualCaptureEnabled,
		setIsManualCaptureEnabled,
	] = useManualCapture();
	const { isUpeEnabled } = useContext( UpeToggleContext );

	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);

	const handleCheckboxToggle = ( isChecked ) => {
		// toggling from "manual" capture to "automatic" capture - no need to show the modal.
		if ( ! isChecked || ! isUpeEnabled ) {
			setIsManualCaptureEnabled( isChecked );
			return;
		}
		setIsConfirmationModalOpen( true );
	};

	const handleModalCancel = () => {
		setIsConfirmationModalOpen( false );
	};

	const handleModalConfirmation = () => {
		setIsManualCaptureEnabled( true );
		setIsConfirmationModalOpen( false );
	};

	return (
		<>
			<CheckboxControl
				onChange={ handleCheckboxToggle }
				checked={ isManualCaptureEnabled }
				label={ __(
					'Issue an authorization on checkout, and capture later',
					'woocommerce-gateway-stripe'
				) }
				help={ interpolateComponents( {
					mixedString: __(
						'Charge must be captured on the order details screen within 7 days of authorization, otherwise the authorization and order will be canceled. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						'woocommerce-gateway-stripe'
					),
					components: {
						learnMoreLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a href="https://woocommerce.com/document/stripe/admin-experience/authorize-and-capture/" />
						),
					},
				} ) }
			/>
			{ isConfirmationModalOpen && (
				<ConfirmationModal
					onRequestClose={ handleModalCancel }
					title={ __(
						'Enable manual capture',
						'woocommerce-gateway-stripe'
					) }
					actions={
						<>
							<Button onClick={ handleModalCancel } isSecondary>
								{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
							</Button>
							<Button
								onClick={ handleModalConfirmation }
								isPrimary
							>
								{ __( 'Enable', 'woocommerce-gateway-stripe' ) }
							</Button>
						</>
					}
				>
					<strong>
						{ __(
							'Are you sure you want to enable manual capture of payments?',
							'woocommerce-gateway-stripe'
						) }
					</strong>
					<WarningList>
						<WarningListElement>
							{ __(
								'Only cards, Affirm, Afterpay, and Klarna support manual capture. When enabled, all other payment methods will be hidden from checkout.',
								'woocommerce-gateway-stripe'
							) }
						</WarningListElement>
						<WarningListElement>
							{ __(
								'You must capture the payment on the order details screen within 7 days of authorization, otherwise the money will return to the shopper.',
								'woocommerce-gateway-stripe'
							) }
						</WarningListElement>
					</WarningList>
				</ConfirmationModal>
			) }
		</>
	);
};

export default ManualCaptureControl;
