import React from 'react';
import clsx from 'clsx';
import './style.scss';

const Pill = ( { children, className, isFlex = false, ...restProps } ) => (
	<span
		className={ clsx( 'wc-stripe-pill', className, {
			'wc-stripe-pill--flex': isFlex,
		} ) }
		{ ...restProps }
	>
		{ children }
	</span>
);

export default Pill;
