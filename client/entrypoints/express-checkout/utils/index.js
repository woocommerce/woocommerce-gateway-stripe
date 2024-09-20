/* global wc_stripe_express_checkout_params */

/**
 * Internal dependencies
 */
export * from './normalize';

/**
 * Get error messages from WooCommerce notice from server response.
 *
 * @param {string} notice Error notice.
 * @return {string} Error messages.
 */
export const getErrorMessageFromNotice = ( notice ) => {
	const div = document.createElement( 'div' );
	div.innerHTML = notice.trim();
	return div.firstChild ? div.firstChild.textContent : '';
};

/**
 * Retrieves express checkout data from global variable.
 *
 * @param {string} key The object property key.
 * @return {*|null} Value of the object prop or null.
 */
export const getExpressCheckoutData = ( key ) =>
	// eslint-disable-next-line camelcase
	wc_stripe_express_checkout_params[ key ] ?? null;

/**
 * Construct Express Checkout AJAX endpoint URL.
 *
 * @param {string} endpoint Request endpoint URL.
 * @param {string} prefix Endpoint URI prefix (default: 'wc_stripe_').
 * @return {string} URL with interpolated endpoint.
 */
export const getExpressCheckoutAjaxURL = (
	endpoint,
	prefix = 'wc_stripe_'
) => {
	return getExpressCheckoutData( 'ajax_url' )
		?.toString()
		?.replace( '%%endpoint%%', prefix + endpoint );
};

/**
 * Displays a `confirm` dialog which leads to a redirect.
 *
 * @param {string} expressPaymentType Can be either 'apple_pay', 'google_pay', 'amazon_pay', 'paypal' or 'link'.
 */
export const displayLoginConfirmation = ( expressPaymentType ) => {
	const loginConfirmation = getExpressCheckoutData( 'login_confirmation' );

	if ( ! loginConfirmation ) {
		return;
	}

	const paymentTypesMap = {
		apple_pay: 'Apple Pay',
		google_pay: 'Google Pay',
		amazon_pay: 'Amazon Pay',
		paypal: 'PayPal',
		link: 'Link',
	};
	let message = loginConfirmation.message;

	// Replace dialog text with specific express checkout type.
	message = message.replace(
		/\*\*.*?\*\*/,
		paymentTypesMap[ expressPaymentType ]
	);

	// Remove asterisks from string.
	message = message.replace( /\*\*/g, '' );

	// eslint-disable-next-line no-alert
	if ( window.confirm( message ) ) {
		// Redirect to my account page.
		window.location.href = loginConfirmation.redirect_url;
	}
};

export const getDefaultBorderRadius = () => {
	return 4;
};

/**
 * Returns the appearance settings for the Express Checkout buttons.
 * Currently only configures border radius for the buttons.
 */
export const getExpressCheckoutButtonAppearance = () => {
	return {
		variables: {
			borderRadius: `${
				getExpressCheckoutData( 'button' )?.radius ||
				getDefaultBorderRadius()
			}px`,
			spacingUnit: '6px',
		},
	};
};

/**
 * Returns the style settings for the Express Checkout buttons.
 */
export const getExpressCheckoutButtonStyleSettings = () => {
	const buttonSettings = getExpressCheckoutData( 'button' );

	// Maps the WC Stripe theme from settings to the button theme.
	const mapButtonSettingToStripeButtonTheme = ( buttonType, theme ) => {
		switch ( theme ) {
			case 'dark':
				return 'black';
			case 'light':
				return 'white';
			case 'light-outline':
				if ( buttonType === 'googlePay' ) {
					return 'white';
				}

				return 'white-outline';
			default:
				return 'black';
		}
	};

	const buttonMethodType =
		buttonSettings?.type === 'default'
			? 'plain'
			: buttonSettings?.type ?? 'buy';

	return {
		paymentMethods: {
			applePay: 'always',
			googlePay: 'always',
			link: 'never',
			paypal: 'never',
			amazonPay: 'never',
		},
		layout: { overflow: 'never' },
		buttonTheme: {
			googlePay: mapButtonSettingToStripeButtonTheme(
				'googlePay',
				buttonSettings?.theme ?? 'black'
			),
			applePay: mapButtonSettingToStripeButtonTheme(
				'applePay',
				buttonSettings?.theme ?? 'black'
			),
		},
		buttonType: {
			googlePay: buttonMethodType,
			applePay: buttonMethodType,
		},
		// Allowed height must be 40px to 55px.
		buttonHeight: Math.min(
			Math.max( parseInt( buttonSettings?.height ?? '48', 10 ), 40 ),
			55
		),
	};
};
