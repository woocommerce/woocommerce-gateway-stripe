import { __, sprintf } from '@wordpress/i18n';
import { React, useRef, useState } from 'react';
import styled from '@emotion/styled';
import {
	Button,
	TabPanel,
	TextControl,
	BaseControl,
} from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import SectionStatus from '../section-status';
import {
	useAccountKeys,
	useAccountKeysPublishableKey,
	useAccountKeysSecretKey,
	useAccountKeysWebhookSecret,
	useAccountKeysTestPublishableKey,
	useAccountKeysTestSecretKey,
	useAccountKeysTestWebhookSecret,
} from 'wcstripe/data/account-keys';
import { useAccount } from 'wcstripe/data/account';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import InlineNotice from 'wcstripe/components/inline-notice';
import { AccountKeysConnectionStatus } from 'wcstripe/settings/payment-settings/account-keys-connection-status';

const PublishableKey = () => {
	const [ publishableKey ] = useAccountKeysPublishableKey();
	const { isSaving } = useAccountKeys();
	const [ value, setValue ] = useState( publishableKey );

	return (
		<TextControl
			label={ __( 'Live publishable key', 'woocommerce-gateway-stripe' ) }
			help={ __(
				'Only values starting with "pk_live_" will be saved.',
				'woocommerce-gateway-stripe'
			) }
			value={ value }
			onChange={ ( val ) => setValue( val ) }
			disabled={ isSaving }
			name="publishable_key"
			autoComplete="off"
			onFocus={ ( e ) => e.target.select() }
		/>
	);
};

const TestPublishableKey = () => {
	const [ testPublishableKey ] = useAccountKeysTestPublishableKey();
	const { isSaving } = useAccountKeys();
	const [ value, setValue ] = useState( testPublishableKey );

	return (
		<TextControl
			label={ __( 'Test publishable key', 'woocommerce-gateway-stripe' ) }
			help={ __(
				'Only values starting with "pk_test_" will be saved.',
				'woocommerce-gateway-stripe'
			) }
			value={ value }
			onChange={ ( val ) => setValue( val ) }
			disabled={ isSaving }
			name="test_publishable_key"
			autoComplete="off"
			onFocus={ ( e ) => e.target.select() }
		/>
	);
};

const SecretKey = () => {
	const [ secretKey, setSecretKey ] = useAccountKeysSecretKey();
	const { isSaving } = useAccountKeys();
	const [ value, setValue ] = useState( secretKey );
	return (
		<TextControl
			label={ __( 'Live secret key', 'woocommerce-gateway-stripe' ) }
			help={ __(
				'Only values starting with "sk_live_" or "rk_live_" will be saved.',
				'woocommerce-gateway-stripe'
			) }
			value={ value }
			onChange={ ( val ) => {
				setValue( val );
				setSecretKey( val );
			} }
			disabled={ isSaving }
			name="secret_key"
			autoComplete="off"
			onFocus={ ( e ) => e.target.select() }
		/>
	);
};

const TestSecretKey = () => {
	const [ testSecretKey, setTestSecretKey ] = useAccountKeysTestSecretKey();
	const { isSaving } = useAccountKeys();
	const [ value, setValue ] = useState( testSecretKey );
	return (
		<TextControl
			label={ __( 'Test secret key', 'woocommerce-gateway-stripe' ) }
			help={ __(
				'Only values starting with "sk_test_" or "rk_test_" will be saved.',
				'woocommerce-gateway-stripe'
			) }
			value={ value }
			onChange={ ( val ) => {
				setValue( val );
				setTestSecretKey( val );
			} }
			disabled={ isSaving }
			name="test_secret_key"
			autoComplete="off"
			onFocus={ ( e ) => e.target.select() }
		/>
	);
};

const WebhookSecret = () => {
	return (
		<WebhookSecretComponent
			id="wc-stripe-webhook-element"
			label={ __( 'Live Webhook', 'woocommerce-gateway-stripe' ) }
			secretKeyHook={ useAccountKeysSecretKey }
			webhookSecretHook={ useAccountKeysWebhookSecret }
			liveMode={ true }
		/>
	);
};

const TestWebhookSecret = () => {
	return (
		<WebhookSecretComponent
			id="wc-stripe-test-webhook-element"
			label={ __( 'Test Webhook', 'woocommerce-gateway-stripe' ) }
			secretKeyHook={ useAccountKeysTestSecretKey }
			webhookSecretHook={ useAccountKeysTestWebhookSecret }
			liveMode={ false }
		/>
	);
};

