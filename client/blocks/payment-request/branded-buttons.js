import { useState, useEffect } from '@wordpress/element';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

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
	const {
		theme = 'dark',
		locale = 'en',
		height = '44',
	} = getBlocksConfiguration()?.button;

	const allowedTypes = [ 'short', 'long' ];
	const { branded_type } = getBlocksConfiguration()?.button; // eslint-disable-line camelcase
	const type = allowedTypes.includes( branded_type ) ? branded_type : 'long'; // eslint-disable-line camelcase

	// Allowed themes for Google Pay button image are 'dark' and 'light'.
	// We may include 'light-outline' as a theme, so we ensure only 'dark' or 'light' are possible
	// here.
	const gpayButtonTheme = theme === 'dark' ? 'dark' : 'light';

	// Let's make sure the localized Google Pay button exists, otherwise we fall back to the
	// english version. This test element is not used on purpose.
	const backgroundUrl = useLocalizedGoogleSvg(
		type,
		gpayButtonTheme,
		locale
	);

	return (
		<button
			type="button"
			id="wc-stripe-branded-button"
			aria-label="Google Pay"
			// 'light-outline' is a viable CSS class for the button, so we don't use the normalized
			// `gpayButtonTheme` as the class here.
			className={ `gpay-button ${ theme } ${ type }` }
			style={ {
				backgroundImage: `url(${ backgroundUrl })`,
				height: height + 'px',
			} }
			onClick={ onButtonClicked }
		/>
	);
};
