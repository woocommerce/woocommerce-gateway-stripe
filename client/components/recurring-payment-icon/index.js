import React from 'react';
import icon from './icon.svg';
import { __ } from '@wordpress/i18n';
import Tooltip from 'wcstripe/components/tooltip';
import './style.scss';

const RecurringPaymentIcon = () => {
	return (
		<Tooltip
			className="wc-stripe-recurring-payment-icon"
			content={ __(
				'Supports recurring payments',
				'woocommerce-gateway-stripe'
			) }
		>
			<img
				className="wc-stripe-recurring-payment-icon__icon"
				src={ icon }
				alt=""
			/>
		</Tooltip>
	);
};

export default RecurringPaymentIcon;
