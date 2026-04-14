import React from 'react';
import { createRoot } from 'react-dom/client';
import PluginsPageApp from './plugins-page-app';

const container = document.getElementById( 'wc-stripe-plugins-page-app' );
if ( container ) {
	createRoot( container ).render( <PluginsPageApp /> );
}
