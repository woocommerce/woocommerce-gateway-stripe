import React, { useEffect, useState } from 'react';
import DiagnosticsTraces from './diagnostics-traces';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { NAMESPACE } from 'wcstripe/data/constants';
import {
	useDiagnosticsMode,
	useDiagnosticsCaptureLimit,
	useDiagnosticsCaptureLimitPresets,
} from 'wcstripe/data';

const SUMMARY_PATH = `${ NAMESPACE }/diagnostics/summary`;

const DiagnosticsMode = () => {
	const [ isDiagnosticsChecked, setIsDiagnosticsChecked ] =
		useDiagnosticsMode();
	const [ captureLimit, setCaptureLimit ] = useDiagnosticsCaptureLimit();
	const captureLimitPresets = useDiagnosticsCaptureLimitPresets();
	// Rolling 24-hour count of /events 429s for this store. Read from the
	// summary endpoint so a merchant can tell whether the recorder is being
	// throttled (typically wallet bursts or shared-NAT traffic) without
	// needing access to fleet Tracks data. Fetched here rather than in
	// DiagnosticsTraces so the indicator survives even when the trace list
	// is empty — that "I see no traces" case is exactly when a merchant
	// needs to know whether the rate limit ate them.
	const [ rateLimitedCount, setRateLimitedCount ] = useState( 0 );

	useEffect( () => {
		apiFetch( { path: SUMMARY_PATH } )
			.then( ( response ) =>
				setRateLimitedCount(
					Number( response?.rate_limited_count ) || 0
				)
			)
			.catch( () => setRateLimitedCount( 0 ) );
	}, [] );

	return (
		<>
			<div className="wc-stripe-diagnostics-header">
				<div className="wc-stripe-diagnostics-header__text">
					<h4>
						{ __(
							'Checkout diagnostics',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<p>
						{ __(
							'Records structured traces of checkout sessions so support can diagnose issues. Turns off automatically once the capture limit is reached.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				</div>
				<ToggleControl
					label={ __(
						'Capture checkout diagnostics',
						'woocommerce-gateway-stripe'
					) }
					checked={ isDiagnosticsChecked }
					onChange={ setIsDiagnosticsChecked }
				/>
			</div>
			{ rateLimitedCount > 0 && (
				<div
					className="wc-stripe-diagnostics-rate-limit-notice"
					role="status"
				>
					{ sprintf(
						/* translators: %d: number of rate-limited /events requests in the last 24h */
						_n(
							'%d diagnostics event request was rate-limited in the last 24 hours. Some traces may be incomplete; widen the window via the wc_stripe_diagnostics_events_rate_limit filter if this persists.',
							'%d diagnostics event requests were rate-limited in the last 24 hours. Some traces may be incomplete; widen the window via the wc_stripe_diagnostics_events_rate_limit filter if this persists.',
							rateLimitedCount,
							'woocommerce-gateway-stripe'
						),
						rateLimitedCount
					) }
				</div>
			) }
			<DiagnosticsTraces
				isRecording={ isDiagnosticsChecked }
				captureLimit={ Number( captureLimit ) }
				captureLimitPresets={ captureLimitPresets }
				onChangeCaptureLimit={ setCaptureLimit }
			/>
		</>
	);
};

export default DiagnosticsMode;
