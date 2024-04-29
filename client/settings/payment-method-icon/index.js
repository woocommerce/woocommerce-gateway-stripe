import React from 'react';
import paymentMethodsMap from '../../payment-methods-map';
import './style.scss';

const PaymentMethodIcon = ( { name, showName } ) => {
	const paymentMethod = paymentMethodsMap[ name ];

	if ( ! paymentMethod ) {
		return <></>;
	}

	const { label, Icon } = paymentMethod;

	return (
		<span className="woocommerce-gateway-stripe__payment-method-icon">
			<Icon
				className="woocommerce-gateway-stripe__payment-method-icon__icon"
				alt={ label }
			/>
			{ showName && (
				<span className="woocommerce-gateway-stripe__payment-method-icon__label">
					{ label }
				</span>
			) }
		</span>
	);
};

export default PaymentMethodIcon;
