/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import ExpressCheckoutsSettings from './express-checkout-settings';

const container = document.getElementById(
	'wc_stripe-express_checkouts_customizer-container'
);

if ( container ) {
	ReactDOM.render(
		<ExpressCheckoutsSettings methodId="payment_request" />,
		container
	);
}
