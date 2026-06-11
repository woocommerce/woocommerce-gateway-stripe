import React from 'react';
import clsx from 'clsx';
import BaseIcon from '../styles/base-icon';
import icon from './icon.svg';
import '../style.scss';

const LinkIcon = ( { className, ...props } ) => {
	return (
		<BaseIcon
			src={ icon }
			className={ clsx(
				'wc-stripe-payment-method-icon__link',
				className
			) }
			{ ...props }
		/>
	);
};

export default LinkIcon;
