import React from 'react';
import paymentMethodsMap from '../../payment-methods-map';
import './style.scss';
import { usePaymentMethodData } from 'wcstripe/utils/use-payment-method-data';

const PaymentMethodIcon = ( { name, showName } ) => {
	const paymentMethod = paymentMethodsMap[ name ];

	if ( ! paymentMethod ) {
		return <></>;
	}

	// eslint-disable-next-line react-hooks/rules-of-hooks
	const { label, Icon } = usePaymentMethodData( name );

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
