import React from 'react';
import ReactDOM from 'react-dom';
import PaymentGatewaysConfirmation from './payment-gateways-confirmation';

const paymentGatewaysContainer = document.getElementById(
	'wc-stripe-payment-gateways-container'
);
if ( paymentGatewaysContainer ) {
	ReactDOM.render(
		<PaymentGatewaysConfirmation />,
		paymentGatewaysContainer
	);
}
