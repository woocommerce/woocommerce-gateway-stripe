import { recordEvent } from 'wcstripe/tracking';

/**
 * Tracks the toggle state of the Optimized Checkout feature.
 *
 * @param {boolean} isEnabled The current state of the Optimized Checkout feature.
 * @param {string} source The source of the toggle action, e.g., 'settings-tab-checkbox'.
 */
export const trackOCToggle = ( isEnabled, source ) => {
	const eventName = isEnabled
		? 'wcstripe_optimized_checkout_enabled'
		: 'wcstripe_optimized_checkout_disabled';
	recordEvent( eventName, {
		source,
	} );
};
