import React from 'react';
import clsx from 'clsx';
import IconWithShell from '../styles/icon-with-shell';
import icon from './icon.svg';
import '../style.scss';

const CashAppIcon = ( { className, ...props } ) => {
	return (
		<IconWithShell
			src={ icon }
			className={ clsx(
				'wc-stripe-payment-method-icon__cashapp',
				className
			) }
			{ ...props }
		/>
	);
};

export default CashAppIcon;
