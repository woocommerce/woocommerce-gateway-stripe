/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import PaymentRequestsPage from './payment-request-page';

const container = document.getElementById(
	'wc-stripe-payment-requests-settings-container'
);

if ( container ) {
	ReactDOM.render( <PaymentRequestsPage />, container );
}
