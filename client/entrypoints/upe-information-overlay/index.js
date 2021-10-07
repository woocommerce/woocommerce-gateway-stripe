import React from 'react';
import ReactDOM from 'react-dom';
import UpeInformationOverlay from './upe-information-overlay';

const informationOverlayContainer = document.getElementById(
	'wc-stripe-information-overlay-container'
);

if ( informationOverlayContainer ) {
	ReactDOM.render( <UpeInformationOverlay />, informationOverlayContainer );
}
