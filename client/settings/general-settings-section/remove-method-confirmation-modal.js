import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import { Button } from '@wordpress/components';
import PaymentMethodsMap from '../../payment-methods-map';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import AlertTitle from 'wcstripe/components/confirmation-modal/alert-title';

const RemoveMethodConfirmationModal = ( { method, onClose, handleRemove } ) => {
	const { label } = PaymentMethodsMap[ method ];
	return (
		<ConfirmationModal
			title={
				<AlertTitle
					title={ sprintf(
						/* translators: %s: payment method name (e.g.: giropay, EPS, Sofort, etc). */
						__(
							'Remove %s from checkout',
							'woocommerce-gateway-stripe'
						),
						label
					) }
				/>
			}
			onRequestClose={ onClose }
			actions={
				<>
					<Button isSecondary onClick={ onClose }>
						{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
					</Button>
					<Button isPrimary onClick={ handleRemove }>
						{ __( 'Remove', 'woocommerce-gateway-stripe' ) }
					</Button>
				</>
			}
		>
			<p>
				{ sprintf(
					/* translators: %1: payment method name (e.g.: giropay, EPS, Sofort, etc). */
					__(
						'Are you sure you want to remove %1$s? Your customers will no longer be able to pay using %1$s.',
						'woocommerce-gateway-stripe'
					),
					label
				) }
			</p>
			<p>
				{ __(
					'You can add it again at any time in Stripe settings.',
					'woocommerce-gateway-stripe'
				) }
			</p>
		</ConfirmationModal>
	);
};

export default RemoveMethodConfirmationModal;
