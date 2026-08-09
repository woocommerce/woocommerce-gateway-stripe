/* global wc_price_based_country_ajax_geo_params */

import { addFilter } from '@wordpress/hooks';

// WCPBC tells us the visitor's currency through a body event. it usually fires on
// its own, but if we started listening after it already fired, we missed it - so
// we poke it once after this delay to make it fire again.
const RETRIGGER_AFTER_MS = 3000;
// if WCPBC never answers, stop waiting after this so init doesn't hang. keeping it
// long on purpose. when we give up we fall back to the base currency, and the button
// would then charge the wrong currency and get rejected at checkout. better to
// wait than to charge wrong.
const BAIL_AFTER_MS = 6000;

// Rides WCPBC's AJAX geolocation mode: the `wc_price_based_country_ajax_geo_params`
// global plus the `wc_price_based_country_set_currency_params` body event. That
// contract has been stable since WCPBC 2.0 (2020), so instead of pinning a version
// we feature-detect the global here.
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
