import React from 'react';
import clsx from 'clsx';
import './style.scss';

/**
 * Base icon component for payment method icons.
 *
 * @param {Object} props
 * @param {string} props.src       The image URL for the icon.
 * @param {string} props.children  The children of the icon. Only used when src is not provided.
 * @param {string} props.alt       The alt text of the icon. Only used when src is provided.
 * @param {string} props.size      The size of the icon. Defaults to 'small', but can also be 'medium'.
 * @param {string} props.className The class name of the icon.
 * @return {JSX.Element} The rendered icon component.
 */
const BaseIcon = ( {
	src,
	children,
	alt,
	size = 'small',
	className,
	...restProps
} ) => {
	const classes = clsx( 'wc-stripe-payment-method-icon', className, {
		'wc-stripe-payment-method-icon__small': size === 'small',
		'wc-stripe-payment-method-icon__medium': size === 'medium',
	} );

	return (
		<span className={ classes } { ...restProps }>
			{ src ? <img src={ src } alt={ alt } /> : children }
		</span>
	);
};

export default BaseIcon;
