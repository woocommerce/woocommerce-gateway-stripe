import React from 'react';
import ReactDOM from 'react-dom';
import UpeInformationOverlay from './upe-information-overlay';

const stripeRowTop = jQuery( 'tr[data-gateway_id="stripe"]' ).offset().top;
const windowHeight = jQuery( window ).height();

const informationOverlayContainer = document.getElementById(
	'wc-stripe-information-overlay-container'
);

if ( informationOverlayContainer ) {
	const scrollDown = setInterval( () => {
		const body = document.querySelector( 'body,html' );
		const top = body.scrollTop + 50;
		if ( top > stripeRowTop || top > windowHeight / 2 ) {
			clearInterval( scrollDown );
			ReactDOM.render(
				<UpeInformationOverlay />,
				informationOverlayContainer
			);
		} else {
			body.scrollTop = top;
		}
	}, 20 );
}
