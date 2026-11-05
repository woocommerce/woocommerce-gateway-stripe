import React from 'react';
import clsx from 'clsx';
import IconWithShell from '../styles/icon-with-shell';
import icon from './icon.svg';

const SepaIcon = ( { className, ...props } ) => (
	<IconWithShell
		{ ...props }
		className={ clsx( 'wc-stripe-payment-method-icon--sepa', className ) }
		src={ icon }
	/>
);

export default SepaIcon;
