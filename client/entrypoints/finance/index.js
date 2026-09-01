import React from 'react';
import { createRoot } from 'react-dom/client';
import FinancePage from './finance-page';

const container = document.getElementById( 'wc-stripe-finance-container' );

if ( container ) {
	const context = container.dataset.context ?? 'payouts';

	createRoot( container ).render( <FinancePage context={ context } /> );
}
