import React from 'react';
import ReactDOM from 'react-dom';
import PaymentRequestsPage from './payment-request-page';

const container = document.getElementById(
	'wc-stripe-payment-request-settings-container'
);

if ( container ) {
	ReactDOM.render( <PaymentRequestsPage />, container );
}
