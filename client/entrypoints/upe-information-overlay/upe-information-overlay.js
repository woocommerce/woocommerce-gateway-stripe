import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
import React, { useState } from 'react';
import styled from '@emotion/styled';
import { Modal, Button } from '@wordpress/components';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';

const INFORMATION_OVERLAY_OPTION = 'wc_stripe_show_information_overlay';

const InformationOverlayWrapper = styled( Modal )`
	transform: translate( -50%, 0 );
	.components-modal__content {
		max-width: 400px;

		@media ( max-width: 660px ) {
			max-width: inherit;
		}
	}

	.components-modal__header {
		border-bottom: none;
		margin-bottom: 0;
	}
`;

const Actions = styled.div`
	margin-top: 16px;
`;

const UpeInformationOverlay = () => {
	const [ isOverlayVisible, setIsOverlayVisible ] = useState( true );
	const { updateOptions } = useDispatch( OPTIONS_STORE_NAME );

	const scrollDown = setInterval( () => {
		const stripeRow = document.querySelector(
			'tr[data-gateway_id="stripe"]'
		);
		const stripeRowTop = stripeRow.getBoundingClientRect().top;
		const body = document.querySelector( 'body,html' );
		const top = body.scrollTop + 50;
		if ( top > stripeRowTop ) {
			clearInterval( scrollDown );
			changeElementStyles();
		} else {
			body.scrollTop = top;
		}
	}, 20 );

	const changeElementStyles = () => {
		const modal = document.querySelector(
			'div[class^="components-modal__frame"]'
		);

		if ( ! modal ) {
			return;
		}

		const stripeRow = document.querySelector(
			'tr[data-gateway_id="stripe"]'
		);

		//position the modal
		const stripeRowBottom = stripeRow.getBoundingClientRect().bottom;
		modal.style.top = `${ stripeRowBottom + 30 }px`;

		// highlight the Stripe row
		stripeRow.style.background = 'white';
		stripeRow.style.position = 'relative';
		stripeRow.style[ 'z-index' ] = 1000000;
	};

	const handleDismiss = useCallback( () => {
		updateOptions( {
			[ INFORMATION_OVERLAY_OPTION ]: 'no',
		} );
		setIsOverlayVisible( false );

		// revert highlighting of the Stripe row
		const stripeRow = document.querySelector(
			'tr[data-gateway_id="stripe"]'
		);
		stripeRow.style.background = '';
		stripeRow.style.position = '';
		stripeRow.style[ 'z-index' ] = '';
	}, [ updateOptions ] );

	if ( ! isOverlayVisible ) {
		return null;
	}

	return (
		<InformationOverlayWrapper
			title={ __(
				'View your Stripe payment methods',
				'woocommerce-gateway-stripe'
			) }
			onRequestClose={ handleDismiss }
		>
			{ __(
				'In the new payment management experience, you can view and manage all supported Stripe-powered payment methods in a single place.',
				'woocommerce-gateway-stripe'
			) }
			<Actions>
				<Button onClick={ handleDismiss } isPrimary>
					Got it
				</Button>
			</Actions>
		</InformationOverlayWrapper>
	);
};

export default UpeInformationOverlay;
