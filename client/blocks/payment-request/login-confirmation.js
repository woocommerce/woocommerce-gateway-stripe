import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

/**
 * Displays a `confirm` dialog which leads to a redirect.
 *
 * @param {string} paymentRequestType Can be either apple_pay, google_pay or payment_request_api.
 */
export const displayLoginConfirmation = ( paymentRequestType ) => {
	if ( ! getBlocksConfiguration()?.login_confirmation ) {
		return;
	}

	let message = getBlocksConfiguration()?.login_confirmation?.message;

	// Replace dialog text with specific payment request type "Apple Pay" or "Google Pay".
	if ( paymentRequestType !== 'payment_request_api' ) {
		message = message.replace(
			/\*\*.*?\*\*/,
			paymentRequestType === 'apple_pay' ? 'Apple Pay' : 'Google Pay'
		);
	}

	// Remove asterisks from string.
	message = message.replace( /\*\*/g, '' );

	// eslint-disable-next-line no-alert, no-undef
	if ( confirm( message ) ) {
		// Redirect to my account page.
		window.location.href = getBlocksConfiguration()?.login_confirmation?.redirect_url;
	}
};
