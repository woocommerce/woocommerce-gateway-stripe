import React from 'react';
import { createRoot } from 'react-dom/client';
import LinkPage from './link-page';

const linkContainer = document.getElementById(
	'wc-stripe-link-settings-container'
);

if ( linkContainer ) {
	createRoot( linkContainer ).render( <LinkPage /> );
}
