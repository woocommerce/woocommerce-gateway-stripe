/** @format */
/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { CheckboxControl, Icon, VisuallyHidden } from '@wordpress/components';
import { useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PaymentMethodIcon from '../../settings/payment-method-icon';
import Pill from '../pill';
import Tooltip from '../tooltip';
import paymentMethodsMap from '../../payment-methods-map';
// import './payment-method-checkbox.scss';
import './style.scss';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';

const PaymentMethodDescription = ( { name } ) => {
	const description = paymentMethodsMap[ name ]?.description;
	if ( ! description ) return null;

	return (
		<Tooltip content={ description }>
			<div className="payment-method-checkbox__info">
				<VisuallyHidden>
					{ __(
						'Information about the payment method, click to expand',
						'woocommerce-gateway-stripe'
					) }
				</VisuallyHidden>
				<Icon icon="info-outline" />
			</div>
		</Tooltip>
	);
};

const PaymentMethodCheckbox = ( { onChange, name, checked = false, fees } ) => {
	const { accountFees } = useContext( WCPaySettingsContext );

	const handleChange = useCallback(
		( enabled ) => {
			onChange( name, enabled );
		},
		[ name, onChange ]
	);

	const label = useMemo( () => <PaymentMethodIcon name={ name } showName />, [
		name,
	] );

	return (
		<li className="payment-method-checkbox">
			<CheckboxControl
				checked={ checked }
				onChange={ handleChange }
				label={ label }
			/>
			<Tooltip
				content={ __(
					'Base transaction fees',
					'woocommerce-gateway-stripe'
				) }
			>
				<Pill
					className="payment-method-checkbox__fees"
					aria-label={ sprintf(
						__(
							'Base transaction fees: %s',
							'woocommerce-gateway-stripe'
						),
						fees
					) }
				>
				{__('2.9% + C$0.30', }
				</Pill>
			</Tooltip>
			<PaymentMethodDescription name={ name } />
		</li>
	);
};

export default PaymentMethodCheckbox;
