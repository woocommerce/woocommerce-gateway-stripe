/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import PaymentRequestsSettings from './payment-request-settings';

const container = document.getElementById(
	'wc_stripe-payment-requests_customizer_container'
);

if ( container ) {
	ReactDOM.render( <PaymentRequestsSettings />, container );
}
