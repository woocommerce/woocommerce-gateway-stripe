import React from 'react';
import paymentMethodsMap from '../../payment-methods-map';
import './style.scss';
import { useAccount } from 'wcstripe/data/account';

const PaymentMethodIcon = ( { name, showName } ) => {
	const paymentMethod = paymentMethodsMap[ name ];
	const { data } = useAccount();

	if ( ! paymentMethod ) {
		return <></>;
	}

	let { label, Icon } = paymentMethod;
	if ( data?.account?.country === 'GB' && name === 'afterpay_clearpay' ) {
		const { IconClearpay, labelClearpay } = paymentMethod;
		Icon = IconClearpay;
		label = labelClearpay;
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
