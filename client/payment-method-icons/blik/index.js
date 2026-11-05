import React from 'react';
import clsx from 'clsx';
import IconWithShell from '../styles/icon-with-shell';
import icon from './icon.svg';

const BlikIcon = ( { className, ...props } ) => (
	<IconWithShell
		{ ...props }
		className={ clsx( 'wc-stripe-payment-method-icon--blik', className ) }
		src={ icon }
	/>
);

export default BlikIcon;
