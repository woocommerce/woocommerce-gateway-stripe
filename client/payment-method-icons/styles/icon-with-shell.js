import React from 'react';
import clsx from 'clsx';
import BaseIcon from './base-icon';
import './style.scss';

/**
 * Icon with shell spacing for payment method icons.
 *
 * @param {Object} props           The props for the icon.
 * @param {string} props.src       The image URL for the icon.
 * @param {string} props.children  The children of the icon. Only used when src is not provided.
 * @param {string} props.alt       The alt text of the icon. Only used when src is provided.
 * @param {string} props.size      The size of the icon. Defaults to 'small', but can also be 'medium'.
 * @param {string} props.className The class name of the icon.
 * @return {JSX.Element}           The rendered icon with shell component.
 */
const IconWithShell = ( {
	src,
	children,
	alt,
	size = 'small',
	className,
	...restProps
} ) => {
	const classes = clsx(
		'wc-stripe-payment-method-icon__with-shell',
		className,
		{
			'wc-stripe-payment-method-icon__small': size === 'small',
			'wc-stripe-payment-method-icon__medium': size === 'medium',
		}
	);

	return (
		<BaseIcon
			className={ classes }
			size={ size }
			src={ src }
			alt={ alt }
			{ ...restProps }
		>
			{ children }
		</BaseIcon>
	);
};

export default IconWithShell;
