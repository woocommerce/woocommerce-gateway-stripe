import React from 'react';
import { getExpressCheckoutLocationDefinitions } from './locations';
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * The "Show express checkouts on" location checkboxes shared by every Customize express checkouts
 * tab. The checkboxes are disabled until the method is enabled, and the value is owned by the
 * caller's settings store so this component stays presentational.
 *
 * @param {Object}   props
 * @param {boolean}  props.methodEnabled             Whether the method is enabled; gates the checkboxes.
 * @param {string[]} props.locations                 Currently enabled location keys.
 * @param {Function} props.onChange                  Receives the next array of enabled location keys.
 * @param {boolean}  [props.showChangePaymentMethod] Show the subscriptions-only location (Express/Link).
 */
const ExpressCheckoutLocationsControl = ( {
	methodEnabled,
	locations,
	onChange,
	showChangePaymentMethod = false,
} ) => {
	const definitions = getExpressCheckoutLocationDefinitions().filter(
		( location ) => ! location.subscriptionsOnly || showChangePaymentMethod
	);

	const handleChange = ( key ) => ( isChecked ) => {
		if ( isChecked ) {
			// Guard against duplicates so a re-checked box can't add the same key twice.
			onChange(
				locations.includes( key ) ? locations : [ ...locations, key ]
			);
		} else {
			onChange( locations.filter( ( name ) => name !== key ) );
		}
	};

	return (
		<>
			<h4>
				{ __(
					'Show express checkouts on',
					'woocommerce-gateway-stripe'
				) }
			</h4>
			<ul className="payment-request-settings__location">
				{ definitions.map( ( location ) => (
					<li key={ location.key }>
						<CheckboxControl
							disabled={ ! methodEnabled }
							checked={
								methodEnabled &&
								locations.includes( location.key )
							}
							onChange={ handleChange( location.key ) }
							label={ location.label }
						/>
					</li>
				) ) }
			</ul>
		</>
	);
};

export default ExpressCheckoutLocationsControl;
