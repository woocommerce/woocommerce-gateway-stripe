import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { NAMESPACE } from '../constants';
import { updateAccountKeys } from './actions';

/**
 * Retrieve settings from the site's REST API.
 */
export function* getAccountKeys() {
	const path = `${ NAMESPACE }/account_keys`;

	try {
		const result = yield apiFetch( { path } );
		yield updateAccountKeys( result );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error retrieving account keys.', 'woocommerce-gateway-stripe' )
		);
	}
}
