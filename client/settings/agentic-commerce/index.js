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
import AgenticCommerceFeedPreview from './feed-preview';
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
		<h2>{ __( 'Agentic Commerce', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'Enable and configure Agentic Commerce for your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<p>
			{ __(
				'WooCommerce coupons and their usage limits do not apply to purchases completed inside AI agents. Purchases redirected to your store use the standard checkout, where coupons work as usual.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<p>
			<ExternalLink href="https://docs.stripe.com/agentic-commerce">
				{ __(
					'Learn more about Agentic Commerce',
					'woocommerce-gateway-stripe'
				) }
			</ExternalLink>
		</p>
	</>
);

const AgenticCommerceSection = forwardRef( ( props, ref ) => {
	const [ isFeatureEnabled, setIsFeatureEnabled ] = useState( false );
	const [ disableCheckout, setDisableCheckout ] = useState( false );
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
			setDisableCheckout( result.disable_checkout ?? false );
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
					disable_checkout: disableCheckout,
					webhook_secret: webhookSecret,
				},
			} );
			setIsFeatureEnabled( result.is_enabled );
			setDisableCheckout( result.disable_checkout ?? false );
			setWebhookSecret( result.webhook_secret ?? '' );
			// No success notice: the global Save changes flow already shows a page-level toast.
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
	}, [ isFeatureEnabled, disableCheckout, webhookSecret ] );

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
										'Enable Agentic Commerce',
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
									<CheckboxControl
										label={ __(
											'Redirect shoppers to my store to check out',
											'woocommerce-gateway-stripe'
										) }
										help={ __(
											'When enabled, agents send shoppers to the product page on your store to complete checkout instead of purchasing in the agent. Your products are still discoverable in the agent.',
											'woocommerce-gateway-stripe'
										) }
										checked={ disableCheckout }
										onChange={ setDisableCheckout }
									/>
								) }

								{ isFeatureEnabled && (
									<p>
										{ interpolateComponents( {
											mixedString: __(
												'To keep specific products out of the catalog, exclude them on the Products screen — individually, via Quick Edit, or with the bulk actions. {{excludedLink}}View excluded products{{/excludedLink}}',
												'woocommerce-gateway-stripe'
											),
											components: {
												excludedLink: (
													// eslint-disable-next-line jsx-a11y/anchor-has-content
													<a
														href={
															window
																.wc_stripe_settings_params
																?.agentic_commerce_excluded_products_url ||
															'edit.php?post_type=product&wc_stripe_agentic_sync_status=excluded'
														}
													/>
												),
											},
										} ) }
									</p>
								) }

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
																'Set up webhooks in {{strong}}Account details{{/strong}} on the Settings tab, then set endpoint URL to your webhook URL',
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
												'Agentic Commerce webhook secret',
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

			{ isFeatureEnabled && <AgenticCommerceFeedPreview /> }
		</SettingsSection>
	);
} );

AgenticCommerceSection.displayName = 'AgenticCommerceSection';

export default AgenticCommerceSection;
