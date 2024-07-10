import { __, sprintf } from '@wordpress/i18n';
import { React, useRef, useState, useEffect } from 'react';
import styled from '@emotion/styled';
import { Button, TabPanel, BaseControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import {
	useAccountKeys,
	useAccountKeysSecretKey,
	useAccountKeysWebhookSecret,
	useAccountKeysTestSecretKey,
	useAccountKeysTestWebhookSecret,
} from 'wcstripe/data/account-keys';
import { useAccount } from 'wcstripe/data/account';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import StripeConnectionSection from 'wcstripe/settings/stripe-connection-section';

const WebhookSecretComponent = ( {
	secretKeyHook,
	webhookSecretHook,
	liveMode,
	setWebhookHelpText,
} ) => {
	const { isSaving, configureWebhooks, isConfiguring } = useAccountKeys();
	const { data } = useAccount();
	const [ secretKey ] = secretKeyHook();
	const [ webhookSecret, setWebhookSecret ] = webhookSecretHook();

	const initialWebhookURL = liveMode
		? data?.configured_webhook_urls?.live ?? ''
		: data?.configured_webhook_urls?.test ?? '';
	const [ webhookURL, setWebhookURL ] = useState( initialWebhookURL );

	/**
	 * The callback to be called when the webhook configuration is successful.
	 *
	 * This callback is passed to the configureWebhooks action function and is called when the API request succeeds.
	 *
	 * @param {*} secret The webhook secret.
	 * @param {*} URL    The webhook URL.
	 */
	const successCallback = ( secret, URL ) => {
		setWebhookSecret( secret );
		setWebhookURL( URL );
	};

	const hasSecretKey = Boolean( secretKey );

	// Determine the button type and text based on whether a webhook as already been configured.
	const buttonType = webhookSecret ? 'secondary' : 'primary';
	const buttonText = webhookSecret
		? __( 'Reconfigure webhooks', 'woocommerce-gateway-stripe' )
		: __( 'Configure webhooks', 'woocommerce-gateway-stripe' );

	// If webhooks are configured, display a message with the webhook URL (if it's available).
	useEffect( () => {
		if ( webhookSecret ) {
			setWebhookHelpText(
				webhookURL
					? interpolateComponents( {
							mixedString: sprintf(
								/* translators: %s: is a webhook URL. */
								__(
									'Your webhooks are configured and will be sent to: {{webhookURL}}%s{{/webhookURL}}.',
									'woocommerce-gateway-stripe'
								),
								decodeURIComponent( webhookURL )
							),
							components: {
								webhookURL: <strong />,
							},
					  } )
					: __(
							'Your webhooks are configured.',
							'woocommerce-gateway-stripe'
					  )
			);
		}
	}, [ webhookSecret, webhookURL, setWebhookHelpText ] );

	return (
		<Button
			disabled={ isSaving || isConfiguring || ! hasSecretKey }
			isBusy={ isConfiguring }
			onClick={ () => {
				configureWebhooks( {
					live: liveMode,
					secret: secretKey,
					callback: successCallback,
				} );
			} }
			variant={ buttonType }
			text={ buttonText }
			style={ {
				display: 'block',
			} }
		/>
	);
};

const WebhookConfigureButton = ( { testMode, setWebhookHelpText } ) => {
	return testMode ? (
		<WebhookSecretComponent
			secretKeyHook={ useAccountKeysTestSecretKey }
			webhookSecretHook={ useAccountKeysTestWebhookSecret }
			liveMode={ false }
			setWebhookHelpText={ setWebhookHelpText }
		/>
	) : (
		<WebhookSecretComponent
			secretKeyHook={ useAccountKeysSecretKey }
			webhookSecretHook={ useAccountKeysWebhookSecret }
			liveMode={ true }
			setWebhookHelpText={ setWebhookHelpText }
		/>
	);
};

const StripeConnectActions = ( { testMode } ) => {
	const [ webhookHelpText, setWebhookHelpText ] = useState(
		__(
			'Configuring webhooks will enable your store to receive notifications on charge statuses from Stripe.',
			'woocommerce-gateway-stripe'
		)
	);
	const mode = testMode ? 'test' : 'live';
	const buttonText = testMode
		? __( 'Connect a test account', 'woocommerce-gateway-stripe' )
		: __( 'Connect an account', 'woocommerce-gateway-stripe' );
	const buttonType = 'primary';
	return (
		<BaseControl
			id={ `woocommerce-stripe-connection-${ mode }-actions` }
			help={ webhookHelpText }
			className="woocommerce-stripe-connection__actions"
		>
			<div className="woocommerce-stripe-connection__actions-wrapper">
				<Button variant={ buttonType } text={ buttonText } />
				<WebhookConfigureButton
					testMode={ testMode }
					setWebhookHelpText={ setWebhookHelpText }
				/>
			</div>
		</BaseControl>
	);
};

const Form = ( { formRef, testMode } ) => {
	return (
		<form ref={ formRef }>
			<StripeConnectionSection testMode={ testMode } />
			<StripeConnectActions testMode={ testMode } />
		</form>
	);
};

const StyledTabPanel = styled( TabPanel )`
	margin: 0 24px 24px;
`;

const StyledConfirmationModal = styled( ConfirmationModal )`
	.components-modal__content {
		padding: 0;
	}
	.components-modal__header {
		padding: 0 24px;
		margin: 0;
	}
	.components-tab-panel__tabs {
		background-color: #f1f1f1;
		margin: 0 -24px 24px;
	}
	.wcstripe-inline-notice {
		margin-top: 0;
		margin-bottom: 0;
	}
	.wcstripe-confirmation-modal__separator {
		margin: 0;
	}
	.wcstripe-confirmation-modal__footer {
		padding: 16px;
	}
`;

export const AccountKeysModal = ( { type, onClose } ) => {
	const [ openTab, setOpenTab ] = useState( type );
	const { updateIsValidAccountKeys } = useAccountKeys();
	const formRef = useRef( null );
	const testFormRef = useRef( null );
	const testMode = openTab === 'test';

	const onCloseHelper = () => {
		// Reset AccountKeysConnectionStatus to default state.
		updateIsValidAccountKeys( null );
		onClose();
	};

	const onTabSelect = ( tabName ) => {
		// Reset AccountKeysConnectionStatus to default state.
		updateIsValidAccountKeys( null );
		setOpenTab( tabName );
	};

	return (
		<StyledConfirmationModal
			onRequestClose={ onCloseHelper }
			title={
				testMode
					? __(
							'Test Stripe account & webhooks',
							'woocommerce-gateway-stripe'
					  )
					: __(
							'Live Stripe account & webhooks',
							'woocommerce-gateway-stripe'
					  )
			}
		>
			<StyledTabPanel
				initialTabName={ type }
				onSelect={ onTabSelect }
				tabs={ [
					{
						name: 'live',
						title: __( 'Live', 'woocommerce-gateway-stripe' ),
						className: 'live-tab',
					},
					{
						name: 'test',
						title: __( 'Test', 'woocommerce-gateway-stripe' ),
						className: 'test-tab',
					},
				] }
			>
				{ () => (
					<Form
						formRef={ testMode ? testFormRef : formRef }
						testMode={ testMode }
					/>
				) }
			</StyledTabPanel>
		</StyledConfirmationModal>
	);
};
