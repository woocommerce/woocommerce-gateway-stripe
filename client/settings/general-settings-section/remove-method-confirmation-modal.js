import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import { Button } from '@wordpress/components';
import PaymentMethodsMap from '../../payment-methods-map';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import AlertTitle from 'wcstripe/components/confirmation-modal/alert-title';

const RemoveMethodConfirmationModal = ( { method, onClose, onConfirm } ) => {
	const { label } = PaymentMethodsMap[ method ];

	const confirmMethodRemovalString = sprintf(
		/* translators: %1: payment method name (e.g.: giropay, EPS, Sofort, etc). */
		__(
			'Are you sure you want to remove %1$s? Your customers will no longer be able to pay using %1$s.',
			'woocommerce-gateway-stripe'
		),
		`<strong>${ label }</strong>`
	);

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
					<Button isPrimary isDestructive onClick={ onConfirm }>
						{ __( 'Remove', 'woocommerce-gateway-stripe' ) }
					</Button>
					<Button isSecondary onClick={ onClose }>
						{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
					</Button>
				</>
			}
		>
			<p
				dangerouslySetInnerHTML={ {
					__html: confirmMethodRemovalString,
				} }
			/>
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
