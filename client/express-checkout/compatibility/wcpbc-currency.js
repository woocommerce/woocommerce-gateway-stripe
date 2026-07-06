/* global wc_price_based_country_ajax_geo_params */

import { addFilter } from '@wordpress/hooks';

const RETRIGGER_AFTER_MS = 3000;
const BAIL_AFTER_MS = 6000;

const isWCPBCAjaxModeActive = () =>
	// eslint-disable-next-line camelcase
	typeof wc_price_based_country_ajax_geo_params !== 'undefined';

const waitForWCPBCCurrency = ( upstream ) =>
	new Promise( ( resolve ) => {
		const $body = jQuery( document.body );
		const cleanups = [];
		const finish = ( value ) => {
			cleanups.forEach( ( fn ) => fn() );
			resolve( value );
		};

		const onEvent = ( _e, params ) => {
			if ( params?.code ) {
				finish( String( params.code ).toLowerCase() );
			}
		};
		$body.on( 'wc_price_based_country_set_currency_params', onEvent );
		cleanups.push( () =>
			$body.off( 'wc_price_based_country_set_currency_params', onEvent )
		);

		// WCPBC fires its event synchronously at priority 1, so we may
		// attach after their AJAX has already started. Re-trigger to
		// catch a second event.
		const retriggerTimer = setTimeout(
			() =>
				$body.triggerHandler(
					'wc_price_based_country_ajax_geolocation'
				),
			RETRIGGER_AFTER_MS
		);
		cleanups.push( () => clearTimeout( retriggerTimer ) );

		// Hard watchdog: surrender rather than hang ECE forever. A rejecting
		// upstream must still settle us, or ECE init would await this promise
		// indefinitely.
		const bailTimer = setTimeout(
			() =>
				Promise.resolve( upstream )
					.catch( () => undefined )
					.then( finish ),
			BAIL_AFTER_MS
		);
		cleanups.push( () => clearTimeout( bailTimer ) );
	} );

addFilter(
	'wc-stripe.express-checkout.resolved-currency',
	'automattic/wc-stripe/express-checkout/wcpbc',
	( upstream ) =>
		isWCPBCAjaxModeActive() ? waitForWCPBCCurrency( upstream ) : upstream
);
