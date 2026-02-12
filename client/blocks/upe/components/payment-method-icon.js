import BaseIcon from '../../../payment-method-icons/styles/base-icon';
import { getBlocksConfiguration, getStripeImageUrl } from '../../utils';
import Icons from 'wcstripe/payment-method-icons';
import {
	PAYMENT_METHOD_AFTERPAY,
	PAYMENT_METHOD_AFTERPAY_CLEARPAY,
	PAYMENT_METHOD_CLEARPAY,
} from 'wcstripe/stripe-utils/constants';

const { accountCountry, isAdmin, isOCEnabled } = getBlocksConfiguration() || {};

/**
 * Creates an icon component that.
 *
 * @param {string} iconName The base name of the icon file without extension
 * @return {Function} A React component that renders the appropriate icon
 */
const createIconComponent = ( iconName ) => ( props ) => {
	const iconSrc = getStripeImageUrl( iconName );
	return <BaseIcon { ...props } src={ iconSrc } />;
};

/**
 * Initialize checkout icons for payment methods
 *
 * @return {Object|null} Object containing checkout icons or null if in admin
 */
const initializeCheckoutIcons = () => {
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
			au_becs_debit: createIconComponent( 'bank-debit' ),
			acss_debit: createIconComponent( 'bank-debit' ),
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

const checkoutIcons = initializeCheckoutIcons();

/**
 * Returns the icon for the UPE payment method.
 *
 * @param {string} paymentMethod The payment method name.
 * @return {JSX.Element|null} The icon element.
 */
export const PaymentMethodIcon = ( { paymentMethod } ) => {
	if ( isOCEnabled ) {
		return null;
	}

	let iconName = paymentMethod;

	// Afterpay/Clearpay have different icons for UK merchants.
	if ( paymentMethod === PAYMENT_METHOD_AFTERPAY_CLEARPAY ) {
		iconName =
			accountCountry === 'GB'
				? PAYMENT_METHOD_CLEARPAY
				: PAYMENT_METHOD_AFTERPAY;
	}

	// Use checkout icons if available, otherwise fallback to default Icons
	return ( checkoutIcons && checkoutIcons[ iconName ] ) || Icons[ iconName ];
};
