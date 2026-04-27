import React, { useState, useCallback } from 'react';
import { Modal, Button, ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const WhatsNewModal = ( { params } ) => {
	const [ isOpen, setIsOpen ] = useState( true );
	const {
		version,
		changes = [],
		fullChangelogUrl,
		dismissAjaxUrl,
		dismissAjaxAction,
		dismissNonce,
	} = params;

	const dismiss = useCallback( () => {
		setIsOpen( false );

		if ( ! dismissAjaxUrl || ! dismissAjaxAction || ! dismissNonce ) {
			return;
		}

		const body = new URLSearchParams();
		body.append( 'action', dismissAjaxAction );
		body.append( 'nonce', dismissNonce );

		// Fire-and-forget; UI already closed.
		window.fetch( dismissAjaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} );
	}, [ dismissAjaxUrl, dismissAjaxAction, dismissNonce ] );

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
			onRequestClose={ dismiss }
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
					<ExternalLink href={ fullChangelogUrl }>
						{ __(
							'View full changelog',
							'woocommerce-gateway-stripe'
						) }
					</ExternalLink>
				) }
				<Button variant="primary" onClick={ dismiss }>
					{ __( 'Got it', 'woocommerce-gateway-stripe' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default WhatsNewModal;
