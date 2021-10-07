import { __ } from '@wordpress/i18n';
import { React, useRef, useState } from 'react';
import { Button, TextControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import {
	useAccountKeys,
	useAccountKeysPublishableKey,
	useAccountKeysSecretKey,
	useAccountKeysWebhookSecret,
	useAccountKeysTestPublishableKey,
	useAccountKeysTestSecretKey,
	useAccountKeysTestWebhookSecret,
} from 'wcstripe/data/account-keys';
import { useTestMode } from 'wcstripe/data';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import InlineNotice from 'wcstripe/components/inline-notice';

export const AccountKeysModal = ( { type, onClose } ) => {
	const [ testMode ] = useTestMode();
	const { isSaving, saveAccountKeys } = useAccountKeys();
	const formRef = useRef( null );

	// @todo - make a higher-order component for these and pass in label and help?
	const PublishableKey = () => {
		const [ publishableKey ] = useAccountKeysPublishableKey();
		const [ testPublishableKey ] = useAccountKeysTestPublishableKey();
		const [ value, setValue ] = useState(
			testMode ? testPublishableKey : publishableKey
		);

		return (
			<TextControl
				label={
					testMode
						? __(
								'Test publishable key',
								'woocommerce-gateway-stripe'
						  )
						: __(
								'Live publishable key',
								'woocommerce-gateway-stripe'
						  )
				}
				help={
					testMode
						? __(
								'Only values starting with "pk_test_" will be saved.',
								'woocommerce-gateway-stripe'
						  )
						: __(
								'Only values starting with "pk_live_" will be saved.',
								'woocommerce-gateway-stripe'
						  )
				}
				value={ value }
				onChange={ ( val ) => setValue( val ) }
				disabled={ isSaving }
				name={ testMode ? 'test_publishable_key' : 'publishable_key' }
			/>
		);
	};

	const SecretKey = () => {
		const [ testSecretKey ] = useAccountKeysTestSecretKey();
		const [ secretKey ] = useAccountKeysSecretKey();
		const [ value, setValue ] = useState(
			testMode ? testSecretKey : secretKey
		);
		return (
			<TextControl
				label={
					testMode
						? __( 'Test secret key', 'woocommerce-gateway-stripe' )
						: __( 'Live secret key', 'woocommerce-gateway-stripe' )
				}
				help={
					testMode
						? __(
								'Only values starting with "sk_test_" or "rk_test_" will be saved.',
								'woocommerce-gateway-stripe'
						  )
						: __(
								'Only values starting with "sk_live_" or "rk_live_" will be saved.',
								'woocommerce-gateway-stripe'
						  )
				}
				value={ value }
				onChange={ ( val ) => setValue( val ) }
				disabled={ isSaving }
				name={ testMode ? 'test_secret_key' : 'secret_key' }
			/>
		);
	};

	const WebhookSecret = () => {
		const [ testWebhookSecret ] = useAccountKeysTestWebhookSecret();
		const [ webhookSecret ] = useAccountKeysWebhookSecret();
		const [ value, setValue ] = useState(
			testMode ? testWebhookSecret : webhookSecret
		);
		return (
			<TextControl
				label={ __( 'Webhook secret', 'woocommerce-gateway-stripe' ) }
				help={ __(
					'Get your webhook signing secret from the webhooks section in your Stripe account.',
					'woocommerce-gateway-stripe'
				) }
				value={ value }
				onChange={ ( val ) => setValue( val ) }
				disabled={ isSaving }
				name={ testMode ? 'test_webhook_secret' : 'webhook_secret' }
			/>
		);
	};

	const handleSave = () => {
		// Grab the HTMLCollection of elements of the HTML form, convert to array.
		const elements = Array.from( formRef.current.elements );
		// Convert HTML elements array to an object acceptable for saving keys.
		const keysToSave = elements.reduce( ( acc, curr ) => {
			const { name, value } = curr;
			return { ...acc, [ name ]: value };
		}, {} );
		saveAccountKeys( keysToSave );
		// @todo - automatically close once saving is done successfully. leave open if failed? onClose();
	};

	return (
		<ConfirmationModal
			onRequestClose={ onClose }
			actions={
				<>
					<Button
						isSecondary
						onClick={ onClose }
						disabled={ isSaving }
					>
						{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
					</Button>
					<Button
						isPrimary
						isBusy={ isSaving }
						disabled={ isSaving }
						onClick={ handleSave }
					>
						{ __( 'Save changes', 'woocommerce-gateway-stripe' ) }
					</Button>
				</>
			}
			title={
				type === 'test'
					? __(
							'Edit test account keys & webhooks',
							'woocommerce-gateway-stripe'
					  )
					: __(
							'Edit live account keys & webhooks',
							'woocommerce-gateway-stripe'
					  )
			}
		>
			<InlineNotice isDismissible={ false }>
				{ type === 'test'
					? interpolateComponents( {
							mixedString: __(
								"To enable the test mode, get the test account keys from your {{accountLink}}Stripe Account{{/accountLink}} (we'll save them for you so you won't have to do this every time).",
								'woocommerce-gateway-stripe'
							),
							components: {
								accountLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a href="https://dashboard.stripe.com/test/apikeys" />
								),
							},
					  } )
					: interpolateComponents( {
							mixedString: __(
								"To enable the live mode, get the account keys from your {{accountLink}}Stripe Account{{/accountLink}} (we'll save them for you so you won't have to do this every time).",
								'woocommerce-gateway-stripe'
							),
							components: {
								accountLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a href="https://dashboard.stripe.com/apikeys" />
								),
							},
					  } ) }
			</InlineNotice>
			<form ref={ formRef }>
				<PublishableKey />
				<SecretKey />
				<WebhookSecret />
			</form>
		</ConfirmationModal>
	);
};
