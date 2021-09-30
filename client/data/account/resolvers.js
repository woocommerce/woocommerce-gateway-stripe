import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { NAMESPACE } from '../constants';
import { updateAccount } from './actions';

/**
 * Retrieve the account data from the site's REST API.
 */
export function* getAccountData() {
	const path = `${ NAMESPACE }/account`;

	try {
		const result = yield apiFetch( { path } );
		yield updateAccount( result );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error retrieving account data.', 'woocommerce-gateway-stripe' )
		);
	}
}
