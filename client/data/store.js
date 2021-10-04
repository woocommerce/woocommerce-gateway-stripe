import { registerStore, combineReducers } from '@wordpress/data';
import { controls } from '@wordpress/data-controls';
import { STORE_NAME } from './constants';
import * as settings from './settings';
import * as account from './account';
import * as accountKeys from './account-keys';

// Extracted into wrapper function to facilitate testing.
export const initStore = () =>
	registerStore( STORE_NAME, {
		reducer: combineReducers( {
			settings: settings.reducer,
			account: account.reducer,
			accountKeys: accountKeys.reducer,
		} ),
		actions: {
			...settings.actions,
			...account.actions,
			...accountKeys.actions,
		},
		controls,
		selectors: {
			...settings.selectors,
			...account.selectors,
			...accountKeys.selectors,
		},
		resolvers: {
			...settings.resolvers,
			...account.resolvers,
			...accountKeys.resolvers,
		},
	} );
