import React from 'react';
import ReactDOM from 'react-dom';
import UpeInformationOverlay from './upe-information-overlay';

const stripeRowTop = jQuery( 'tr[data-gateway_id="stripe"]' ).offset().top;
const windowHeight = jQuery( window ).height();

// waiting for the dom to be fully loaded as the section below the table takes time to load
jQuery( () => {
	const scrollTop =
		stripeRowTop > windowHeight / 2
			? stripeRowTop - windowHeight / 2
			: stripeRowTop;
	// scrolling so that the Stripe row is always within view
	jQuery( 'body,html' ).animate( { scrollTop }, 800, () => {
		const informationOverlayContainer = document.getElementById(
			'wc-stripe-information-overlay-container'
		);

		if ( informationOverlayContainer ) {
			ReactDOM.render(
				<UpeInformationOverlay />,
				informationOverlayContainer
			);
		}
	} );

	// highlight the Stripe row
	jQuery( 'tr[data-gateway_id="stripe"]' ).css( {
		background: 'white',
		position: 'relative',
		'z-index': '1000000',
	} );
} );
