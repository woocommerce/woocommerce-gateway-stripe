import React, { useEffect, useState, useCallback } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { NAMESPACE } from 'wcstripe/data/constants';
import { recordEvent } from 'wcstripe/tracking';

// Clipboard writes silently truncate or fail above a few MB on most
// browsers, and ticket forms strip large pastes long before that. Falling
// back to a file download above this size is cheap insurance.
const CLIPBOARD_BYTE_THRESHOLD = 1024 * 1024;

// Status filter used by the primary "Copy failed traces for support"
// button. Mirrors the spec's "what support actually needs" — failures plus
// the shopper-walked-away signal that finalizes via `express.cancel`.
const SUPPORT_STATUSES = [ 'failed', 'abandoned' ];

const buildTracesPath = ( statuses ) => {
	if ( ! statuses ) {
		return `${ NAMESPACE }/diagnostics/traces`;
	}
	const query = statuses
		.map( ( s ) => `status[]=${ encodeURIComponent( s ) }` )
		.join( '&' );
	return `${ NAMESPACE }/diagnostics/traces?${ query }`;
};

const downloadAsFile = ( contents ) => {
	const blob = new Blob( [ contents ], { type: 'application/json' } );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	const stamp = new Date().toISOString().replace( /[:.]/g, '-' );
	link.href = url;
	link.download = `wc-stripe-diagnostics-${ stamp }.json`;
	document.body.appendChild( link );
	link.click();
	document.body.removeChild( link );
	// Defer the revoke to give Safari/iOS time to start the download —
	// synchronous revoke can produce a 0-byte file there.
	setTimeout( () => URL.revokeObjectURL( url ), 0 );
};

const DiagnosticsTraces = () => {
	const [ summary, setSummary ] = useState( null );
	const [ isCopying, setIsCopying ] = useState( false );
	const [ feedback, setFeedback ] = useState( null );

	const refreshSummary = useCallback( () => {
		apiFetch( { path: `${ NAMESPACE }/diagnostics/summary` } )
			.then( setSummary )
			.catch( () => setSummary( { counts: {}, total: 0 } ) );
	}, [] );

	useEffect( () => {
		refreshSummary();
	}, [ refreshSummary ] );

	if ( ! summary || ! summary.total ) {
		return null;
	}

	const counts = summary.counts || {};
	const failed = counts.failed || 0;
	const abandoned = counts.abandoned || 0;
	const completed = counts.completed || 0;
	const pending = counts.pending || 0;
	const supportCount = failed + abandoned;

	// Build the breakdown list dynamically so empty buckets disappear
	// rather than showing a noisy "0 abandoned" segment.
	const breakdownSegments = [
		failed > 0 &&
			sprintf(
				/* translators: %d: number of failed traces */
				__( '%d failed', 'woocommerce-gateway-stripe' ),
				failed
			),
		abandoned > 0 &&
			sprintf(
				/* translators: %d: number of abandoned traces */
				__( '%d abandoned', 'woocommerce-gateway-stripe' ),
				abandoned
			),
		completed > 0 &&
			sprintf(
				/* translators: %d: number of succeeded traces */
				__( '%d succeeded', 'woocommerce-gateway-stripe' ),
				completed
			),
		pending > 0 &&
			sprintf(
				/* translators: %d: number of pending traces */
				__( '%d pending', 'woocommerce-gateway-stripe' ),
				pending
			),
	].filter( Boolean );

	const handleCopy = async ( statuses ) => {
		// 'support' when filtering to SUPPORT_STATUSES (failed + abandoned), 'all' otherwise.
		const scope = statuses ? 'support' : 'all';
		setIsCopying( true );
		setFeedback( null );
		try {
			const response = await apiFetch( {
				path: buildTracesPath( statuses ),
			} );
			const json = JSON.stringify( response.traces, null, 2 );
			const bytes = new Blob( [ json ] ).size;

			if ( bytes > CLIPBOARD_BYTE_THRESHOLD ) {
				downloadAsFile( json );
				recordEvent( 'wcstripe_diagnostics_copy_traces', {
					scope,
					result: 'download',
					count: response.count,
				} );
				setFeedback( {
					status: 'success',
					message: __(
						'Trace bundle was too large to copy — downloaded as a file instead.',
						'woocommerce-gateway-stripe'
					),
				} );
			} else {
				await navigator.clipboard.writeText( json );
				recordEvent( 'wcstripe_diagnostics_copy_traces', {
					scope,
					result: 'clipboard',
					count: response.count,
				} );
				setFeedback( {
					status: 'success',
					message: sprintf(
						/* translators: %d: number of traces */
						_n(
							'Copied %d trace to clipboard.',
							'Copied %d traces to clipboard.',
							response.count,
							'woocommerce-gateway-stripe'
						),
						response.count
					),
				} );
			}
			refreshSummary();
		} catch ( err ) {
			recordEvent( 'wcstripe_diagnostics_copy_traces', {
				scope,
				result: 'error',
				count: null,
			} );
			setFeedback( {
				status: 'error',
				message: __(
					'Could not copy traces. Try again.',
					'woocommerce-gateway-stripe'
				),
			} );
		} finally {
			setIsCopying( false );
		}
	};

	return (
		<div className="wc-stripe-diagnostics-traces">
			<p>
				{ sprintf(
					/* translators: 1: total trace count, 2: comma-separated breakdown of non-zero status counts */
					_n(
						'%1$d trace captured (%2$s)',
						'%1$d traces captured (%2$s)',
						summary.total,
						'woocommerce-gateway-stripe'
					),
					summary.total,
					breakdownSegments.join( ', ' )
				) }
			</p>
			<Button
				variant="primary"
				isBusy={ isCopying }
				disabled={ isCopying || supportCount === 0 }
				onClick={ () => handleCopy( SUPPORT_STATUSES ) }
			>
				{ sprintf(
					/* translators: %d: count of failed + abandoned traces */
					__(
						'Copy failed traces for support (%d)',
						'woocommerce-gateway-stripe'
					),
					supportCount
				) }
			</Button>{ ' ' }
			<Button
				variant="link"
				disabled={ isCopying }
				onClick={ () => handleCopy( null ) }
			>
				{ __( 'Copy all instead', 'woocommerce-gateway-stripe' ) }
			</Button>
			{ feedback && (
				<Notice
					status={ feedback.status }
					isDismissible
					onRemove={ () => setFeedback( null ) }
				>
					{ feedback.message }
				</Notice>
			) }
		</div>
	);
};

export default DiagnosticsTraces;
