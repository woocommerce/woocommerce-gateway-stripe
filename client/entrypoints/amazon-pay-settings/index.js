import React from 'react';
import ReactDOM from 'react-dom';
import AmazonPayPage from './amazon-pay-page';

const amazonPayContainer = document.getElementById(
	'wc-stripe-amazon-pay-settings-container'
);

if ( amazonPayContainer ) {
	ReactDOM.render( <AmazonPayPage />, amazonPayContainer );
}
