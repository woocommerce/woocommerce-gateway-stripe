import React from 'react';
import { createRoot } from 'react-dom/client';
import TransactionsPage from './transactions-page';

const container = document.getElementById( 'wc-stripe-finance-container' );

if ( container ) {
	createRoot( container ).render( <TransactionsPage /> );
}
