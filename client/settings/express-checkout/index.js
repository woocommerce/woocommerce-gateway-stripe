/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import ExpressCheckoutSettings from './express-checkout-settings';

const container = document.getElementById(
	'wc_stripe-express_checkouts_customizer-container'
);

if ( container ) {
	ReactDOM.render( <ExpressCheckoutSettings />, container );
}