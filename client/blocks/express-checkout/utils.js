import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

/**
 * Get error messages from WooCommerce notice from server response.
 *
 * @param {string} notice
 */
export const getErrorMessageFromNotice = ( notice ) => {
	const div = document.createElement( 'div' );
	div.innerHTML = notice.trim();
	return div.firstChild ? div.firstChild.textContent : '';
};

/**
 * Displays a `confirm` dialog which leads to a redirect.
 *
 * @param {string} expressPaymentType Can be either apple_pay, google_pay or payment_request_api.
 */
export const displayLoginConfirmation = ( expressPaymentType ) => {
	const loginConfirmation = getBlocksConfiguration()?.login_confirmation;

	if ( ! loginConfirmation ) {
		return;
	}

	let message = loginConfirmation.message;

	const paymentTypesMap = {
		apple_pay: 'Apple Pay',
		google_pay: 'Google Pay',
		amazon_pay: 'Amazon Pay',
		paypal: 'PayPal',
		link: 'Link',
	};

	// Replace dialog text with specific express checkout type.
	message = message.replace(
		/\*\*.*?\*\*/,
		paymentTypesMap[ expressPaymentType ]
	);

	// Remove asterisks from string.
	message = message.replace( /\*\*/g, '' );

	// eslint-disable-next-line no-alert, no-undef
	if ( confirm( message ) ) {
		// Redirect to my account page.
		window.location.href = loginConfirmation.redirect_url;
	}
};

/**
 * Returns the appearance settings for the Express Checkout buttons.
 * Currently only configures border radius for the buttons.
 */
export const getExpressCheckoutButtonAppearance = () => {
	const buttonSettings = getBlocksConfiguration().button;

	return {
		variables: {
			borderRadius: `${ buttonSettings?.radius ?? '4' }px`,
			spacingUnit: '6px',
		},
	};
};

/**
 * Returns the style settings for the Express Checkout buttons.
 */
export const getExpressCheckoutButtonStyleSettings = () => {
	const buttonSettings = getBlocksConfiguration().button;

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

	const googlePayType =
		buttonSettings?.type === 'default'
			? 'plain'
			: buttonSettings?.type ?? 'buy';

	const applePayType =
		buttonSettings?.type === 'default'
			? 'plain'
			: buttonSettings?.type ?? 'plain';

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
				buttonSettings?.theme ?? 'black'
			),
			applePay: mapWooPaymentsThemeToButtonTheme(
				'applePay',
				buttonSettings?.theme ?? 'black'
			),
		},
		buttonType: {
			googlePay: googlePayType,
			applePay: applePayType,
		},
		// Allowed height must be 40px to 55px.
		buttonHeight: Math.min(
			Math.max( parseInt( buttonSettings?.height ?? '48', 10 ), 40 ),
			55
		),
	};
};
