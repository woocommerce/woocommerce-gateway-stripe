import React from 'react';
import styled from '@emotion/styled';
import classnames from 'classnames';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import PaymentMethodCheckbox from './payment-method-checkbox';
import { useEnabledPaymentMethodIds, useManualCapture } from 'wcstripe/data';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';
import usePaymentMethodUnavailableReason from 'utils/use-payment-method-unavailable-reason';
import { getFormattedPaymentMethodDescription } from 'wcstripe/settings/general-settings-section/get-formatted-payment-method-description';

const ListElement = styled.li`
	display: flex;
	flex-wrap: nowrap;
	gap: 16px;

	@media ( min-width: 660px ) {
		align-items: center;
	}

	&.has-overlay {
		position: relative;

		&:after {
			content: '';
			position: absolute;
			// adds some spacing for the borders, so that they're not part of the opacity
			top: 1px;
			bottom: 1px;
			// ensures that the info icon isn't part of the opacity
			left: 55px;
			right: 0;
			background: white;
			opacity: 0.5;
			pointer-events: none;
		}
	}

	button {
		&.hide {
			visibility: hidden;
		}
	}
`;

const PaymentMethodWrapper = styled.div`
	display: flex;
	flex-direction: column;
	gap: 20px;

	@media ( min-width: 660px ) {
		flex-direction: row;
		flex-wrap: nowrap;
		align-items: center;
	}
`;

const StyledFees = styled( PaymentMethodFeesPill )`
	flex: 1 0 auto;
`;

const PaymentMethod = ( { method, data } ) => {
	const [ isManualCaptureEnabled ] = useManualCapture();
	const paymentMethodUnavailableReason =
		usePaymentMethodUnavailableReason( method );
	const [ enabledPaymentMethods ] = useEnabledPaymentMethodIds();

	const {
		Icon,
		label,
		allows_manual_capture: isAllowingManualCapture,
		supportsRecurring,
	} = PaymentMethodsMap[ method ] || {};

	// Skip if there are no mapped fields for the payment method.
	if ( ! Icon || ! label ) {
		return null;
	}

	// If the payment method is unavailable and enabled, we should not disable so it can be unchecked.
	const isDisabled =
		paymentMethodUnavailableReason !== null &&
		! enabledPaymentMethods.includes( method );

	return (
		<div key={ method }>
			<ListElement
				key={ method }
				className={ classnames( {
					'has-overlay':
						! isAllowingManualCapture && isManualCaptureEnabled,
				} ) }
			>
				<PaymentMethodCheckbox
					id={ method }
					label={ label }
					isAllowingManualCapture={ isAllowingManualCapture }
					disabled={ isDisabled }
				/>
				<PaymentMethodWrapper>
					<PaymentMethodDescription
						id={ method }
						Icon={ Icon }
						description={ getFormattedPaymentMethodDescription(
							method,
							data.account?.default_currency
						) }
						label={ label }
						supportsRecurring={ supportsRecurring }
					/>
					<StyledFees id={ method } />
				</PaymentMethodWrapper>
			</ListElement>
		</div>
	);
};

export default PaymentMethod;
