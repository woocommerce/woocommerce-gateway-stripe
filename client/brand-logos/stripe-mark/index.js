import { __ } from '@wordpress/i18n';
import classNames from 'classnames';
import mark from './mark.svg';

/**
 * StripeMark component.
 *
 * @param {Object} props             The component props.
 * @param {string} [props.className] Additional classes to add to the StripeMark component.
 * @param {any} props.restProps      Additional props to add to the StripeMark component.
 *
 * @return {JSX.Element} The rendered StripeMark component.
 */
const StripeMark = ( { className, ...restProps } ) => (
	<img
		className={ classNames( 'woocommerce-stripe-mark-logo', className ) }
		src={ mark }
		width="64"
		height="64"
		alt={ __( 'Stripe logo', 'woocommerce-gateway-stripe' ) }
		{ ...restProps }
	/>
);

export default StripeMark;
