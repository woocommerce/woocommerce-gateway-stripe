import React from 'react';
import { createRoot } from 'react-dom/client';
import ExpressCheckoutPage from './express-checkout-page';

const container = document.getElementById(
	'wc-stripe-payment-request-settings-container'
);

if ( container ) {
	createRoot( container ).render( <ExpressCheckoutPage /> );
}
