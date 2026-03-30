import React, { useState, useEffect, useCallback } from 'react';
import styled from '@emotion/styled';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Notice,
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

const StatusBadge = styled.span`
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
	margin-bottom: 12px;

	&.success {
		background: #d4edda;
		color: #155724;
	}
	&.error {
		background: #f8d7da;
		color: #721c24;
	}
	&.warning {
		background: #fff3cd;
		color: #856404;
	}
	&.info {
		background: #d1ecf1;
		color: #0c5460;
	}
	&.unknown {
		background: #e2e3e5;
		color: #383d41;
	}
`;

const DetailsTable = styled.table`
	border-collapse: collapse;
	margin-bottom: 12px;
	width: 100%;

	th {
		width: 160px;
		text-align: left;
		padding: 4px 8px 4px 0;
		font-weight: 600;
		vertical-align: top;
	}

	td {
		padding: 4px 0;
	}
`;

const Actions = styled.div`
	display: flex;
	gap: 8px;
	margin-top: 16px;
	align-items: center;
`;

const HistoryTable = styled.table`
	width: 100%;
	border-collapse: collapse;

	th,
	td {
		text-align: left;
		padding: 8px;
		border-bottom: 1px solid #f0f0f0;
	}

	th {
		font-weight: 600;
		background: #f9f9f9;
	}

	tr:last-child td {
		border-bottom: none;
	}

	code {
		font-size: 11px;
	}
`;

const FieldRow = styled.div`
	margin-bottom: 16px;

	label {
		display: block;
		font-weight: 600;
		margin-bottom: 4px;
	}

	p.description {
		margin: 4px 0 0;
		color: #646970;
		font-size: 13px;
	}
`;

const SecretPlaceholder = styled.span`
	font-family: monospace;
	color: #646970;
	font-size: 13px;
`;

const STATUS_CONFIG = {
	succeeded: {
		label: __( 'Success', 'woocommerce-gateway-stripe' ),
		className: 'success',
		icon: '✓',
	},
	pending: {
		label: __( 'Processing', 'woocommerce-gateway-stripe' ),
		className: 'info',
		icon: '⏳',
	},
	failed: {
		label: __( 'Failed', 'woocommerce-gateway-stripe' ),
		className: 'error',
		icon: '✗',
	},
	succeeded_with_errors: {
		label: __( 'Partial Success', 'woocommerce-gateway-stripe' ),
		className: 'warning',
		icon: '⚠',
	},
};

const SyncStatusBadge = ( { status } ) => {
	const config = STATUS_CONFIG[ status ] ?? {
		label: __( 'Unknown', 'woocommerce-gateway-stripe' ),
		className: 'unknown',
		icon: '?',
	};
	return (
		<StatusBadge className={ config.className }>
			{ config.icon } { config.label }
		</StatusBadge>
	);
};

const formatTimestamp = ( timestamp ) => {
	if ( ! timestamp ) return '—';
	return new Date( timestamp * 1000 ).toLocaleString();
};

const humanTimeDiff = ( timestamp ) => {
	if ( ! timestamp ) return '';
	const diffSec = Math.floor( Date.now() / 1000 ) - timestamp;
	if ( diffSec < 60 ) return __( 'just now', 'woocommerce-gateway-stripe' );
	if ( diffSec < 3600 ) {
		const m = Math.floor( diffSec / 60 );
		if ( m === 1 )
			return __( '1 minute ago', 'woocommerce-gateway-stripe' );
		return sprintf(
			/* translators: %d: number of minutes */
			__( '%d minutes ago', 'woocommerce-gateway-stripe' ),
			m
		);
	}
	const h = Math.floor( diffSec / 3600 );
	if ( h === 1 ) return __( '1 hour ago', 'woocommerce-gateway-stripe' );
	return sprintf(
		/* translators: %d: number of hours */
		__( '%d hours ago', 'woocommerce-gateway-stripe' ),
		h
	);
};

// Minimal sprintf for %d substitution.
const sprintf = ( fmt, ...args ) => fmt.replace( /%d/g, () => args.shift() );

// ---------------------------------------------------------------------------
// Onboarding panel
// ---------------------------------------------------------------------------

/**
 * Renders the merchant onboarding form for the Agentic Commerce integration.
 * Collects policy URLs, a customization hook URL, and a webhook secret, then
 * saves them via the REST API. Also provides a link to the Stripe Dashboard.
 */
