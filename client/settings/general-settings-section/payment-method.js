/* global wc_stripe_settings_params */
import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import classnames from 'classnames';
import { Button } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import CustomizePaymentMethod from './customize-payment-method';
import PaymentMethodCheckbox from './payment-method-checkbox';
import { useManualCapture } from 'wcstripe/data';
import {
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_AFTERPAY_CLEARPAY,
	PAYMENT_METHOD_CARD,
} from 'wcstripe/stripe-utils/constants';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';
import { usePaymentMethodCurrencies } from 'utils/use-payment-method-currencies';

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

/**
 * Formats the payment method description with the account default currency.
 *
 * @param {*} method Payment method ID.
 * @param {*} accountDefaultCurrency Account default currency.
 */
const getFormattedPaymentMethodDescription = (
	method,
	accountDefaultCurrency
) => {
	const { description } = PaymentMethodsMap[ method ];

	if ( method === PAYMENT_METHOD_AFFIRM ) {
		const currency = accountDefaultCurrency?.toUpperCase();
		return sprintf( description, currency, currency, currency );
	}

	if ( method === PAYMENT_METHOD_AFTERPAY_CLEARPAY ) {
		/* eslint-disable jsx-a11y/anchor-has-content */
		return interpolateComponents( {
			mixedString: description,
			components: {
				limitsLink: (
					<a
						target="_blank"
						rel="noreferrer"
						href="https://docs.stripe.com/payments/afterpay-clearpay#collection-schedule"
					/>
				),
			},
		} );
		/* eslint-enable jsx-a11y/anchor-has-content */
	}

	return description;
};

const StyledFees = styled( PaymentMethodFeesPill )`
	flex: 1 0 auto;
`;

const CustomizeButton = styled( Button )`
	margin-left: auto;
`;

const PaymentMethod = ( {
	method,
	onSaveChanges,
	customizationStatus,
	setCustomizationStatus,
	data,
} ) => {
	const [ isManualCaptureEnabled ] = useManualCapture();
	const paymentMethodCurrencies = usePaymentMethodCurrencies( method );

	const { Icon, label, allows_manual_capture: isAllowingManualCapture } =
		PaymentMethodsMap[ method ] || {};

	// Skip if there are no mapped fields for the payment method.
	if ( ! Icon || ! label ) {
		return null;
	}

	// Remove APMs (legacy checkout) due deprecation by Stripe on Oct 31st, 2024.
	const deprecated =
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params.are_apms_deprecated &&
		method !== PAYMENT_METHOD_CARD;

	const storeCurrency = window?.wcSettings?.currency?.code;
	const isDisabled =
		paymentMethodCurrencies.length &&
		! paymentMethodCurrencies.includes( storeCurrency );

	const onSaveCustomization = ( methodName, customizationData = null ) => {
		setCustomizationStatus( {
			...customizationStatus,
			[ methodName ]: false,
		} );

		if ( data ) {
			onSaveChanges(
				'individual_payment_method_settings',
				customizationData
			);
		}
	};

	return (
		<div key={ method }>
			<ListElement
				key={ method }
				className={ classnames( {
					'has-overlay':
						! isAllowingManualCapture && isManualCaptureEnabled,
					expanded: customizationStatus[ method ],
				} ) }
			>
				<PaymentMethodCheckbox
					id={ method }
					label={ label }
					isAllowingManualCapture={ isAllowingManualCapture }
					disabled={ deprecated || isDisabled }
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
						deprecated={ deprecated }
					/>
					<StyledFees id={ method } />
				</PaymentMethodWrapper>
				{ ! customizationStatus[ method ] && (
					<CustomizeButton
						variant="secondary"
						onClick={ () =>
							setCustomizationStatus( {
								...customizationStatus,
								[ method ]: true,
							} )
						}
						disabled={ deprecated }
					>
						{ __( 'Customize', 'woocommerce-gateway-stripe' ) }
					</CustomizeButton>
				) }
			</ListElement>
			{ customizationStatus[ method ] && (
				<CustomizePaymentMethod
					method={ method }
					onClose={ ( customizationData ) =>
						onSaveCustomization( method, customizationData )
					}
				/>
			) }
		</div>
	);
};

export default PaymentMethod;
