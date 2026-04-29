import React, {
	useState,
	useEffect,
	useCallback,
	useImperativeHandle,
	forwardRef,
} from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import styled from '@emotion/styled';
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import CopyButton from '../../components/copy-button';
import AgenticCommerceSyncStatus from './sync-status';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	Notice,
	CheckboxControl,
	TextControl,
	ExternalLink,
	Card,
} from '@wordpress/components';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import { useAccount } from 'wcstripe/data/account';
import { useTestMode } from 'wcstripe/data';
import { HorizontalRule } from '@wordpress/primitives';

const OnboardingSteps = styled.ol`
	margin: 12px 0 24px;
	padding-left: 20px;

	li {
		margin-bottom: 6px;
		color: #757575;
		font-size: 12px;
	}
`;

const AgenticCommerceDescription = () => (
	<>
		<h2>{ __( 'Agentic commerce', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'Enable and configure agentic commerce for your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<p>
			<ExternalLink href="https://docs.stripe.com/agentic-commerce">
				{ __(
					'Learn more about agentic commerce',
					'woocommerce-gateway-stripe'
				) }
			</ExternalLink>
		</p>
	</>
);

const AgenticCommerceSection = forwardRef( ( props, ref ) => {
	const [ isFeatureEnabled, setIsFeatureEnabled ] = useState( false );
	const [ webhookSecret, setWebhookSecret ] = useState( '' );
	const [ isLoadingSettings, setIsLoadingSettings ] = useState( true );
	const [ settingsNotice, setSettingsNotice ] = useState( null );

	const [ isTestMode ] = useTestMode();
	const mode = isTestMode ? 'test' : 'live';
	const { data } = useAccount();
	const webhookURLForDisplay = data?.configured_webhook_urls?.[ mode ] ?? '';
	const agenticCommerceUrl = isTestMode
		? 'https://dashboard.stripe.com/test/agentic-commerce'
		: 'https://dashboard.stripe.com/agentic-commerce';

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

	const handleSaveSettings = useCallback( async () => {
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
		}
	}, [ isFeatureEnabled, webhookSecret ] );

	// Expose save function to parent via ref so the global Save changes
	// button can trigger it alongside the main settings save.
	useImperativeHandle(
		ref,
		() => ( {
			save: handleSaveSettings,
		} ),
		[ handleSaveSettings ]
	);

	return (
		<SettingsSection Description={ AgenticCommerceDescription }>
			<LoadableSettingsSection numLines={ 10 }>
				<Card>
					<CardBody>
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

						{ isLoadingSettings ? (
							<p>
								{ __(
									'Loading\u2026',
									'woocommerce-gateway-stripe'
								) }
							</p>
						) : (
							<>
								<CheckboxControl
									label={ __(
										'Enable agentic commerce',
										'woocommerce-gateway-stripe'
									) }
									help={ __(
										'When enabled, your product catalog will be synced to Stripe and AI agents will be able to purchase on behalf of your customers.',
										'woocommerce-gateway-stripe'
									) }
									checked={ isFeatureEnabled }
									onChange={ setIsFeatureEnabled }
								/>

								{ isFeatureEnabled && (
									<>
										<HorizontalRule
											className="wcstripe-agentic-commerce-onboarding__separator"
											style={ { margin: '24px 0' } }
										/>
										<p>
											<strong>
												{ __(
													'Getting started',
													'woocommerce-gateway-stripe'
												) }
											</strong>
										</p>

										<OnboardingSteps>
											<li>
												{ interpolateComponents( {
													mixedString: __(
														'Log into your {{agenticLink}}Stripe Dashboard{{/agenticLink}} and go to {{strong}}Payments > Agentic commerce{{/strong}}',
														'woocommerce-gateway-stripe'
													),
													components: {
														agenticLink: (
															<ExternalLink
																href={
																	agenticCommerceUrl
																}
															/>
														),
														strong: <strong />,
													},
												} ) }
											</li>
											<li>
												{ __(
													'Follow the setup instructions to enable the feature',
													'woocommerce-gateway-stripe'
												) }
											</li>
											<li>
												{ webhookURLForDisplay
													? interpolateComponents( {
															mixedString:
																sprintf(
																	/* translators: %s: the site's URL where webhooks will be sent.*/
																	__(
																		'Set endpoint URL as {{webhookURL}}%s{{/webhookURL}} {{copyButton/}}',
																		'woocommerce-gateway-stripe'
																	),
																	decodeURIComponent(
																		webhookURLForDisplay
																	)
																),
															components: {
																webhookURL: (
																	<strong />
																),
																copyButton: (
																	<CopyButton
																		text={ decodeURIComponent(
																			webhookURLForDisplay
																		) }
																	/>
																),
															},
													  } )
													: interpolateComponents( {
															mixedString: __(
																'Setup webhooks in {{strong}}Account details{{/strong}} above, then set endpoint URL to your webhook URL',
																'woocommerce-gateway-stripe'
															),
															components: {
																strong: (
																	<strong />
																),
															},
													  } ) }
											</li>
											<li>
												{ interpolateComponents( {
													mixedString: __(
														'Go to {{strong}}Developers > Webhooks{{/strong}} and copy and paste the webhook secret into the field below',
														'woocommerce-gateway-stripe'
													),
													components: {
														strong: <strong />,
													},
												} ) }
											</li>
										</OnboardingSteps>

										<TextControl
											label={ __(
												'Agentic commerce webhook secret',
												'woocommerce-gateway-stripe'
											) }
											help={ __(
												'Get the webhook signing secret in the Stripe dashboard to enable this feature.',
												'woocommerce-gateway-stripe'
											) }
											type="password"
											value={ webhookSecret }
											onChange={ setWebhookSecret }
											autoComplete="off"
										/>
									</>
								) }
							</>
						) }
					</CardBody>
				</Card>
			</LoadableSettingsSection>

			{ isFeatureEnabled && <AgenticCommerceSyncStatus /> }
		</SettingsSection>
	);
} );

AgenticCommerceSection.displayName = 'AgenticCommerceSection';

export default AgenticCommerceSection;
