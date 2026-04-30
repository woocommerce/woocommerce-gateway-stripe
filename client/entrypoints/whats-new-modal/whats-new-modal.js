import React, { useState, useCallback, useEffect, useRef } from 'react';
import { Modal, Button, ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const recordEvent = ( name, props = {} ) => {
	if ( ! window.wcTracks || ! window.wcTracks.isEnabled ) {
		return;
	}
	const fn =
		( window.wc && window.wc.tracks && window.wc.tracks.recordEvent ) ||
		window.wcTracks.recordEvent;
	if ( typeof fn !== 'function' ) {
		return;
	}
	try {
		fn( name, props );
	} catch ( e ) {
		// Tracks is best-effort; never let it break the modal.
	}
};

const WhatsNewModal = ( { params } ) => {
	const [ isOpen, setIsOpen ] = useState( true );
	const mountedAt = useRef( Date.now() );
	const dismissedSource = useRef( 'unknown' );

	const {
		version,
		changes = [],
		isTestMode = false,
		fullChangelogUrl,
		dismissAjaxUrl,
		dismissAjaxAction,
		dismissNonce,
	} = params;

	const baseProps = {
		stripe_version: version,
		is_test_mode: isTestMode ? 'yes' : 'no',
		entry_count: changes.length,
	};

	useEffect( () => {
		recordEvent( 'wcstripe_whats_new_modal_impression', baseProps );
		// Run once on mount.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const dismiss = useCallback(
		( source ) => {
			const resolvedSource = source || dismissedSource.current;
			const dwellMs = Date.now() - mountedAt.current;

			setIsOpen( false );

			// The dismiss event is fired server-side from `handle_dismiss` so
			// the version/mode tagging matches the other modal events. Skipping
			// it here avoids a duplicate.

			if ( ! dismissAjaxUrl || ! dismissAjaxAction || ! dismissNonce ) {
				return;
			}

			const body = new URLSearchParams();
			body.append( 'action', dismissAjaxAction );
			body.append( 'nonce', dismissNonce );
			body.append( 'dwell_ms', String( dwellMs ) );
			body.append( 'source', resolvedSource );

			window.fetch( dismissAjaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body,
			} );
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ dismissAjaxUrl, dismissAjaxAction, dismissNonce ]
	);

	const handleChangelogClick = useCallback( () => {
		recordEvent( 'wcstripe_whats_new_modal_changelog_click', baseProps );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( ! isOpen ) {
		return null;
	}

	const title = sprintf(
		/* translators: %s: plugin version, e.g. 10.7.0 */
		__(
			"What's new in WooCommerce Stripe %s",
			'woocommerce-gateway-stripe'
		),
		version
	);

	return (
		<Modal
			title={ title }
			onRequestClose={ () => dismiss( 'close_icon' ) }
			className="wc-stripe-whats-new-modal"
			shouldCloseOnClickOutside={ false }
		>
			{ changes.length === 0 ? (
				<p>
					{ __(
						'This update includes improvements and fixes. See the full changelog for details.',
						'woocommerce-gateway-stripe'
					) }
				</p>
			) : (
				<ul className="wc-stripe-whats-new-modal__list">
					{ changes.map( ( change, index ) => (
						<li
							key={ index }
							className="wc-stripe-whats-new-modal__item"
						>
							{ change.tag && (
								<span className="wc-stripe-whats-new-modal__tag">
									{ change.tag }
								</span>
							) }
							<span className="wc-stripe-whats-new-modal__text">
								{ change.text }
							</span>
						</li>
					) ) }
				</ul>
			) }

			<div className="wc-stripe-whats-new-modal__actions">
				{ fullChangelogUrl && (
					<ExternalLink
						href={ fullChangelogUrl }
						onClick={ handleChangelogClick }
					>
						{ __(
							'View full changelog',
							'woocommerce-gateway-stripe'
						) }
					</ExternalLink>
				) }
				<Button
					variant="primary"
					onClick={ () => dismiss( 'primary_button' ) }
				>
					{ __( 'Got it', 'woocommerce-gateway-stripe' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default WhatsNewModal;
