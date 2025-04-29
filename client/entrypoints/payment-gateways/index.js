import React from 'react';
import { createRoot } from 'react-dom/client';
import PaymentGatewaysConfirmation from './payment-gateways-confirmation';

const paymentGatewaysContainer = document.getElementById(
	'wc-stripe-payment-gateways-container'
);
if ( paymentGatewaysContainer ) {
	const paymentGatewaysRoot = createRoot( paymentGatewaysContainer );
	paymentGatewaysRoot.render( <PaymentGatewaysConfirmation /> );
}
