import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, CheckboxControl } from '@wordpress/components';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import AlertTitle from 'wcstripe/components/confirmation-modal/alert-title';
import { useAccountKeys } from 'wcstripe/data/account-keys/hooks';
import { useAccount } from 'wcstripe/data/account';
import './style.scss';

const DisconnectStripeConfirmationModal = ( {
	onClose,
	setKeepModalContent,
} ) => {
	const { saveAccountKeys } = useAccountKeys();
	const { data } = useAccount();
	const [ status, setStatus ] = useState( false );
	const [ shouldDeauthorize, setShouldDeauthorize ] = useState( false );

	// Only classic Connect OAuth connections can be revoked
	const canDeauthorize =
		data?.oauth_connections?.live?.type === 'connect' ||
		data?.oauth_connections?.test?.type === 'connect';

	const handleDisconnect = async () => {
		setStatus( 'pending' );
		setKeepModalContent( true );

		const accountKeys = {
			publishable_key: '',
			secret_key: '',
			webhook_secret: '',
			test_publishable_key: '',
			test_secret_key: '',
			test_webhook_secret: '',
		};
		if ( canDeauthorize && shouldDeauthorize ) {
			accountKeys.deauthorize_connection = true;
		}
		await saveAccountKeys( accountKeys );

		window.location.reload();
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
				onRequestClose={ () => {
					// Do not allow to close the modal after clicking the "Disconnect" button
					if ( status === 'pending' ) {
						return;
					}

					onClose();
				} }
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
						'All settings will be cleared and your customers will no longer be able to pay using cards and other payment methods offered by Stripe. Due to this change, you might be required to create a new Stripe account should you want to reactivate.',
						'woocommerce-gateway-stripe'
					) }
				</p>
				{ canDeauthorize && (
					<div className="wcstripe-deauthorize-checkbox">
						<CheckboxControl
							checked={ shouldDeauthorize }
							disabled={ status === 'pending' }
							onChange={ setShouldDeauthorize }
							label={ __(
								'Also revoke Connect API keys',
								'woocommerce-gateway-stripe'
							) }
							help={ __(
								'Permanently revoke the API keys issued for this Stripe account through Stripe Connect. Any other stores, including staging or test copies of this store, and other tools using keys from your WooCommerce connection to this Stripe account will also stop working, including taking payments, until you re-authorize them.',
								'woocommerce-gateway-stripe'
							) }
						/>
					</div>
				) }
			</ConfirmationModal>
		</>
	);
};

export default DisconnectStripeConfirmationModal;
