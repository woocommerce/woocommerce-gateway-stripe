import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { getQuery } from '@woocommerce/navigation';
import { NAMESPACE } from '../constants';
import { updatePaymentGateway } from './actions';

const { section } = getQuery();

/**
 * Retrieve payment gateway settings from the site's REST API.
 */
export function* getPaymentGateway() {
	const path = `${ NAMESPACE }/payment-gateway/${ section }`;

	try {
		const result = yield apiFetch( { path } );
		yield updatePaymentGateway( result );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__(
				'Error retrieving payment gateway settings.',
				'woocommerce-gateway-stripe'
			)
		);
	}
}
