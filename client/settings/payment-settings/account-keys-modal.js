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

	const handleSave = async ( ref ) => {
		// Grab the HTMLCollection of elements of the HTML form, convert to array.
		const elements = Array.from( ref.current.elements );
		// Convert HTML elements array to an object acceptable for saving keys.
		const keysToSave = elements.reduce( ( acc, curr ) => {
			const { name, value } = curr;
			return { ...acc, [ name ]: value };
		}, {} );
		await saveAccountKeys( keysToSave );
		onClose( { saveSuccess: true } );
	};

	const onTabSelect = ( tabName ) => {
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
