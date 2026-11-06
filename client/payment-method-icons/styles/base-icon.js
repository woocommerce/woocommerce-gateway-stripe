import React from 'react';
import clsx from 'clsx';
import './style.scss';

/**
 * Renders a payment method icon at one of the fixed icon sizes.
 *
 * @param {Object} props
 * @param {string} props.src       The image URL for the icon.
 * @param {*}      props.children  Rendered instead of the image when `src` is not provided.
 * @param {string} props.alt       The alt text of the image.
 * @param {string} props.size      'small' (default) or 'medium'.
 * @param {string} props.className Extra class names for the wrapper.
 * @param {string} props.iconType  The type of icon to render.
 * @return {JSX.Element} The rendered icon.
 */
const BaseIcon = ( {
	src,
	children,
	alt,
	size = 'small',
	className,
	iconType,
	...restProps
} ) => (
	<span
		className={ clsx(
			'wc-stripe-payment-method-icon',
			{
				'wc-stripe-payment-method-icon--small': size === 'small',
				'wc-stripe-payment-method-icon--medium': size === 'medium',
				'wc-stripe-payment-method-icon--flush-y':
					iconType === 'flush-vertical',
				'wc-stripe-payment-method-icon--flush-outlined':
					iconType === 'flush-outlined',
				'wc-stripe-payment-method-icon--pad-y':
					iconType === 'pad-vertical',
			},
			className
		) }
		{ ...restProps }
	>
		{ src ? <img src={ src } alt={ alt } /> : children }
	</span>
);

export default BaseIcon;
