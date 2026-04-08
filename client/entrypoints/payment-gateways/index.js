/* global wcStripeExitSurveyParams */
import React from 'react';
import { createRoot } from 'react-dom/client';
import PaymentGatewaysConfirmation from './payment-gateways-confirmation';
import PaymentsPageSurvey from './payments-page-survey';

const paymentGatewaysContainer = document.getElementById(
	'wc-stripe-payment-gateways-container'
);
if ( paymentGatewaysContainer ) {
	createRoot( paymentGatewaysContainer ).render(
		<PaymentGatewaysConfirmation />
	);
}

// Mount the exit survey listener for the WC core Payments page.
// Uses jQuery ajaxComplete to detect gateway toggle AJAX calls.
// Works independently of the legacy gateway toggle container.
if ( typeof wcStripeExitSurveyParams !== 'undefined' ) {
	const surveyContainer = document.createElement( 'div' );
	surveyContainer.id = 'wc-stripe-payments-page-survey';
	document.body.appendChild( surveyContainer );
	createRoot( surveyContainer ).render( <PaymentsPageSurvey /> );
}
