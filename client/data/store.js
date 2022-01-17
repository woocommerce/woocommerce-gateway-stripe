import { registerStore, combineReducers } from '@wordpress/data';
import { controls } from '@wordpress/data-controls';
import { STORE_NAME } from './constants';
import * as settings from './settings';
import * as account from './account';
import * as accountKeys from './account-keys';
import * as paymentGateway from './payment-gateway';

const actions = {};
const selectors = {};
const resolvers = {};

[ settings, account, accountKeys, paymentGateway ].forEach( ( item ) => {
	Object.assign( actions, { ...item.actions } );
	Object.assign( selectors, { ...item.selectors } );
	Object.assign( resolvers, { ...item.resolvers } );
} );

// Extracted into wrapper function to facilitate testing.
export const initStore = () =>
	registerStore( STORE_NAME, {
		reducer: combineReducers( {
			settings: settings.reducer,
			account: account.reducer,
			accountKeys: accountKeys.reducer,
			paymentGateway: paymentGateway.reducer,
		} ),
		controls,
		actions,
		selectors,
		resolvers,
	} );
