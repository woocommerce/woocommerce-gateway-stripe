import React, { useState, useEffect, useCallback } from 'react';
import styled from '@emotion/styled';
import AgenticCommerceSyncStatus from './sync-status';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Notice,
	ToggleControl,
	TextControl,
	ExternalLink,
} from '@wordpress/components';

const Card = styled.div`
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px 24px;
	margin-bottom: 20px;
`;

const CardTitle = styled.h2`
	font-size: 14px;
	font-weight: 600;
	margin: 0 0 16px;
	padding-bottom: 8px;
	border-bottom: 1px solid #eee;
`;

const Actions = styled.div`
	display: flex;
	gap: 8px;
	margin-top: 16px;
	align-items: center;
`;

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
	const [ isSavingSettings, setIsSavingSettings ] = useState( false );
	const [ settingsNotice, setSettingsNotice ] = useState( null );

	const fetchSettings = useCallback( async () => {
		setIsLoadingSettings( true );
		try {
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/settings',
			} );
			setIsFeatureEnabled( result.is_enabled );
			setWebhookSecret( result.webhook_secret ?? '' );
		} catch {
			// Settings fetch failure is non-fatal; defaults remain.
		} finally {
			setIsLoadingSettings( false );
		}
	}, [] );

	useEffect( () => {
		fetchSettings();
	}, [ fetchSettings ] );

	const handleSaveSettings = async () => {
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

	return (
		<div>
			{ /* Introduction card */ }
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
							'Read the Stripe Agentic Commerce documentation',
							'woocommerce-gateway-stripe'
						) }
					</ExternalLink>
				</p>
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
						{ __(
							'Log in to your Stripe Dashboard.',
							'woocommerce-gateway-stripe'
						) }
					</li>
					<li>
						{ __(
							'Go to Payments > Agentic Commerce.',
							'woocommerce-gateway-stripe'
						) }
					</li>
					<li>
						{ __(
							'Follow the setup instructions to enable the feature and create a webhook endpoint for delegated checkout events.',
							'woocommerce-gateway-stripe'
						) }
					</li>
					<li>
						{ __(
							'Copy the webhook signing secret and paste it in the settings below.',
							'woocommerce-gateway-stripe'
						) }
					</li>
				</OnboardingSteps>
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
					>
						{ settingsNotice.message }
					</Notice>
				) }

				{ isLoadingSettings ? (
					<p>{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }</p>
				) : (
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
									isSavingSettings || isLoadingSettings
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

			<AgenticCommerceSyncStatus isFeatureEnabled={ isFeatureEnabled } />
		</div>
	);
};

export default AgenticCommercePanel;
