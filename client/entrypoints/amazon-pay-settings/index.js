import React from 'react';
import { createRoot } from 'react-dom/client';
import AmazonPayPage from './amazon-pay-page';

const amazonPayContainer = document.getElementById(
	'wc-stripe-amazon-pay-settings-container'
);

if ( amazonPayContainer ) {
	const amazonPayRoot = createRoot( amazonPayContainer );
	amazonPayRoot.render( <AmazonPayPage /> );
}
