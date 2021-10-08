import { __ } from '@wordpress/i18n';
import { React, useRef, useState } from 'react';
import styled from '@emotion/styled';
import { Button, TabPanel, TextControl } from '@wordpress/components';
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
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import InlineNotice from 'wcstripe/components/inline-notice';

const PublishableKey = ( { testMode } ) => {
	const [ publishableKey ] = useAccountKeysPublishableKey();
	const [ testPublishableKey ] = useAccountKeysTestPublishableKey();
	const { isSaving } = useAccountKeys();
	const [ value, setValue ] = useState(
		testMode ? testPublishableKey : publishableKey
	);

	return (
		<TextControl
			label={
				testMode
					? __( 'Test publishable key', 'woocommerce-gateway-stripe' )
					: __( 'Live publishable key', 'woocommerce-gateway-stripe' )
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
		/>
	);
};

const SecretKey = () => {
	const [ secretKey ] = useAccountKeysSecretKey();
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
			onChange={ ( val ) => setValue( val ) }
			disabled={ isSaving }
			name="secret_key"
		/>
	);
};

const TestSecretKey = () => {
	const [ testSecretKey ] = useAccountKeysTestSecretKey();
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
			onChange={ ( val ) => setValue( val ) }
			disabled={ isSaving }
			name="test_secret_key"
		/>
	);
};

const WebhookSecret = () => {
	const [ webhookSecret ] = useAccountKeysWebhookSecret();
	const { isSaving } = useAccountKeys();
	const [ value, setValue ] = useState( webhookSecret );
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
			name="webhook_secret"
		/>
	);
};

const TestWebhookSecret = () => {
	const [ testWebhookSecret ] = useAccountKeysTestWebhookSecret();
	const { isSaving } = useAccountKeys();
	const [ value, setValue ] = useState( testWebhookSecret );
	return (
		<TextControl
			label={ __( 'Test Webhook secret', 'woocommerce-gateway-stripe' ) }
			help={ __(
				'Get your webhook signing secret from the webhooks section in your Stripe account.',
				'woocommerce-gateway-stripe'
			) }
			value={ value }
			onChange={ ( val ) => setValue( val ) }
			disabled={ isSaving }
			name="test_webhook_secret"
		/>
	);
};

const TestForm = ( { formRef } ) => {
	return (
		<form ref={ formRef }>
			<TestPublishableKey />
			<TestSecretKey />
			<TestWebhookSecret />
		</form>
	);
};

const LiveForm = ( { formRef } ) => {
	return (
		<form ref={ formRef }>
			<PublishableKey />
			<SecretKey />
			<WebhookSecret />
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
	const { isSaving, saveAccountKeys } = useAccountKeys();
	const formRef = useRef( null );
	const testFormRef = useRef( null );
	const testMode = openTab === 'test';

	// @todo - make a higher-order component for these and pass in label and help?

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

	const onTabSelect = ( tabName ) => {
		// @todo - use local state to change between live/test modals.
		setOpenTab( tabName );
	};

	return (
		<StyledConfirmationModal
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
			<StyledTabPanel
				// @todo className="my-tab-panel"
				// @todo activeClass="active-tab"
				// @todo - style it like in designs.
				initialTabName={ type }
				onSelect={ onTabSelect }
				tabs={ [
					{
						name: 'live',
						title: 'Live',
						className: 'live-tab',
					},
					{
						name: 'test',
						title: 'Test',
						className: 'test-tab',
					},
				] }
			>
				{ ( { name } ) =>
					name === 'test' ? (
						<TestForm formRef={ testFormRef } />
					) : (
						<LiveForm formRef={ formRef } />
					)
				}
			</StyledTabPanel>
		</StyledConfirmationModal>
	);
};
