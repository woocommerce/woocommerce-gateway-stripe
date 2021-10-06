import { __ } from '@wordpress/i18n';
import { React, useState } from 'react';
import { Button, TextControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { useAccountKeys } from 'wcstripe/data/account-keys';
import { useTestMode } from 'wcstripe/data';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import InlineNotice from 'wcstripe/components/inline-notice';

export const AccountKeysModal = ( { type, onClose } ) => {
	const [ , setTestMode ] = useTestMode();
	const { accountKeys, isSaving } = useAccountKeys();

	// @todo - make a higher-order component for these and pass in label and help?
	const PublishableKey = () => {
		const [ value, setValue ] = useState(
			type === 'test'
				? accountKeys.test_publishable_key
				: accountKeys.publishable_key
		);
		return (
			<TextControl
				label={
					type === 'test'
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
					type === 'test'
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
			/>
		);
	};

	const SecretKey = () => {
		const [ value, setValue ] = useState(
			type === 'test'
				? accountKeys.test_secret_key
				: accountKeys.secret_key
		);
		return (
			<TextControl
				label={
					type === 'test'
						? __( 'Test secret key', 'woocommerce-gateway-stripe' )
						: __( 'Live secret key', 'woocommerce-gateway-stripe' )
				}
				help={
					type === 'test'
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
			/>
		);
	};

	const WebhookSecret = () => {
		const [ value, setValue ] = useState(
			type === 'test'
				? accountKeys.test_webhook_secret
				: accountKeys.webhook_secret
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
			/>
		);
	};

	const handleSave = () => {
		setTestMode( type === 'test' );
		onClose();
	};

	return (
		<ConfirmationModal
			onRequestClose={ onClose }
			actions={
				<>
					<Button isSecondary onClick={ onClose }>
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
			<PublishableKey />
			<SecretKey />
			<WebhookSecret />
		</ConfirmationModal>
	);
};
