import React from 'react';
import classNames from 'classnames';
import './style.scss';

const Pill = ( { children, ...props } ) => {
	const { className, ...restProps } = props;
	return (
		<span
			className={ classNames( 'wc-stripe-pill', className ) }
			{ ...restProps }
		>
			{ children }
		</span>
	);
};

export default Pill;
