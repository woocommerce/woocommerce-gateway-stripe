/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getStripeServerData } from '../stripe-utils';

export const shouldUseGooglePayBrand = () => {
	const ua = window.navigator.userAgent.toLowerCase();
	const isChrome =
		/chrome/.test( ua ) &&
		! /edge|edg|opr|brave\//.test( ua ) &&
		window.navigator.vendor === 'Google Inc.';
	// newer versions of Brave do not have the userAgent string
	const isBrave = isChrome && window.navigator.brave;
	return isChrome && ! isBrave;
};

const useLocalizedGoogleSvg = ( type, theme, locale ) => {
	// If we're using the short button type (i.e. logo only) make sure we get the logo only SVG.
	const googlePlaySvg =
		type === 'long'
			? `https://www.gstatic.com/instantbuy/svg/${ theme }/${ locale }.svg`
			: `https://www.gstatic.com/instantbuy/svg/${ theme }_gpay.svg`;

	const [ url, setUrl ] = useState( googlePlaySvg );

	useEffect( () => {
		const im = document.createElement( 'img' );
		im.addEventListener( 'error', () => {
			setUrl(
				`https://www.gstatic.com/instantbuy/svg/${ theme }/en.svg`
			);
		} );
		im.src = url;
	}, [ url, theme ] );

	return url;
};

export const GooglePayButton = ( { onButtonClicked } ) => {
	const { locale = 'en', height = '44' } = getStripeServerData().button;

	const allowedTypes = [ 'short', 'long' ];
	// Use pre-blocks settings until we merge the two distinct settings objects.
	/* global wc_stripe_payment_request_params */
	const { branded_type } = wc_stripe_payment_request_params.button; // eslint-disable-line camelcase
	const type = allowedTypes.includes( branded_type ) ? branded_type : 'long'; // eslint-disable-line camelcase

	// Allowed themes for Google Pay button image are 'dark' and 'light'.
	// We may include 'light-outline' as a theme, so we ensure only 'dark' or 'light' are possible
	// here.
	const theme =
		getStripeServerData()?.button?.theme === 'dark' ? 'dark' : 'light';

	// Let's make sure the localized Google Pay button exists, otherwise we fall back to the
	// english version. This test element is not used on purpose.
	const backgroundUrl = useLocalizedGoogleSvg( type, theme, locale );

	return (
		<button
			type={ 'button' }
			id={ 'wc-stripe-branded-button' }
			aria-label={ 'Google Pay' }
			className={ `gpay-button ${ theme } ${ type }` }
			style={ {
				backgroundImage: `url(${ backgroundUrl })`,
				height: height + 'px',
			} }
			onClick={ onButtonClicked }
		></button>
	);
};
