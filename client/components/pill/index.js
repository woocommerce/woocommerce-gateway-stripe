import React from 'react';
import clsx from 'clsx';
import './style.scss';

const Pill = ( { children, ...props } ) => {
	const { className, ...restProps } = props;
	return (
		<span
			className={ clsx( 'wc-stripe-pill', className ) }
			{ ...restProps }
		>
			{ children }
		</span>
	);
};

export default Pill;
