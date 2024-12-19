import { __ } from '@wordpress/i18n';
import React from 'react';
import { Button } from '@wordpress/components';
import {
	useEnabledPaymentMethodIds,
	usePaymentRequestEnabledSettings,
} from '../../data';
import PaymentMethodIcon from '../../settings/payment-method-icon';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import './style.scss';
import AlertTitle from 'wcstripe/components/confirmation-modal/alert-title';
import { PAYMENT_METHOD_LINK } from 'wcstripe/stripe-utils/constants';

const DisableConfirmationModal = ( { onClose, onConfirm } ) => {
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const [
		paymentRequestEnabledSettings,
	] = usePaymentRequestEnabledSettings();

	const enabledMethodIds = enabledPaymentMethodIds.filter(
		( methodId ) => methodId !== PAYMENT_METHOD_LINK
	);

	const mainDialogText =
		enabledMethodIds.length === 0 && paymentRequestEnabledSettings
			? __(
					'Are you sure you want to disable Stripe? Without it, your customers will no longer be able to pay using the express checkouts.',
					'woocommerce-gateway-stripe'
			  )
			: __(
					'Are you sure you want to disable Stripe? Without it, your customers will no longer be able to pay using the payment methods below as well as express checkouts.',
					'woocommerce-gateway-stripe'
			  );

	return (
		<ConfirmationModal
			title={
				<AlertTitle
					title={ __(
						'Disable Stripe',
						'woocommerce-gateway-stripe'
					) }
				/>
			}
			className="disable-confirmation-modal"
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
			<p>{ mainDialogText }</p>
			{ enabledMethodIds.length > 0 && (
				<>
					<p>
						{ __(
							'Payment methods that need Stripe:',
							'woocommerce-gateway-stripe'
						) }
					</p>
					<ul className="disable-confirmation-modal__payment-methods-list">
						{ enabledMethodIds.map( ( methodId ) => {
							return (
								<li key={ methodId }>
									<PaymentMethodIcon
										name={ methodId }
										showName
									/>
								</li>
							);
						} ) }
					</ul>
				</>
			) }
		</ConfirmationModal>
	);
};

export default DisableConfirmationModal;
