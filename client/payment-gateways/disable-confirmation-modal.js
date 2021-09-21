/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import React from 'react';
import { Button } from '@wordpress/components';
/**
 * Internal dependencies
 */
import './style.scss';
import { useEnabledPaymentMethodIds } from '../data';
import PaymentMethodIcon from '../settings/payment-method-icon';
import ConfirmationModal from '../components/confirmation-modal';

const DisableConfirmationModal = ( { onClose, onConfirm } ) => {
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();

	return (
		<ConfirmationModal
			title={ __( 'Disable Stripe', 'woocommerce-payments' ) }
			className="alert"
			onRequestClose={ onClose }
			actions={
				<>
					<Button onClick={ onConfirm } isPrimary isDestructive>
						Disable
					</Button>
					<Button onClick={ onClose } isSecondary>
						Cancel
					</Button>
				</>
			}
		>
			<p>
				{ __(
					'Are you sure you want to disable Stripe? Without it, your customers will no longer be able to pay using the payment methods below as well as express checkouts.',
					'woocommerce-gateway-stripe'
				) }
			</p>
			<p>
				{ __(
					'Payment methods that need Stripe:',
					'woocommerce-gateway-stripe'
				) }
			</p>
			<ul className="disable-confirmation-modal__payment-methods-list">
				{ enabledPaymentMethodIds.map( ( methodId ) => {
					return (
						<li key={ methodId }>
							<PaymentMethodIcon name={ methodId } showName />
						</li>
					);
				} ) }
			</ul>
		</ConfirmationModal>
	);
};

export default DisableConfirmationModal;
