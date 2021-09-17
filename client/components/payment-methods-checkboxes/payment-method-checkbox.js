/**
 * External dependencies
 */
import React from 'react';
import { CheckboxControl, Icon, VisuallyHidden } from '@wordpress/components';
import { useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PaymentMethodIcon from '../../settings/payment-method-icon';
import Tooltip from '../tooltip';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';
import paymentMethodsMap from '../../payment-methods-map';
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

			<PaymentMethodFeesPill
				id={ name }
				className="payment-method-checkbox__fees"
			/>
			<PaymentMethodDescription id={ name } />
		</li>
	);
};

export default PaymentMethodCheckbox;
