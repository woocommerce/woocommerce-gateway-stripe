import React from 'react';
import clsx from 'clsx';
import './style.scss';

const UnstyledLink = ( {
	children,
	target = '_blank',
	rel = 'noreferrer',
	className,
	href,
	...props
} ) => {
	return (
		<a
			className={ clsx( 'wc-stripe-unstyled-link', className ) }
			target={ target }
			rel={ rel }
			href={ href }
			{ ...props }
		>
			{ children }
		</a>
	);
};

export default UnstyledLink;
