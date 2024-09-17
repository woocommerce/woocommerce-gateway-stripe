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
export const getExpressCheckoutData = ( key ) => {
	if (
		// eslint-disable-next-line camelcase
		! wc_stripe_express_checkout_params ||
		! wc_stripe_express_checkout_params[ key ]
	) {
		return null;
	}
	return wc_stripe_express_checkout_params[ key ];
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
	const buttonSettings = getExpressCheckoutData( 'button' );
	let borderRadiusPx = getDefaultBorderRadius();
	if ( buttonSettings.radius ) {
		borderRadiusPx = buttonSettings.radius;
	}

	return {
		variables: {
			borderRadius: `${ borderRadiusPx }px`,
			spacingUnit: '6px',
		},
	};
};

/**
 * Returns the style settings for the Express Checkout buttons.
 */
export const getExpressCheckoutButtonStyleSettings = () => {
	const buttonSettings = getExpressCheckoutData( 'button' );

	const mapWooPaymentsThemeToButtonTheme = ( buttonType, theme ) => {
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

	let googlePayType;
	let applePayType;
	if ( buttonSettings.type === 'default' ) {
		googlePayType = 'plain';
		applePayType = 'plain';
	} else if ( buttonSettings.type ) {
		googlePayType = buttonSettings.type;
		applePayType = buttonSettings.type;
	} else {
		googlePayType = 'buy';
		applePayType = 'buy';
	}

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
			googlePay: mapWooPaymentsThemeToButtonTheme(
				'googlePay',
				buttonSettings.theme ? buttonSettings.theme : 'black'
			),
			applePay: mapWooPaymentsThemeToButtonTheme(
				'applePay',
				buttonSettings.theme ? buttonSettings.theme : 'black'
			),
		},
		buttonType: {
			googlePay: googlePayType,
			applePay: applePayType,
		},
		// Allowed height must be 40px to 55px.
		buttonHeight: Math.min(
			Math.max(
				parseInt(
					buttonSettings.height ? buttonSettings.height : '48',
					10
				),
				40
			),
			55
		),
	};
};
