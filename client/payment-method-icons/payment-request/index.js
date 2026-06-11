import React from 'react';
import clsx from 'clsx';
import BaseIcon from '../styles/base-icon';
import icon from './icon.svg';
import '../style.scss';

const PaymentRequestIcon = ( { className, ...props } ) => {
	return (
		<BaseIcon
			src={ icon }
			className={ clsx(
				'wc-stripe-payment-method-icon__payment-request',
				className
			) }
			{ ...props }
		/>
	);
};

export default PaymentRequestIcon;
