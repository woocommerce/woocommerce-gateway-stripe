import BaseIcon from '../../payment-method-icons/styles/base-icon';
import { getStripeImageUrl } from '../utils';

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
			ideal: createIconComponent( 'ideal-wero' ),
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
