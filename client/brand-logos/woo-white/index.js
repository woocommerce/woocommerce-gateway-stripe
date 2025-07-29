import { _x } from '@wordpress/i18n';
import classNames from 'classnames';
import logo from './logo.svg';

/**
 * WooLogo component.
 *
 * @param {Object} props             The component props.
 * @param {string} [props.className] Additional classes to add to the WooLogo component.
 * @param {any} props.restProps      Additional props to add to the WooLogo component.
 *
 * @return {JSX.Element} The rendered WooLogo component.
 */
const WooLogo = ( { className, ...restProps } ) => (
	<img
		className={ classNames(
			'woocommerce-stripe-woo-white-logo',
			className
		) }
		src={ logo }
		width="64"
		height="64"
		alt={ _x( 'Woo logo', '[shopper]', 'woocommerce-gateway-stripe' ) }
		{ ...restProps }
	/>
);

export default WooLogo;
