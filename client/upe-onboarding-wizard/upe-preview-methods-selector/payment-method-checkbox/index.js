import { useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import styled from '@emotion/styled';
import React from 'react';
import { CheckboxControl, VisuallyHidden } from '@wordpress/components';
import { Icon, info } from '@wordpress/icons';
import PaymentMethodMissingCurrencyPill from '../../../components/payment-method-missing-currency-pill';
import PaymentMethodIcon from 'wcstripe/settings/payment-method-icon';
import Tooltip from 'wcstripe/components/tooltip';
import paymentMethodsMap from 'wcstripe/payment-methods-map';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';
import PaymentMethodCapabilityStatusPill from 'wcstripe/components/payment-method-capability-status-pill';
import './style.scss';

const InfoIcon = styled( Icon )`
	fill: #949494;
`;

const InfoWrapper = styled.div`
	flex-shrink: 0;
	width: 20px;
	height: 20px;
`;

const PaymentMethodDescription = ( { id } ) => {
	const description = paymentMethodsMap[ id ]?.description;
	if ( ! description ) {
		return null;
	}

	return (
		<Tooltip content={ description }>
			<InfoWrapper>
				<InfoIcon icon={ info } size={ 20 } />
				<VisuallyHidden>
					{ __(
						'Information about the payment method, click to expand',
						'woocommerce-gateway-stripe'
					) }
				</VisuallyHidden>
			</InfoWrapper>
		</Tooltip>
	);
};

const PaymentMethodCheckbox = ( { onChange, id, checked = false } ) => {
	const handleChange = useCallback(
		( enabled ) => {
			onChange( id, enabled );
		},
		[ id, onChange ]
	);

	const label = useMemo( () => <PaymentMethodIcon name={ id } showName />, [
		id,
	] );
	const pillLabel = paymentMethodsMap[ id ]?.label;

	return (
		<li className="payment-method-checkbox">
			<div className="payment-method-checkbox__element-wrapper">
				<CheckboxControl
					checked={ checked }
					onChange={ handleChange }
					label={ label }
				/>
				<PaymentMethodCapabilityStatusPill
					id={ id }
					label={ pillLabel }
				/>
				<PaymentMethodMissingCurrencyPill
					id={ id }
					label={ pillLabel }
				/>
			</div>

			<div className="payment-method-checkbox__element-wrapper">
				<PaymentMethodFeesPill
					id={ id }
					className="payment-method-checkbox__fees"
				/>
				<PaymentMethodDescription id={ id } />
			</div>
		</li>
	);
};

export default PaymentMethodCheckbox;
