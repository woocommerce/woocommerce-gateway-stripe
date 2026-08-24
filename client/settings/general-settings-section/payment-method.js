import React from 'react';
import styled from '@emotion/styled';
import clsx from 'clsx';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import PaymentMethodCheckbox from './payment-method-checkbox';
import { useEnabledPaymentMethodIds, useManualCapture } from 'wcstripe/data';
import usePaymentMethodUnavailableReason from 'utils/use-payment-method-unavailable-reason';
import { getFormattedPaymentMethodDescription } from 'wcstripe/settings/general-settings-section/get-formatted-payment-method-description';
import { PAYMENT_METHOD_UNAVAILABLE_REASONS } from 'wcstripe/stripe-utils/constants';

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

	// Currency support depends on the checkout currency, so it must not block configuration.
	const isDisabled =
		paymentMethodUnavailableReason !== null &&
		paymentMethodUnavailableReason !==
			PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY &&
		! enabledPaymentMethods.includes( method );

	return (
		<div key={ method }>
			<ListElement
				key={ method }
				className={ clsx( {
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
				</PaymentMethodWrapper>
			</ListElement>
		</div>
	);
};

export default PaymentMethod;
