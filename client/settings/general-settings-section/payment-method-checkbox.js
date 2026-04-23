import React from 'react';
import styled from '@emotion/styled';
import { Icon, info } from '@wordpress/icons';
import { CheckboxControl, VisuallyHidden } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useEnabledPaymentMethodIds, useManualCapture } from 'wcstripe/data';
import Tooltip from 'wcstripe/components/tooltip';
import { PAYMENT_METHOD_SOFORT } from 'wcstripe/stripe-utils/constants';

const StyledCheckbox = styled( CheckboxControl )`
	.components-base-control__field {
		margin-bottom: 0;
	}
`;

const AlertIcon = styled( Icon )`
	fill: #f0b849;
`;

const IconWrapper = styled.span`
	margin-right: 8px;
	flex-shrink: 0;
`;

const PaymentMethodCheckbox = ( {
	id,
	label,
	isAllowingManualCapture,
	disabled,
} ) => {
	const [ isManualCaptureEnabled ] = useManualCapture();
	const [ enabledPaymentMethods, setEnabledPaymentMethods ] =
		useEnabledPaymentMethodIds();
	const checked = ! disabled && enabledPaymentMethods.includes( id );

	const handleCheckboxChange = ( hasBeenChecked ) => {
		if ( disabled ) {
			return;
		}
		if ( ! hasBeenChecked ) {
			// Sofort is being deprecated by Stripe and is hidden from the
			// available list once disabled, so the action is irreversible
			// from this UI. Confirm before proceeding.
			if (
				id === PAYMENT_METHOD_SOFORT &&
				// eslint-disable-next-line no-alert
				! window.confirm(
					__(
						'Sofort is being deprecated by Stripe and cannot be re-enabled once disabled. Are you sure you want to disable it?',
						'woocommerce-gateway-stripe'
					)
				)
			) {
				return;
			}
			setEnabledPaymentMethods(
				enabledPaymentMethods.filter( ( m ) => m !== id )
			);
			return;
		}

		setEnabledPaymentMethods( [ ...enabledPaymentMethods, id ] );
	};

	return (
		<>
			{ isManualCaptureEnabled && ! isAllowingManualCapture ? (
				<Tooltip
					content={ sprintf(
						/* translators: %s: a payment method name. */
						__(
							'%s is not available to your customers when the "manual capture" setting is enabled.',
							'woocommerce-gateway-stripe'
						),
						label
					) }
				>
					{ /* a span element is added here to ensure the tooltip can get the correct content to position itself */ }
					<IconWrapper>
						<AlertIcon icon={ info } />
						<VisuallyHidden>
							{ sprintf(
								/* translators: %s: a payment method name. */
								__(
									'%s cannot be enabled at checkout. Click to expand.',
									'woocommerce-gateway-stripe'
								),
								label
							) }
						</VisuallyHidden>
					</IconWrapper>
				</Tooltip>
			) : (
				<StyledCheckbox
					label={ <VisuallyHidden>{ label }</VisuallyHidden> }
					onChange={ handleCheckboxChange }
					checked={ checked }
					disabled={ disabled }
				/>
			) }
		</>
	);
};

export default PaymentMethodCheckbox;
