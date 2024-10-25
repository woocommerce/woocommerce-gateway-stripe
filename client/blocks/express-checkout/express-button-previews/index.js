import classNames from 'classnames';
import googlePayIcon from '../../../payment-method-icons/google-pay/icon-white.svg';
import applePayIcon from '../../../payment-method-icons/apple-pay/icon-white.svg';
import stripeLinkIcon from '../../../payment-method-icons/link/icon-black.svg';
import './style.scss';

/**
 * Base PaymentButtonPreview Component
 *
 * @param {Object} props
 * @param {string} props.icon - The icon to display.
 * @param {string} [props.className] - Optional additional class names.
 * @return {JSX.Element} The rendered component.
 */
const PaymentButtonPreview = ( { icon, className } ) => (
	<div
		className={ classNames(
			'wc-stripe-payment-button-preview',
			className
		) }
	>
		<img src={ icon } alt="Payment Method Icon" />
	</div>
);

/**
 * GooglePayPreview Component
 *
 * @return {JSX.Element} The rendered component.
 */
export const GooglePayPreview = () => (
	<PaymentButtonPreview
		icon={ googlePayIcon }
		className="wc-stripe-google-pay-preview"
	/>
);

/**
 * ApplePayPreview Component
 *
 * @return {JSX.Element} The rendered component.
 */
export const ApplePayPreview = () => (
	<PaymentButtonPreview
		icon={ applePayIcon }
		className="wc-stripe-apple-pay-preview"
	/>
);

/**
 * StripeLinkPreview Component
 *
 * @return {JSX.Element} The rendered component.
 */
export const StripeLinkPreview = () => (
	<PaymentButtonPreview
		icon={ stripeLinkIcon }
		className="wc-stripe-link-preview"
	/>
);
