import { __ } from '@wordpress/i18n';
import React from 'react';
import { Button } from '@wordpress/components';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import AlertTitle from 'wcstripe/components/confirmation-modal/alert-title';
import { useAccountKeys } from 'wcstripe/data/account-keys/hooks';

const DisconnectStripeConfirmationModal = ( { onClose } ) => {
	const { saveAccountKeys } = useAccountKeys();

	const handleDisconnect = () => {
		const accountKeys = {
			publishable_key: '',
			secret_key: '',
			webhook_secret: '',
			test_publishable_key: '',
			test_secret_key: '',
			test_webhook_secret: '',
		};
		saveAccountKeys( accountKeys );
	};

	return (
		<>
			<ConfirmationModal
				title={
					<AlertTitle
						title={ __(
							'Disconnect Stripe account',
							'woocommerce-gateway-stripe'
						) }
					/>
				}
				onRequestClose={ onClose }
				actions={
					<>
						<Button
							isSecondary
							disabled={ status === 'pending' }
							onClick={ onClose }
						>
							{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
						</Button>
						<Button
							isPrimary
							isDestructive
							isBusy={ status === 'pending' }
							disabled={ status === 'pending' }
							onClick={ handleDisconnect }
						>
							{ __( 'Disconnect', 'woocommerce-gateway-stripe' ) }
						</Button>
					</>
				}
			>
				<strong>
					{ __(
						'Are you sure you want to disconnect your Stripe account from your WooCommerce store?',
						'woocommerce-gateway-stripe'
					) }
				</strong>
				<p>
					{ __(
						'All settings will be cleared and your customers will no longer be able to pay using cards and other payment methods offered by Stripe.',
						'woocommerce-gateway-stripe'
					) }
				</p>
			</ConfirmationModal>
		</>
	);
};

export default DisconnectStripeConfirmationModal;
