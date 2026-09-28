import React, { useState, useEffect, useCallback } from 'react';
import styled from '@emotion/styled';
import { check, close, help, info, pending, warning } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardHeader,
	ExternalLink,
	Flex,
	Icon,
	Notice,
	Tooltip,
} from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import CardBody from 'wcstripe/settings/card-body';
import Pill from 'wcstripe/components/pill';
import { useTestMode } from 'wcstripe/data';

const DetailsTable = styled.table`
	border-collapse: collapse;
	margin: 16px 0 12px;
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

const HistoryTable = styled.table`
	width: 100%;
	border-collapse: collapse;
	table-layout: fixed;

	th,
	td {
		text-align: left;
		padding: 8px;
		border-bottom: 1px solid #f0f0f0;
		overflow: hidden;
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

	.wc-stripe-agentic-sync-col-timestamp {
		width: 170px;
		white-space: nowrap;
	}

	.wc-stripe-agentic-sync-col-products {
		width: 90px;
		text-align: center;
		white-space: nowrap;
	}

	.wc-stripe-agentic-sync-col-status {
		width: 200px;
		white-space: nowrap;
	}

	.wc-stripe-agentic-sync-col-import-id {
		width: auto;
	}

	.wc-stripe-agentic-sync-col-import-id code {
		display: flex;
		align-items: center;
		min-width: 0;
	}

	.wc-stripe-agentic-sync-col-import-id .id-start {
		flex: 0 1 auto;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.wc-stripe-agentic-sync-col-import-id .id-end {
		flex: 0 0 auto;
		white-space: nowrap;
	}
`;

const StatusPill = styled( Pill )`
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 2px 8px;
	border-radius: 2px;
	line-height: 16px;

	&.is-success {
		background: #edfaef;
		border-color: #edfaef;
		color: #005c12;
	}

	&.is-error {
		background: #fcf0f1;
		border-color: #fcf0f1;
		color: #8a2424;
	}

	&.is-warning {
		background: #fcf9e8;
		border-color: #fcf9e8;
		color: #674600;
	}

	&.is-info {
		background: #f0f6fc;
		border-color: #f0f6fc;
		color: #1d4a72;
	}

	&.is-neutral {
		background: #f0f0f0;
		border-color: #f0f0f0;
		color: #50575e;
	}

	svg {
		fill: currentColor;
	}
`;

// Focusable, non-interactive container for the inline error tooltip in the
// recent-syncs table. Stays a <span> (not a <button>) because the only action
// is revealing the wrapping Tooltip on hover/focus — there is nothing to click.
const ErrorInfoTarget = styled.span`
	display: inline-flex;
	align-items: center;
	justify-content: center;
	margin-left: 4px;
	color: inherit;
	cursor: help;
	vertical-align: middle;

	&:focus-visible {
		outline: 2px solid #2271b1;
		outline-offset: 1px;
		border-radius: 2px;
	}

	svg {
		fill: currentColor;
	}
`;

const IntroDescription = styled.p`
	margin-top: 16px;
`;

const SectionCard = styled( Card )`
	margin-bottom: 20px;

	&:last-child {
		margin-bottom: 0;
	}
`;

const SectionHeading = styled.h2`
	margin: 0;
	font-size: 14px;
`;

const NextSyncNote = styled.p`
	color: #646970;
	font-style: italic;
`;

const ActionRow = styled( Flex )`
	margin-top: 16px;
`;

/**
 * Map a Stripe ImportSet status into the bits the badge needs to render.
 *
 * @param {string} status ImportSet status as returned by the server.
 * @return {{ label: string, tone: string, icon: object }} Translated label,
 *   StatusPill modifier class, and a @wordpress/icons icon descriptor.
 */
const getStatusConfig = ( status ) => {
	switch ( status ) {
		case 'succeeded':
		case 'pending_archive':
		case 'archived':
			return {
				label: __( 'Success', 'woocommerce-gateway-stripe' ),
				tone: 'is-success',
				icon: check,
			};
		case 'pending':
			return {
				label: __( 'Processing', 'woocommerce-gateway-stripe' ),
				tone: 'is-info',
				icon: pending,
			};
		case 'creating_records':
			return {
				label: __( 'Creating records', 'woocommerce-gateway-stripe' ),
				tone: 'is-info',
				icon: pending,
			};
		case 'queued':
			return {
				label: __( 'Queued', 'woocommerce-gateway-stripe' ),
				tone: 'is-info',
				icon: pending,
			};
		case 'validating':
		case 'validating_records':
			return {
				label: __( 'Validating', 'woocommerce-gateway-stripe' ),
				tone: 'is-info',
				icon: pending,
			};
		case 'failed':
			return {
				label: __( 'Failed', 'woocommerce-gateway-stripe' ),
				tone: 'is-error',
				icon: close,
			};
		case 'succeeded_with_errors':
			return {
				label: __( 'Partial success', 'woocommerce-gateway-stripe' ),
				tone: 'is-warning',
				icon: warning,
			};
		default:
			return {
				label:
					typeof status === 'string' && status
						? status
						: __( 'Unknown', 'woocommerce-gateway-stripe' ),
				tone: 'is-neutral',
				icon: help,
			};
	}
};

/**
 * Statuses that indicate Stripe is still processing the ImportSet. While the
 * latest entry is in any of these states, the dashboard polls so the badge
 * flips to a terminal status without requiring a manual reload. Mirrors the
 * server-side REFRESHABLE_STATUSES list in the REST controller.
 */
const NON_TERMINAL_STATUSES = [
	'queued',
	'validating',
	'validating_records',
	'pending',
	'creating_records',
	'unknown',
];

const POLL_INTERVAL_MS = 5000;

const SyncStatusBadge = ( { status } ) => {
	const { label, tone, icon } = getStatusConfig( status );
	return (
		<StatusPill className={ tone }>
			<Icon icon={ icon } size={ 14 } />
			<span>{ label }</span>
		</StatusPill>
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
		return sprintf(
			/* translators: %d: number of minutes */
			_n(
				'%d minute ago',
				'%d minutes ago',
				m,
				'woocommerce-gateway-stripe'
			),
			m
		);
	}
	const h = Math.floor( diffSec / 3600 );
	return sprintf(
		/* translators: %d: number of hours */
		_n( '%d hour ago', '%d hours ago', h, 'woocommerce-gateway-stripe' ),
		h
	);
};

const AgenticCommerceSyncStatus = ( {
	isOnboardingComplete = false,
	hasUnsavedWebhookSecret = false,
} ) => {
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSyncing, setIsSyncing ] = useState( false );
	const [ hasError, setHasError ] = useState( false );

	const fetchStatus = useCallback( async () => {
		setIsLoading( true );
		setHasError( false );
		try {
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/status',
			} );
			setData( result );
		} catch ( err ) {
			setHasError( true );
			dispatch( 'core/notices' ).createErrorNotice(
				__(
					'Failed to load sync status.',
					'woocommerce-gateway-stripe'
				)
			);
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchStatus();
	}, [ fetchStatus ] );

	// Auto-refresh while the latest sync is still being processed by Stripe.
	// Each fetch re-runs the server-side lazy refresh of pending entries, so
	// history rows also stay current without polling them individually.
	const latestStatus = data?.last_sync?.status;
	useEffect( () => {
		if ( ! NON_TERMINAL_STATUSES.includes( latestStatus ) ) {
			return undefined;
		}
		const intervalId = setInterval( fetchStatus, POLL_INTERVAL_MS );
		return () => clearInterval( intervalId );
	}, [ latestStatus, fetchStatus ] );

	const handleSync = async () => {
		setIsSyncing( true );
		try {
			await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/sync',
				method: 'POST',
			} );
			dispatch( 'core/notices' ).createSuccessNotice(
				__(
					'Sync triggered successfully.',
					'woocommerce-gateway-stripe'
				)
			);
			await fetchStatus();
		} catch ( err ) {
			dispatch( 'core/notices' ).createErrorNotice(
				__(
					'Sync failed. Check the WooCommerce logs for details.',
					'woocommerce-gateway-stripe'
				)
			);
		} finally {
			setIsSyncing( false );
		}
	};

	const { last_sync: lastSync, history, next_sync: nextSync } = data ?? {};

	const [ isTestMode ] = useTestMode();
	const importSetsUrl = isTestMode
		? 'https://dashboard.stripe.com/test/data-management/import-sets'
		: 'https://dashboard.stripe.com/data-management/import-sets';

	const computeNextSyncLabel = () => {
		if ( ! nextSync ) return null;
		const secondsUntil = nextSync - Math.floor( Date.now() / 1000 );
		if ( secondsUntil <= 0 )
			return __(
				'Next automatic sync: imminent.',
				'woocommerce-gateway-stripe'
			);
		const minutes = Math.ceil( secondsUntil / 60 );
		return sprintf(
			/* translators: %d: number of minutes until next sync */
			_n(
				'Next automatic sync: in %d minute.',
				'Next automatic sync: in %d minutes.',
				minutes,
				'woocommerce-gateway-stripe'
			),
			minutes
		);
	};
	const nextSyncLabel = computeNextSyncLabel();

	return (
		<>
			<IntroDescription>
				{ __(
					'Monitors the product feed sync status for the agentic commerce integration.',
					'woocommerce-gateway-stripe'
				) }{ ' ' }
				<ExternalLink href={ importSetsUrl }>
					{ __(
						'View results on the Stripe Dashboard',
						'woocommerce-gateway-stripe'
					) }
				</ExternalLink>
			</IntroDescription>

			<SectionCard>
				<CardHeader>
					<SectionHeading>
						{ __(
							'Product feed status',
							'woocommerce-gateway-stripe'
						) }
					</SectionHeading>
				</CardHeader>
				<CardBody>
					{ isLoading && (
						<p>
							{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }
						</p>
					) }
					{ ! isLoading && ! hasError && ! lastSync && (
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
													'Last sync',
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
									{ typeof lastSync.products === 'number' && (
										<tr>
											<th>
												{ __(
													'Products synced',
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
												<code>
													{ lastSync.file_id }
												</code>
											</td>
										</tr>
									) }
								</tbody>
							</DetailsTable>

							{ nextSyncLabel && (
								<NextSyncNote>{ nextSyncLabel }</NextSyncNote>
							) }

							{ lastSync.error && (
								<Notice status="error" isDismissible={ false }>
									<strong>
										{ __(
											'Last sync error:',
											'woocommerce-gateway-stripe'
										) }
									</strong>{ ' ' }
									{ lastSync.error }
								</Notice>
							) }
						</>
					) }

					<ActionRow justify="flex-start" gap={ 2 }>
						<Button
							variant="primary"
							isBusy={ isSyncing }
							disabled={
								isSyncing || isLoading || ! isOnboardingComplete
							}
							onClick={ handleSync }
						>
							{ isSyncing
								? __( 'Syncing…', 'woocommerce-gateway-stripe' )
								: __(
										'Sync now',
										'woocommerce-gateway-stripe'
								  ) }
						</Button>
						<Button
							variant="secondary"
							href="/wp-admin/admin.php?page=wc-status&tab=logs"
						>
							{ __( 'View logs', 'woocommerce-gateway-stripe' ) }
						</Button>
					</ActionRow>

					{ ! isOnboardingComplete && (
						<p className="wc-stripe-agentic-sync-onboarding-notice">
							{ hasUnsavedWebhookSecret
								? __(
										'Click Save changes to store your webhook secret before syncing your catalog.',
										'woocommerce-gateway-stripe'
								  )
								: __(
										'Save your webhook secret above to finish setup before syncing your catalog.',
										'woocommerce-gateway-stripe'
								  ) }
						</p>
					) }
				</CardBody>
			</SectionCard>

			<SectionCard>
				<CardHeader>
					<SectionHeading>
						{ __( 'Recent syncs', 'woocommerce-gateway-stripe' ) }
					</SectionHeading>
				</CardHeader>
				<CardBody>
					{ isLoading && (
						<p>
							{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }
						</p>
					) }
					{ ! isLoading && ! hasError && ! history?.length && (
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
									<th className="wc-stripe-agentic-sync-col-timestamp">
										{ __(
											'Timestamp',
											'woocommerce-gateway-stripe'
										) }
									</th>
									<th className="wc-stripe-agentic-sync-col-products">
										{ __(
											'Products',
											'woocommerce-gateway-stripe'
										) }
									</th>
									<th className="wc-stripe-agentic-sync-col-status">
										{ __(
											'Status',
											'woocommerce-gateway-stripe'
										) }
									</th>
									<th className="wc-stripe-agentic-sync-col-import-id">
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
										<td className="wc-stripe-agentic-sync-col-timestamp">
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
										<td className="wc-stripe-agentic-sync-col-products">
											{ typeof entry.products === 'number'
												? entry.products.toLocaleString()
												: '—' }
										</td>
										<td className="wc-stripe-agentic-sync-col-status">
											<SyncStatusBadge
												status={ entry.status }
											/>
											{ entry.error && (
												<Tooltip text={ entry.error }>
													<ErrorInfoTarget
														tabIndex={ 0 }
														role="img"
														aria-label={ sprintf(
															/* translators: %s: sync error message */
															__(
																'Sync error: %s',
																'woocommerce-gateway-stripe'
															),
															entry.error
														) }
													>
														<Icon
															icon={ info }
															size={ 16 }
														/>
													</ErrorInfoTarget>
												</Tooltip>
											) }
										</td>
										<td className="wc-stripe-agentic-sync-col-import-id">
											{ entry.import_set_id ? (
												<code
													title={
														entry.import_set_id
													}
												>
													<span className="id-start">
														{ entry.import_set_id.slice(
															0,
															-6
														) }
													</span>
													<span className="id-end">
														{ entry.import_set_id.slice(
															-6
														) }
													</span>
												</code>
											) : (
												'—'
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</HistoryTable>
					) }
				</CardBody>
			</SectionCard>
		</>
	);
};

export default AgenticCommerceSyncStatus;
