import React, { useState, useEffect, useCallback } from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import styled from '@emotion/styled';
import AgenticCommerceSyncStatus from './sync-status';
import { Card, CardTitle, Actions } from './styled';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Notice,
	ToggleControl,
	TextControl,
	ExternalLink,
} from '@wordpress/components';
import { useAccount } from 'wcstripe/data/account';
import { useTestMode } from 'wcstripe/data';

const OnboardingSteps = styled.ol`
	margin: 12px 0 0;
	padding-left: 20px;

	li {
		margin-bottom: 6px;
	}
`;

const AgenticCommercePanel = () => {
	// Settings state.
	const [ isFeatureEnabled, setIsFeatureEnabled ] = useState( false );
	const [ webhookSecret, setWebhookSecret ] = useState( '' );
	const [ isLoadingSettings, setIsLoadingSettings ] = useState( true );
	const [ areSettingsLoaded, setAreSettingsLoaded ] = useState( false );
	const [ isSavingSettings, setIsSavingSettings ] = useState( false );
	const [ settingsNotice, setSettingsNotice ] = useState( null );
	const [ webhookURLCopied, setWebhookURLCopied ] = useState( false );

	const [ isTestMode ] = useTestMode();
	const mode = isTestMode ? 'test' : 'live';
	const { data } = useAccount();
	const webhookURLForDisplay = data?.configured_webhook_urls?.[ mode ] ?? '';
	const agenticCommerceUrl = isTestMode
		? 'https://dashboard.stripe.com/test/agentic-commerce'
		: 'https://dashboard.stripe.com/agentic-commerce';

	const fetchSettings = useCallback( async () => {
		setIsLoadingSettings( true );
		setSettingsNotice( null );
		try {
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/settings',
			} );
			setIsFeatureEnabled( result.is_enabled );
			setWebhookSecret( result.webhook_secret ?? '' );
			setAreSettingsLoaded( true );
		} catch ( err ) {
			// Leave the form locked — POST-ing the unloaded defaults would
			// silently disable the feature and clear the stored webhook secret
			// for a transient GET failure.
			setAreSettingsLoaded( false );
			setSettingsNotice( {
				status: 'error',
				message:
					err?.message ??
					__(
						'Failed to load Agentic Commerce settings. Refresh the page to retry.',
						'woocommerce-gateway-stripe'
					),
			} );
		} finally {
			setIsLoadingSettings( false );
		}
	}, [] );

	useEffect( () => {
		fetchSettings();
	}, [ fetchSettings ] );

	const handleSaveSettings = async () => {
		// Defensive: never POST defaults that came from an unloaded form.
		if ( ! areSettingsLoaded ) {
			return;
		}

		setIsSavingSettings( true );
		setSettingsNotice( null );
		try {
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/settings',
				method: 'POST',
				data: {
					is_enabled: isFeatureEnabled,
					webhook_secret: webhookSecret,
				},
			} );
			setIsFeatureEnabled( result.is_enabled );
			setWebhookSecret( result.webhook_secret ?? '' );
			setSettingsNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'woocommerce-gateway-stripe' ),
			} );
		} catch ( err ) {
			setSettingsNotice( {
				status: 'error',
				message:
					err?.message ??
					__(
						'Failed to save settings.',
						'woocommerce-gateway-stripe'
					),
			} );
		} finally {
			setIsSavingSettings( false );
		}
	};

	const handleCopy = () => {
		const doCopy = ( text ) => {
			if ( navigator.clipboard?.writeText ) {
				return navigator.clipboard.writeText( text );
			}
			// Fallback for browsers without clipboard API.
			const el = document.createElement( 'textarea' );
			el.value = text;
			el.style.position = 'fixed';
			el.style.opacity = '0';
			document.body.appendChild( el );
			el.select();
			document.execCommand( 'copy' );
			document.body.removeChild( el );
			return Promise.resolve();
		};

		doCopy( webhookURLForDisplay )
			.then( () => {
				setWebhookURLCopied( true );
				setTimeout( () => setWebhookURLCopied( false ), 2000 );
			} )
			.catch( () => {
				setSettingsNotice( {
					status: 'error',
					message: __(
						'Failed to copy URL to clipboard.',
						'woocommerce-gateway-stripe'
					),
				} );
			} );
	};

	return (
		<div>
			<Card>
				<CardTitle>
					{ __(
						'About Agentic Commerce',
						'woocommerce-gateway-stripe'
					) }
				</CardTitle>
				<p>
					{ __(
						"Agentic Commerce lets AI-powered agents browse and purchase products from your store on behalf of your customers. Your product catalog is synced to Stripe so that AI agents can discover your products and complete purchases through Stripe's delegated checkout flow.",
						'woocommerce-gateway-stripe'
					) }
				</p>
				<p>
					<ExternalLink
						href="https://docs.stripe.com/agentic-commerce"
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __(
							'Learn more about Agentic Checkout',
							'woocommerce-gateway-stripe'
						) }
					</ExternalLink>
				</p>

				{ ! isLoadingSettings &&
					isFeatureEnabled &&
					! webhookSecret && (
						<>
							<p>
								<strong>
									{ __(
										'Getting started on the Stripe side:',
										'woocommerce-gateway-stripe'
									) }
								</strong>
							</p>

							<OnboardingSteps>
								<li>
									{ interpolateComponents( {
										mixedString: __(
											'Go to {{agenticLink}}Payments > Agentic Commerce{{/agenticLink}} in your Stripe Dashboard.',
											'woocommerce-gateway-stripe'
										),
										components: {
											agenticLink: (
												<ExternalLink
													href={ agenticCommerceUrl }
												/>
											),
										},
									} ) }
								</li>
								<li>
									{ __(
										'Follow the setup instructions to enable the feature and add a webhook endpoint for delegated checkout events:',
										'woocommerce-gateway-stripe'
									) }
									<TextControl
										type="text"
										value={ decodeURIComponent(
											webhookURLForDisplay
										) }
										autoComplete="off"
										disabled={ true }
									/>
									<Button
										variant="secondary"
										onClick={ handleCopy }
									>
										{ webhookURLCopied
											? __(
													'Copied!',
													'woocommerce-gateway-stripe'
											  )
											: __(
													'Copy',
													'woocommerce-gateway-stripe'
											  ) }
									</Button>
								</li>
								<li>
									{ __(
										'Copy the webhook signing secret from Developers > Webhooks and paste it in the settings below.',
										'woocommerce-gateway-stripe'
									) }
								</li>
							</OnboardingSteps>
						</>
					) }
			</Card>
			{ /* Settings card */ }
			<Card>
				<CardTitle>
					{ __(
						'Agentic Commerce Settings',
						'woocommerce-gateway-stripe'
					) }
				</CardTitle>

				{ settingsNotice && (
					<Notice
						status={ settingsNotice.status }
						onRemove={ () => setSettingsNotice( null ) }
						isDismissible
						style={ { marginBottom: '16px' } }
					>
						{ settingsNotice.message }
					</Notice>
				) }

				{ isLoadingSettings && (
					<p>{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }</p>
				) }

				{ ! isLoadingSettings && areSettingsLoaded && (
					<>
						<ToggleControl
							label={ __(
								'Enable Agentic Commerce',
								'woocommerce-gateway-stripe'
							) }
							help={ __(
								'When enabled, your product catalog will be synced to Stripe and AI agents can purchase on behalf of your customers.',
								'woocommerce-gateway-stripe'
							) }
							checked={ isFeatureEnabled }
							onChange={ setIsFeatureEnabled }
						/>

						{ isFeatureEnabled && (
							<TextControl
								label={ __(
									'Agentic Commerce Webhook Secret',
									'woocommerce-gateway-stripe'
								) }
								help={ __(
									'The webhook signing secret for delegated checkout events. Obtain this from Payments > Agentic Commerce in your Stripe Dashboard.',
									'woocommerce-gateway-stripe'
								) }
								type="password"
								value={ webhookSecret }
								onChange={ setWebhookSecret }
								autoComplete="off"
							/>
						) }

						<Actions>
							<Button
								variant="primary"
								isBusy={ isSavingSettings }
								disabled={
									isSavingSettings ||
									isLoadingSettings ||
									! areSettingsLoaded
								}
								onClick={ handleSaveSettings }
							>
								{ isSavingSettings
									? __(
											'Saving…',
											'woocommerce-gateway-stripe'
									  )
									: __(
											'Save Settings',
											'woocommerce-gateway-stripe'
									  ) }
							</Button>
						</Actions>
					</>
				) }
			</Card>

			{ isFeatureEnabled && <AgenticCommerceSyncStatus /> }
		</div>
	);
};

export default AgenticCommercePanel;
