import { useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import React from 'react';
import { CheckboxControl, Icon, VisuallyHidden } from '@wordpress/components';
import PaymentMethodIcon from '../../settings/payment-method-icon';
import Tooltip from '../tooltip';
import paymentMethodsMap from '../../payment-methods-map';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';
import PaymentMethodSetupHelp from 'wcstripe/settings/general-settings-section/payment-method-setup-help';
import './style.scss';

const PaymentMethodDescription = ( { id } ) => {
	const description = paymentMethodsMap[ id ]?.description;
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

const PaymentMethodCheckbox = ( { onChange, name, checked = false } ) => {
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
			<PaymentMethodSetupHelp
				id={ name }
				label={ paymentMethodsMap[ name ]?.label }
			/>

			<PaymentMethodFeesPill
				id={ name }
				className="payment-method-checkbox__fees"
			/>
			<PaymentMethodDescription id={ name } />
		</li>
	);
};

export default PaymentMethodCheckbox;
