import React from 'react';
import './style.scss';
import { usePaymentMethodsData } from 'wcstripe/utils/use-payment-methods-data';

const PaymentMethodIcon = ( { name, showName } ) => {
	const paymentMethodsData = usePaymentMethodsData();
	const paymentMethod = paymentMethodsData[ name ];

	if ( ! paymentMethod ) {
		return <></>;
	}

	const { Icon, label } = paymentMethod;

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
