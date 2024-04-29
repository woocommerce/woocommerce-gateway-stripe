import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import React from 'react';
import { Button } from '@wordpress/components';
import PaymentMethodsMap from '../../payment-methods-map';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import AlertTitle from 'wcstripe/components/confirmation-modal/alert-title';

const RemoveMethodConfirmationModal = ( { method, onClose, onConfirm } ) => {
	const { label } = PaymentMethodsMap[ method ];

	const confirmMethodRemovalString = sprintf(
		/* translators: %1: payment method name (e.g.: giropay, EPS, etc). */
		__(
			'Are you sure you want to remove <strong>%1$s</strong>? Your customers will no longer be able to pay using <strong>%1$s</strong>.',
			'woocommerce-gateway-stripe'
		),
		label
	);

	return (
		<ConfirmationModal
			title={
				<AlertTitle
					title={ sprintf(
						/* translators: %s: payment method name (e.g.: giropay, EPS, etc). */
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
					<Button isPrimary isDestructive onClick={ onConfirm }>
						{ __( 'Remove', 'woocommerce-gateway-stripe' ) }
					</Button>
					<Button isSecondary onClick={ onClose }>
						{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
					</Button>
				</>
			}
		>
			<p>
				{ createInterpolateElement( confirmMethodRemovalString, {
					strong: <strong />,
				} ) }
			</p>
			<p>
				{ label !== 'Sofort' &&
					__(
						'You can add it again at any time in Stripe settings.',
						'woocommerce-gateway-stripe'
					) }
			</p>
		</ConfirmationModal>
	);
};

export default RemoveMethodConfirmationModal;
