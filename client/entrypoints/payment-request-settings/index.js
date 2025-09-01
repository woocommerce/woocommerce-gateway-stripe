import React from 'react';
import { createRoot } from 'react-dom/client';
import PaymentRequestsPage from './payment-request-page';

const container = document.getElementById(
	'wc-stripe-payment-request-settings-container'
);

if ( container ) {
	createRoot( container ).render( <PaymentRequestsPage /> );
}
