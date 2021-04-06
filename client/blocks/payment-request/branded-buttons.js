/**
 * External dependencies
 */
import { useState } from '@wordpress/element';

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

export const GooglePayButton = ( { onButtonClicked } ) => {
	const {
		theme = 'dark',
		locale = 'en',
		height = '44',
	} = getStripeServerData().button;

	const allowedTypes = [ 'short', 'long' ];
	// Use pre-blocks settings until we merge the two distinct settings objects.
	/* global wc_stripe_payment_request_params */
	const { branded_type } = wc_stripe_payment_request_params.button; // eslint-disable-line camelcase
	const type = allowedTypes.includes( branded_type ) ? branded_type : 'long'; // eslint-disable-line camelcase

	// If we're using the short button type (i.e. logo only) make sure we get the logo only SVG.
	const googlePlaySvg =
		type === 'long'
			? `https://www.gstatic.com/instantbuy/svg/${ theme }/${ locale }.svg`
			: `https://www.gstatic.com/instantbuy/svg/${ theme }_gpay.svg`;
	const [ backgroundUrl, setBackgroundUrl ] = useState( googlePlaySvg );

	// Let's make sure the localized Google Pay button exists, otherwise we fall back to the
	// english version. This test element is not used on purpose.
	const _testImage = ( // eslint-disable-line no-unused-vars
		<img
			src={ backgroundUrl }
			alt={ 'test' }
			onError={ () => {
				setBackgroundUrl(
					`https://www.gstatic.com/instantbuy/svg/${ theme }/en.svg`
				);
			} }
		/>
	);

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
