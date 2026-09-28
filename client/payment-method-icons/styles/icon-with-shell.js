import React from 'react';
import clsx from 'clsx';
import BaseIcon from './base-icon';

/**
 * A payment method icon inside a bordered, padded white "shell".
 *
 * @param {Object} props           Passed through to BaseIcon.
 * @param {string} props.className Extra class names for the wrapper.
 * @return {JSX.Element} The rendered icon.
 */
const IconWithShell = ( { className, ...restProps } ) => (
	<BaseIcon
		className={ clsx( 'wc-stripe-payment-method-icon--shell', className ) }
		{ ...restProps }
	/>
);

export default IconWithShell;
