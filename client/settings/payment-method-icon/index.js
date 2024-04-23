import React from 'react';
import paymentMethodsMap from '../../payment-methods-map';
import './style.scss';
import usePaymentMethodData from 'wcstripe/utils/use-payment-method-data';

const PaymentMethodIcon = ( { name, showName } ) => {
	const { label, Icon } = usePaymentMethodData();

	const paymentMethod = paymentMethodsMap[ name ];

	if ( ! paymentMethod ) {
		return <></>;
	}

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
