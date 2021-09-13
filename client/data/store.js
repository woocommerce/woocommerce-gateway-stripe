/*
 * External dependencies
 */
import { registerStore, combineReducers } from '@wordpress/data';
import { controls } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './constants';
import * as settings from './settings';

// Extracted into wrapper function to facilitate testing.
export const initStore = () =>
	registerStore( STORE_NAME, {
		reducer: combineReducers( {
			settings: settings.reducer,
		} ),
		actions: {
			...settings.actions,
		},
		controls,
		selectors: {
			...settings.selectors,
		},
		resolvers: {
			...settings.resolvers,
		},
	} );