const AgenticCommerceOnboardingPanel = () => {
	const [ settings, setSettings ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	// Local form state — keyed identically to the API fields.
	const [ termsUrl, setTermsUrl ] = useState( '' );
	const [ privacyUrl, setPrivacyUrl ] = useState( '' );
	const [ refundUrl, setRefundUrl ] = useState( '' );
	const [ hookUrl, setHookUrl ] = useState( '' );
	const [ hookSecret, setHookSecret ] = useState( '' );

	const fetchSettings = useCallback( async () => {
		setIsLoading( true );
		try {
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/merchant-settings',
			} );
			setSettings( result );
			setTermsUrl( result.terms_of_service_url ?? '' );
			setPrivacyUrl( result.privacy_policy_url ?? '' );
			setRefundUrl( result.refund_policy_url ?? '' );
			setHookUrl( result.hook_url ?? '' );
			// Never pre-fill the secret — show placeholder only.
			setHookSecret( '' );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ??
					__(
						'Failed to load Agentic Commerce settings.',
						'woocommerce-gateway-stripe'
					),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchSettings();
	}, [ fetchSettings ] );

	const handleSave = async () => {
		setIsSaving( true );
		setNotice( null );
		try {
			const body = {
				terms_of_service_url: termsUrl,
				privacy_policy_url: privacyUrl,
				refund_policy_url: refundUrl,
				hook_url: hookUrl,
			};
			// Only send secret when the user typed something new.
			if ( hookSecret ) {
				body.hook_secret = hookSecret;
			}
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/merchant-settings',
				method: 'POST',
				data: body,
			} );
			setSettings( result );
			setHookSecret( '' );
			setNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'woocommerce-gateway-stripe' ),
			} );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ??
					__(
						'Failed to save settings.',
						'woocommerce-gateway-stripe'
					),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<Card>
			<CardTitle>
				{ __( 'Agentic Commerce Setup', 'woocommerce-gateway-stripe' ) }
			</CardTitle>

			<p className="description">
				{ __(
					'Agentic Commerce lets AI agents browse and purchase products from your store on behalf of customers. Configure the policies and webhook below so Stripe can display accurate terms and notify your store about agent-initiated events.',
					'woocommerce-gateway-stripe'
				) }
			</p>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					isDismissible
				>
					{ notice.message }
				</Notice>
			) }

			{ isLoading && (
				<p>{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }</p>
			) }

			{ ! isLoading && (
				<>
					<FieldRow>
						<TextControl
							label={ __(
								'Terms & Conditions URL',
								'woocommerce-gateway-stripe'
							) }
							value={ termsUrl }
							onChange={ setTermsUrl }
							placeholder="https://example.com/terms"
							type="url"
						/>
						<p className="description">
							{ __(
								"Your store's Terms & Conditions page. Shown to customers during AI agent-assisted checkout.",
								'woocommerce-gateway-stripe'
							) }
						</p>
					</FieldRow>

					<FieldRow>
						<TextControl
							label={ __(
								'Privacy Policy URL',
								'woocommerce-gateway-stripe'
							) }
							value={ privacyUrl }
							onChange={ setPrivacyUrl }
							placeholder="https://example.com/privacy"
							type="url"
						/>
						<p className="description">
							{ __(
								"Your store's Privacy Policy page.",
								'woocommerce-gateway-stripe'
							) }
						</p>
					</FieldRow>

					<FieldRow>
						<TextControl
							label={ __(
								'Refund & Return Policy URL',
								'woocommerce-gateway-stripe'
							) }
							value={ refundUrl }
							onChange={ setRefundUrl }
							placeholder="https://example.com/returns"
							type="url"
						/>
						<p className="description">
							{ __(
								"Your store's Refund & Return Policy page.",
								'woocommerce-gateway-stripe'
							) }
						</p>
					</FieldRow>

					<FieldRow>
						<TextControl
							label={ __(
								'Customization Webhook URL',
								'woocommerce-gateway-stripe'
							) }
							value={ hookUrl }
							onChange={ setHookUrl }
							placeholder="https://example.com/stripe-hook"
							type="url"
						/>
						<p className="description">
							{ __(
								'URL that Stripe calls to allow real-time checkout customization by your store (for example custom line items or discounts).',
								'woocommerce-gateway-stripe'
							) }
						</p>
					</FieldRow>

					<FieldRow>
						<TextControl
							label={ __(
								'Webhook Secret',
								'woocommerce-gateway-stripe'
							) }
							value={ hookSecret }
							onChange={ setHookSecret }
							placeholder={
								settings?.hook_secret_is_set
									? __(
											'Leave blank to keep current secret',
											'woocommerce-gateway-stripe'
									  )
									: __(
											'Enter webhook signing secret',
											'woocommerce-gateway-stripe'
									  )
							}
							type="password"
							autoComplete="off"
						/>
						{ settings?.hook_secret_is_set && ! hookSecret && (
							<p className="description">
								{ __(
									'A webhook secret is already configured.',
									'woocommerce-gateway-stripe'
								) }{ ' ' }
								<SecretPlaceholder aria-hidden="true">
									{ settings.hook_secret }
								</SecretPlaceholder>
							</p>
						) }
						<p className="description">
							{ __(
								'Used to verify that webhook events originate from Stripe. Generate one in your Stripe Dashboard webhook settings.',
								'woocommerce-gateway-stripe'
							) }
						</p>
					</FieldRow>

					<Actions>
						<Button
							variant="primary"
							isBusy={ isSaving }
							disabled={ isSaving }
							onClick={ handleSave }
						>
							{ isSaving
								? __( 'Saving…', 'woocommerce-gateway-stripe' )
								: __(
										'Save Settings',
										'woocommerce-gateway-stripe'
								  ) }
						</Button>
						<ExternalLink href="https://dashboard.stripe.com/settings/agentic-commerce">
							{ __(
								'Manage on Stripe Dashboard',
								'woocommerce-gateway-stripe'
							) }
						</ExternalLink>
					</Actions>
				</>
			) }
		</Card>
	);
};

