import React from 'react';
import clsx from 'clsx';
import { Icon, info } from '@wordpress/icons';
import Pill from 'wcstripe/components/pill';
import Popover from 'wcstripe/components/popover';
import './style.scss';

const IconComponent = ( { children, className, ...props } ) => (
	<span
		className={ clsx(
			'wc-stripe-payment-method-unavailable-pill__icon-wrapper',
			className
		) }
		{ ...props }
	>
		<Icon
			icon={ info }
			size="16"
			className="wc-stripe-payment-method-unavailable-pill__icon"
		/>
		{ children }
	</span>
);

const PaymentMethodUnavailablePill = ( { title, children } ) => {
	return (
		<Pill className="wc-stripe-payment-method-unavailable-pill">
			{ title }
			<Popover BaseComponent={ IconComponent } content={ children } />
		</Pill>
	);
};

const PaymentMethodPopoverLink = ( {
	children,
	target = '_blank',
	rel = 'noreferrer',
	onClick,
	className,
	href,
	...props
} ) => {
	const combinedOnClick = ( ev ) => {
		ev.stopPropagation();
		onClick?.( ev );
	};

	return (
		<a
			className={ clsx(
				'wc-stripe-payment-method-unavailable-pill__popover-link',
				className
			) }
			target={ target }
			href={ href }
			rel={ rel }
			onClick={ combinedOnClick }
			{ ...props }
		>
			{ children }
		</a>
	);
};

export { PaymentMethodPopoverLink };

export default PaymentMethodUnavailablePill;
