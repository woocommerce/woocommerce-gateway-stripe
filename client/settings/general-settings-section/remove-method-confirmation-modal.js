import { __ } from '@wordpress/i18n';
import React from 'react';
import interpolateComponents from 'interpolate-components';
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
					title={ interpolateComponents( {
						mixedString: __(
							'Remove {{methodName /}} from checkout',
							'woocommerce-gateway-stripe'
						),
						components: {
							methodName: <span>&nbsp;{ label }&nbsp;</span>,
						},
					} ) }
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
				{ interpolateComponents( {
					mixedString: __(
						'Are you sure you want to remove {{methodName /}}? Your customers will no longer be able to pay using {{methodName /}}.',
						'woocommerce-gateway-stripe'
					),
					components: {
						methodName: <span>{ label }</span>,
					},
				} ) }
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