// ---------------------------------------------------------------------------
// Feed status panel
// ---------------------------------------------------------------------------

const AgenticCommercePanel = () => {
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSyncing, setIsSyncing ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const fetchStatus = useCallback( async () => {
		setIsLoading( true );
		try {
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce',
			} );
			setData( result );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ??
					__(
						'Failed to load sync status.',
						'woocommerce-gateway-stripe'
					),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchStatus();
	}, [ fetchStatus ] );

	const handleSync = async () => {
		setIsSyncing( true );
		setNotice( null );
		try {
			await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/sync',
				method: 'POST',
			} );
			setNotice( {
				status: 'success',
				message: __(
					'Sync triggered successfully.',
					'woocommerce-gateway-stripe'
				),
			} );
			await fetchStatus();
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ??
					__(
						'Sync failed. Check the WooCommerce logs for details.',
						'woocommerce-gateway-stripe'
					),
			} );
		} finally {
			setIsSyncing( false );
		}
	};

	const { last_sync: lastSync, history, next_sync: nextSync } = data ?? {};

	const nextSyncLabel = () => {
		if ( ! nextSync ) return null;
		const secondsUntil = nextSync - Math.floor( Date.now() / 1000 );
		if ( secondsUntil <= 0 )
			return __(
				'Next automatic sync: imminent.',
				'woocommerce-gateway-stripe'
			);
		const minutes = Math.ceil( secondsUntil / 60 );
		if ( minutes === 1 ) {
			return __(
				'Next automatic sync: in 1 minute.',
				'woocommerce-gateway-stripe'
			);
		}
		return sprintf(
			/* translators: %d: number of minutes until next sync */
			__(
				'Next automatic sync: in %d minutes.',
				'woocommerce-gateway-stripe'
			),
			minutes
		);
	};

	return (
		<div>
			<AgenticCommerceOnboardingPanel />

			<p className="description">
				{ __(
					'Monitors the product feed sync status for the Agentic Commerce integration.',
					'woocommerce-gateway-stripe'
				) }{ ' ' }
				<a
					href="https://dashboard.stripe.com/data-management/import-sets"
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __(
						'View import results on the Stripe Dashboard',
						'woocommerce-gateway-stripe'
					) }
				</a>
			</p>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					isDismissible
				>
					{ notice.message }
				</Notice>
			) }

			<Card>
				<CardTitle>
					{ __(
						'Product Feed Status',
						'woocommerce-gateway-stripe'
					) }
				</CardTitle>

				{ isLoading && (
					<p>{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }</p>
				) }
				{ ! isLoading && ! lastSync && (
					<p>
						{ __(
							'No syncs yet. Feed will sync automatically every 15 minutes.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				) }
				{ ! isLoading && lastSync && (
					<>
						<SyncStatusBadge status={ lastSync.status } />

						<DetailsTable>
							<tbody>
								{ lastSync.timestamp && (
									<tr>
										<th>
											{ __(
												'Last Sync',
												'woocommerce-gateway-stripe'
											) }
										</th>
										<td>
											{ humanTimeDiff(
												lastSync.timestamp
											) }{ ' ' }
											<small>
												(
												{ formatTimestamp(
													lastSync.timestamp
												) }
												)
											</small>
										</td>
									</tr>
								) }
								{ lastSync.products !== null && (
									<tr>
										<th>
											{ __(
												'Products Synced',
												'woocommerce-gateway-stripe'
											) }
										</th>
										<td>
											{ lastSync.products.toLocaleString() }
										</td>
									</tr>
								) }
								{ lastSync.import_set_id && (
									<tr>
										<th>
											{ __(
												'ImportSet ID',
												'woocommerce-gateway-stripe'
											) }
										</th>
										<td>
											<code>
												{ lastSync.import_set_id }
											</code>
										</td>
									</tr>
								) }
								{ lastSync.file_id && (
									<tr>
										<th>
											{ __(
												'File ID',
												'woocommerce-gateway-stripe'
											) }
										</th>
										<td>
											<code>{ lastSync.file_id }</code>
										</td>
									</tr>
								) }
							</tbody>
						</DetailsTable>

						{ nextSyncLabel() && (
							<p className="description">{ nextSyncLabel() }</p>
						) }

						{ lastSync.error && (
							<Notice status="error" isDismissible={ false }>
								<strong>
									{ __(
										'Last Sync Error:',
										'woocommerce-gateway-stripe'
									) }
								</strong>{ ' ' }
								{ lastSync.error }
							</Notice>
						) }
					</>
				) }

				<Actions>
					<Button
						variant="primary"
						isBusy={ isSyncing }
						disabled={ isSyncing || isLoading }
						onClick={ handleSync }
					>
						{ isSyncing
							? __( 'Syncing…', 'woocommerce-gateway-stripe' )
							: __( 'Sync Now', 'woocommerce-gateway-stripe' ) }
					</Button>
					<Button
						variant="secondary"
						href="/wp-admin/admin.php?page=wc-status&tab=logs"
					>
						{ __( 'View Logs', 'woocommerce-gateway-stripe' ) }
					</Button>
				</Actions>
			</Card>

			<Card>
				<CardTitle>
					{ __( 'Recent Syncs', 'woocommerce-gateway-stripe' ) }
				</CardTitle>

				{ isLoading && (
					<p>{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }</p>
				) }
				{ ! isLoading && ! history?.length && (
					<p>
						{ __(
							'No sync history available.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				) }
				{ ! isLoading && !! history?.length && (
					<HistoryTable>
						<thead>
							<tr>
								<th>
									{ __(
										'Timestamp',
										'woocommerce-gateway-stripe'
									) }
								</th>
								<th>
									{ __(
										'Products',
										'woocommerce-gateway-stripe'
									) }
								</th>
								<th>
									{ __(
										'Status',
										'woocommerce-gateway-stripe'
									) }
								</th>
								<th>
									{ __(
										'Import ID',
										'woocommerce-gateway-stripe'
									) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ history.map( ( entry, i ) => (
								<tr key={ i }>
									<td>
										{ entry.timestamp
											? new Date(
													entry.timestamp * 1000
											  ).toLocaleString( [], {
													year: 'numeric',
													month: '2-digit',
													day: '2-digit',
													hour: '2-digit',
													minute: '2-digit',
											  } )
											: '—' }
									</td>
									<td>
										{ entry.products !== null
											? entry.products.toLocaleString()
											: '—' }
									</td>
									<td>
										<SyncStatusBadge
											status={ entry.status }
										/>
										{ entry.error && (
											<span title={ entry.error }>
												{ ' ' }
												ℹ
											</span>
										) }
									</td>
									<td>
										{ entry.import_set_id ? (
											<code>{ entry.import_set_id }</code>
										) : (
											'—'
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</HistoryTable>
				) }
			</Card>
		</div>
	);
};

export default AgenticCommercePanel;
