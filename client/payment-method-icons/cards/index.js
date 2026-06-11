import React from 'react';
import clsx from 'clsx';
import BaseIcon from '../styles/base-icon';
import icon from './icon.svg';
import '../style.scss';

const CardsIcon = ( { className, ...props } ) => {
	return (
		<BaseIcon
			src={ icon }
			className={ clsx(
				'wc-stripe-payment-method-icon__cards',
				className
			) }
			{ ...props }
		/>
	);
};

export default CardsIcon;
