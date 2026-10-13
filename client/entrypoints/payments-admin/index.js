import React from 'react';
import { createRoot } from 'react-dom/client';
import PaymentsPage from './payments-page';

const container = document.getElementById( 'wc-stripe-payments-container' );

if ( container ) {
	createRoot( container ).render( <PaymentsPage /> );
}
