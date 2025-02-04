import { useEffect, useState } from 'react';
import tinycolor from 'tinycolor2';
import BaseIcon from '../../payment-method-icons/styles/base-icon';
import { getStripeImageUrl } from '../utils';
import { getBackgroundColor } from '../../styles/upe/utils';
import { callWhenElementIsAvailable } from './call-when-element-is-available';

/**
 * List of payment method icons that have dark variants
 */
const PAYMENT_METHODS_WITH_DARK_ICONS = [ 'affirm', 'afterpay' ];

/**
 * List of selectors to check for background color
 */
const BACKGROUND_SELECTORS = [
	'#payment-method .wc-block-components-radio-control-accordion-option',
	'#payment-method',
	'form.wc-block-checkout__form',
	'.wc-block-checkout',
	'body',
];

/**
 * Determines whether background color is light or dark.
 *
 * @param {string} color CSS color value.
 * @return {boolean} True, if background is light; false, if background is dark.
 */
const isColorDark = ( color ) => {
	return tinycolor( color ).isDark();
};

/**
 * Creates an icon component that switches between light/dark variants based on background
 *
 * @param {string} iconName The base name of the icon file without extension
 * @return {Function} A React component that renders the appropriate icon
 */
const createIconComponent = ( iconName ) => ( props ) => {
	const [ isDark, setIsDark ] = useState( false );
	const hasDarkVariant = PAYMENT_METHODS_WITH_DARK_ICONS.includes( iconName );

	useEffect( () => {
		const checkBackground = () => {
			const bg = getBackgroundColor( BACKGROUND_SELECTORS );
			setIsDark( isColorDark( bg ) );
		};

		// Wait for the payment method container to be available
		callWhenElementIsAvailable( '#payment-method', checkBackground );
	}, [] );

	// Use dark variant only if it exists and background is dark
	const shouldUseDarkVariant = isDark && hasDarkVariant;
	const iconSrc = shouldUseDarkVariant
		? getStripeImageUrl( `${ iconName }-dark` )
		: getStripeImageUrl( iconName );

	return <BaseIcon { ...props } size="medium" src={ iconSrc } />;
};

/**
 * Initialize checkout icons for payment methods
 *
 * @param {boolean} isAdmin Whether we're in the admin context
 * @return {Object|null} Object containing checkout icons or null if in admin
 */
export const initializeCheckoutIcons = ( isAdmin ) => {
	if ( ! isAdmin ) {
		// Only use checkout icons for frontend
		const checkoutIcons = {
			card: createIconComponent( 'cards' ),
			visa: createIconComponent( 'visa' ),
			mastercard: createIconComponent( 'mastercard' ),
			amex: createIconComponent( 'amex' ),
			discover: createIconComponent( 'discover' ),
			jcb: createIconComponent( 'jcb' ),
			diners: createIconComponent( 'diners' ),
			alipay: createIconComponent( 'alipay' ),
			bancontact: createIconComponent( 'bancontact' ),
			ideal: createIconComponent( 'ideal' ),
			p24: createIconComponent( 'p24' ),
			giropay: createIconComponent( 'giropay' ),
			eps: createIconComponent( 'eps' ),
			multibanco: createIconComponent( 'multibanco' ),
			sofort: createIconComponent( 'sofort' ),
			sepa: createIconComponent( 'sepa' ),
			boleto: createIconComponent( 'boleto' ),
			oxxo: createIconComponent( 'oxxo' ),
			wechat_pay: createIconComponent( 'wechat' ),
			afterpay: createIconComponent( 'afterpay' ),
			clearpay: createIconComponent( 'clearpay' ),
			klarna: createIconComponent( 'klarna' ),
			affirm: createIconComponent( 'affirm' ),
			cashapp: createIconComponent( 'cashapp' ),
		};

		// Replace the icons in the payment methods map
		wp.hooks.addFilter(
			'woocommerce_stripe_payment_method_icons',
			'wc-stripe',
			() => checkoutIcons
		);

		return checkoutIcons;
	}
	return null;
};
