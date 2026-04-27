/* global wcStripeWhatsNewModalParams */
import React from 'react';
import { createRoot } from 'react-dom/client';
import WhatsNewModal from './whats-new-modal';
import './style.scss';

const container = document.getElementById( 'wc-stripe-whats-new-modal-root' );

if ( container && typeof wcStripeWhatsNewModalParams !== 'undefined' ) {
	createRoot( container ).render(
		<WhatsNewModal params={ wcStripeWhatsNewModalParams } />
	);
}
