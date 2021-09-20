import React from 'react';
import classNames from 'classnames';
import paymentMethodsMap from '../../payment-methods-map';
import './style.scss';

const PaymentMethodIcon = ( { name, showName } ) => {
	const paymentMethod = paymentMethodsMap[ name ];

	if ( ! paymentMethod ) {
		return <></>;
	}

	const { label, Icon } = paymentMethod;

	return (
		<span
			className={ classNames(
				'woocommerce-gateway-stripe__payment-method-icon',
				{ 'has-icon-border': name !== 'card' }
			) }
		>
			<Icon className="woocommerce-gateway-stripe__payment-method-icon__icon" />
			{ showName && (
				<span className="woocommerce-gateway-stripe__payment-method-icon__label">
					{ label }
				</span>
			) }
		</span>
	);
};

export default PaymentMethodIcon;
