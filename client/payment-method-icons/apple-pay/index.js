import React from 'react';
import clsx from 'clsx';
import IconWithShell from '../styles/icon-with-shell';
import icon from './icon-black.svg';
import '../style.scss';

const ApplePayIcon = ( { className, ...props } ) => {
	return (
		<IconWithShell
			src={ icon }
			className={ clsx(
				'wc-stripe-payment-method-icon__apple-pay',
				className
			) }
			{ ...props }
		/>
	);
};

export default ApplePayIcon;