const WebhookSecretComponent = ( {
	id,
	label,
	secretKeyHook,
	webhookSecretHook,
	liveMode,
} ) => {
	const { isSaving, configureWebhooks, isConfiguring } = useAccountKeys();
	const { data } = useAccount();
	const [ secretKey ] = secretKeyHook();
	const [ webhookSecret, setWebhookSecret ] = webhookSecretHook();

	const initialWebhookURL = liveMode
		? data.configured_webhook_urls.live
		: data.configured_webhook_urls.test;
	const [ webhookURL, setWebhookURL ] = useState( initialWebhookURL );

	/**
	 * The callback to be called when the webhook configuration is successful.
	 *
	 * @param {*} secret The webhook secret.
	 * @param {*} URL    The webhook URL.
	 */
	const successCallback = ( secret, URL ) => {
		setWebhookSecret( secret );
		setWebhookURL( URL );
	};

	/**
	 * Configure webhooks for the test mode.
	 */
	const onConfigureWebhooks = () => {
		configureWebhooks( {
			live: liveMode,
			secret: secretKey,
			callback: successCallback,
		} );
	};

	const hasSecretKey = Boolean( secretKey );

	// Determine the button type and text based on whether a webhook as already been configured.
	const buttonType = webhookSecret ? 'secondary' : 'primary';
	const buttonText = webhookSecret
		? __( 'Reconfigure webhooks', 'woocommerce-gateway-stripe' )
		: __( 'Configure webhooks', 'woocommerce-gateway-stripe' );

	let helpText = __(
		'Configuring webhooks will enable your store to receive notifications on charge statuses from Stripe.',
		'woocommerce-gateway-stripe'
	);

	// If webhooks are configured, display a message with the webhook URL (if it's available).
	if ( webhookSecret ) {
		helpText = webhookURL
			? interpolateComponents( {
					mixedString: sprintf(
						/* translators: %s: a payment method name. */
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
			  );
	}

	return (
		<BaseControl id={ id } label={ label } help={ helpText }>
			<div className="wc-stripe-configure-webhook-control__content-wrapper">
				<Button
					disabled={ isSaving || isConfiguring || ! hasSecretKey }
					isBusy={ isConfiguring }
					onClick={ onConfigureWebhooks }
					variant={ buttonType }
					text={ buttonText }
					style={ {
						display: 'block',
					} }
				/>
				{ webhookSecret && ! isConfiguring && (
					<SectionStatus isEnabled={ true }>
						{ __( 'Enabled', 'woocommerce-gateway-stripe' ) }
					</SectionStatus>
				) }
			</div>
		</BaseControl>
	);
};

const Form = ( { formRef, testMode } ) => {
	return (
		<form ref={ formRef }>
			{ testMode ? <TestPublishableKey /> : <PublishableKey /> }
			{ testMode ? <TestSecretKey /> : <SecretKey /> }
			{ testMode ? <TestWebhookSecret /> : <WebhookSecret /> }
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

export const AccountKeysModal = ( { type, onClose, setKeepModalContent } ) => {
	const [ openTab, setOpenTab ] = useState( type );
	const {
		isSaving,
		saveAccountKeys,
		updateIsValidAccountKeys,
	} = useAccountKeys();
	const [ isDisabled, setDisabled ] = useState( false );
	const formRef = useRef( null );
	const testFormRef = useRef( null );
	const testMode = openTab === 'test';

	const onCloseHelper = () => {
		// Reset AccountKeysConnectionStatus to default state.
		updateIsValidAccountKeys( null );
		onClose();
	};

	const handleSave = async ( ref ) => {
		setDisabled( true );
		// Grab the HTMLCollection of elements of the HTML form, convert to array.
		const elements = Array.from( ref.current.elements );
		// Convert HTML elements array to an object acceptable for saving keys.
		const keysToSave = elements.reduce( ( acc, curr ) => {
			const { name, value } = curr;
			return { ...acc, [ name ]: value };
		}, {} );

		const saveSuccess = await saveAccountKeys( keysToSave );
		if ( ! saveSuccess ) {
			setDisabled( false );
		} else {
			// After a successful save, we keep the modal open and disabled while the page reloads.
			if ( setKeepModalContent ) {
				setKeepModalContent( true );
			}
			window.location.reload();
		}
	};

	const onTabSelect = ( tabName ) => {
		// Reset AccountKeysConnectionStatus to default state.
		updateIsValidAccountKeys( null );
		setOpenTab( tabName );
	};

	return (
		<StyledConfirmationModal
			onRequestClose={ onCloseHelper }
			actions={
				<div
					style={ {
						display: 'flex',
						justifyContent: 'space-between',
						width: '100%',
					} }
				>
					<AccountKeysConnectionStatus
						formRef={ testMode ? testFormRef : formRef }
					/>
					<div>
						<Button
							isSecondary
							onClick={ onCloseHelper }
							disabled={ isDisabled }
						>
							{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
						</Button>
						<Button
							className="ml-unit-20"
							isPrimary
							isBusy={ isSaving || isDisabled }
							disabled={ isDisabled }
							onClick={ () =>
								handleSave( testMode ? testFormRef : formRef )
							}
						>
							{ testMode
								? __(
										'Save test keys',
										'woocommerce-gateway-stripe'
								  )
								: __(
										'Save live keys',
										'woocommerce-gateway-stripe'
								  ) }
						</Button>
					</div>
				</div>
			}
			title={
				testMode
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
				{ testMode
					? interpolateComponents( {
							mixedString: __(
								'To enable the test mode, get the test account keys from your {{accountLink}}Stripe Account{{/accountLink}}.',
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
								'To enable the live mode, get the account keys from your {{accountLink}}Stripe Account{{/accountLink}}.',
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
