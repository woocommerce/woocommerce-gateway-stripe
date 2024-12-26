/* global wc_stripe_express_checkout_params */
import jQuery from 'jquery';
import { isLinkEnabled, getPaymentMethodTypes } from 'wcstripe/stripe-utils';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { EXPRESS_CHECKOUT_NOTICE_DELAY } from 'wcstripe/data/constants';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_LINK,
} from 'wcstripe/stripe-utils/constants';

export * from './normalize';

/**
 * Get error messages from WooCommerce notice.
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
			link: 'auto',
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

export const getRequiredFieldDataFromCheckoutForm = ( data ) => {
	return getExpressCheckoutData( 'has_block' )
		? getRequiredFieldDataFromBlockCheckoutForm( data )
		: getRequiredFieldDataFromShortcodeCheckoutForm( data );
};

const getRequiredFieldDataFromBlockCheckoutForm = ( data ) => {
	const checkoutForm = document.querySelector( '.wc-block-checkout' );
	// Return if cart page.
	if ( ! checkoutForm ) {
		return data;
	}

	const requiredFields = checkoutForm.querySelectorAll( '[required]' );

	if ( requiredFields.length ) {
		requiredFields.forEach( ( field ) => {
			const value = field.value;
			const id = field.id?.replace( '-', '_' );
			if ( value && ! data[ id ] ) {
				data[ id ] = value;
			}

			// if billing same as shipping is selected, copy the shipping field to billing field.
			const useSameBillingAddress = checkoutForm
				.querySelector( '.wc-block-checkout__use-address-for-billing' )
				?.querySelector( 'input' )?.checked;
			if ( useSameBillingAddress ) {
				const billingFieldName = id.replace( 'shipping_', 'billing_' );
				if ( ! data[ billingFieldName ] && data[ id ] ) {
					data[ billingFieldName ] = data[ id ];
				}
			}
		} );
	}

	return data;
};

const getRequiredFieldDataFromShortcodeCheckoutForm = ( data ) => {
	const checkoutForm = document.querySelector( 'form.checkout' );
	// Return if cart page.
	if ( ! checkoutForm ) {
		return data;
	}

	const requiredfields = checkoutForm.querySelectorAll(
		'.validate-required'
	);

	if ( requiredfields.length ) {
		requiredfields.forEach( ( element ) => {
			const field = element.querySelector( 'input' );
			if ( ! field ) {
				return;
			}

			const name = field.name;

			let value = '';
			if ( field.getAttribute( 'type' ) === 'checkbox' ) {
				value = field.checked;
			} else {
				value = field.value;
			}

			if ( value && name ) {
				if ( ! data[ name ] ) {
					data[ name ] = value;
				}

				// if shipping same as billing is selected, copy the billing field to shipping field.
				const shipToDiffAddressField = document.getElementById(
					'ship-to-different-address'
				);
				const shipToDiffAddress =
					shipToDiffAddressField &&
					shipToDiffAddressField.querySelector( 'input' ).checked;
				if ( ! shipToDiffAddress ) {
					const shippingFieldName = name.replace(
						'billing_',
						'shipping_'
					);
					if ( ! data[ shippingFieldName ] && data[ name ] ) {
						data[ shippingFieldName ] = data[ name ];
					}
				}
			}
		} );
	}

	return data;
};

/**
 * Get array of payment method types to use with intent. Filtering out the method types not part of Express Checkout.
 *
 * @see https://docs.stripe.com/elements/express-checkout-element/accept-a-payment#enable-payment-methods - lists the method types
 * supported and which ones are required by each Express Checkout method.
 *
 * @param {string} paymentMethodType Payment method type Stripe ID.
 * @return {Array} Array of payment method types to use with intent, for Express Checkout.
 */
export const getExpressPaymentMethodTypes = ( paymentMethodType = null ) => {
	const expressPaymentMethodTypes = getPaymentMethodTypes(
		paymentMethodType
	).filter( ( type ) =>
		[ 'paypal', 'amazon_pay', PAYMENT_METHOD_CARD ].includes( type )
	);

	if ( isLinkEnabled() ) {
		expressPaymentMethodTypes.push( PAYMENT_METHOD_LINK );
	}

	return expressPaymentMethodTypes;
};

/**
 * Fetches the payment method types required to process a payment for an Express method.
 *
 * @see https://docs.stripe.com/elements/express-checkout-element/accept-a-payment#enable-payment-methods - lists the method types
 * supported and which ones are required by each Express Checkout method.
 *
 * @param {*} paymentMethodType The express payment method type. eg 'link', 'googlePay', or 'applePay'.
 * @return {Array} Array of payment method types necessary to process a payment for an Express method.
 */
export const getPaymentMethodTypesForExpressMethod = ( paymentMethodType ) => {
	const paymentMethodsConfig = getBlocksConfiguration()?.paymentMethodsConfig;
	const paymentMethodTypes = [];

	if ( ! paymentMethodsConfig ) {
		return paymentMethodTypes;
	}

	// All express payment methods require 'card' payments. Add it if it's enabled.
	if ( paymentMethodsConfig?.card !== undefined ) {
		paymentMethodTypes.push( PAYMENT_METHOD_CARD );
	}

	// Add 'link' payment method type if enabled and requested.
	if (
		paymentMethodType === PAYMENT_METHOD_LINK &&
		isLinkEnabled( paymentMethodsConfig )
	) {
		paymentMethodTypes.push( PAYMENT_METHOD_LINK );
	}

	return paymentMethodTypes;
};

/**
 * Display a notice on the checkout page (for Express Checkout Element).
 *
 * @param {string} message The message to display.
 * @param {string} type The type of notice.
 * @param {Array} additionalClasses Additional classes to add to the notice.
 */
export const displayExpressCheckoutNotice = (
	message,
	type,
	additionalClasses
) => {
	const isBlockCheckout = getExpressCheckoutData( 'has_block' );
	const mainNoticeClass = `woocommerce-${ type }`;
	let classNames = [ mainNoticeClass ];
	if ( additionalClasses ) {
		classNames = classNames.concat( additionalClasses );
	}

	// Remove any existing notices.
	jQuery( '.' + classNames.join( '.' ) ).remove();

	const containerClass = isBlockCheckout
		? 'wc-block-components-main'
		: 'woocommerce-notices-wrapper';
	const $container = jQuery( '.' + containerClass ).first();

	if ( $container.length ) {
		const note = jQuery(
			`<div class="${ classNames.join( ' ' ) }" role="note" />`
		).text( message );
		if ( isBlockCheckout ) {
			$container.prepend( note );
		} else {
			$container.append( note );
		}

		// Scroll to notices.
		jQuery( 'html, body' ).animate(
			{
				scrollTop: $container.find( `.${ mainNoticeClass }` ).offset()
					.top,
			},
			600
		);
	}
};

/**
 * Delay for a short period of time before proceeding with the checkout process.
 *
 * @return {Promise<void>} A promise that resolves after the delay.
 */
export const expressCheckoutNoticeDelay = async () => {
	await new Promise( ( resolve ) =>
		setTimeout( resolve, EXPRESS_CHECKOUT_NOTICE_DELAY )
	);
};
