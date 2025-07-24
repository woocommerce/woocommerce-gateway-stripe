/* global wc_stripe_settings_params */
import { sprintf } from '@wordpress/i18n';
import React, { useContext, useMemo } from 'react';
import styled from '@emotion/styled';
import classnames from 'classnames';
import { Icon as IconComponent, dragHandle } from '@wordpress/icons';
import { Reorder } from 'framer-motion';
import interpolateComponents from 'interpolate-components';
import PaymentMethodsMap from '../../payment-methods-map';
import UpeToggleContext from '../upe-toggle/context';
import PaymentMethodDescription from './payment-method-description';
import PaymentMethod from './payment-method';
import {
	useEnabledPaymentMethodIds,
	useGetOrderedPaymentMethodIds,
	useManualCapture,
} from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';
import { getPaymentMethodCurrencies } from 'wcstripe/utils/use-payment-method-currencies';
import {
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_AFTERPAY_CLEARPAY,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_GIROPAY,
	PAYMENT_METHOD_SOFORT,
} from 'wcstripe/stripe-utils/constants';

const List = styled.ul`
	margin: 0;

	> li {
		margin: 0;
		padding: 16px 24px 14px 24px;

		@media ( min-width: 660px ) {
			padding: 24px 24px 24px 24px;
		}

		&:not( :last-child ) {
			box-shadow: inset 0 -1px 0 #e8eaeb;
		}

		&.expanded {
			box-shadow: none;
			padding-bottom: 0;
		}
	}

	> div {
		margin: 0;
		padding: 16px 24px 14px 24px;

		@media ( min-width: 660px ) {
			padding: 16px 24px 24px 24px;
		}

		&:not( :last-child ) {
			box-shadow: inset 0 -1px 0 #e8eaeb;
		}
	}
`;

const DraggableList = styled( Reorder.Group )`
	margin: 0;

	> li {
		margin: 0;
		padding: 16px 24px 14px 24px;
		background-color: #fff;
		cursor: grab;

		@media ( min-width: 660px ) {
			padding: 24px 24px 24px 24px;
		}

		&:not( :last-child ) {
			box-shadow: inset 0 -1px 0 #e8eaeb;
		}
	}
`;

const DraggableListElement = styled( Reorder.Item )`
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

	svg.drag-handle {
		transform: rotate( 90deg );
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

/**
 * Hook to sort the payment methods based on whether the payment method is supported by the store currency.
 * Unsupported payment methods are placed at the end of the list so irrelevant payment methods don't clutter the screen.
 *
 * @param {string[]} orderedPaymentMethodIds Ordered payment method IDs.
 * @return {string[]} Sorted payment method IDs.
 */
const usePaymentMethodsSortedByStoreCurrencySupport = (
	orderedPaymentMethodIds
) => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	const storeCurrency = window?.wcSettings?.currency?.code;

	// In the logic below, note that getPaymentMethodCurrencies() can return []
	// when the payment method supports all currencies.
	// Note that when we don't have a store currency, we put all methods in the supported list.

	const sortedPaymentMethodIds = useMemo( () => {
		if ( ! storeCurrency ) {
			return orderedPaymentMethodIds;
		}

		const supportedPaymentMethodIds = [];
		const unsupportedPaymentMethodIds = [];

		orderedPaymentMethodIds.forEach( ( paymentMethodId ) => {
			const paymentMethodCurrencies = getPaymentMethodCurrencies(
				paymentMethodId,
				isUpeEnabled
			);

			if (
				paymentMethodCurrencies.length === 0 ||
				paymentMethodCurrencies.includes( storeCurrency )
			) {
				supportedPaymentMethodIds.push( paymentMethodId );
			} else {
				unsupportedPaymentMethodIds.push( paymentMethodId );
			}
		} );

		return [ ...supportedPaymentMethodIds, ...unsupportedPaymentMethodIds ];
	}, [ orderedPaymentMethodIds, storeCurrency, isUpeEnabled ] );

	return sortedPaymentMethodIds;
};

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

const GeneralSettingsSection = ( { isChangingDisplayOrder } ) => {
	const [ isManualCaptureEnabled ] = useManualCapture();
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const {
		orderedPaymentMethodIds,
		setOrderedPaymentMethodIds,
	} = useGetOrderedPaymentMethodIds();
	const { data } = useAccount();

	const availablePaymentMethods = orderedPaymentMethodIds;

	// Remove Sofort if it's not enabled. Hide from the new merchants and keep it for the old ones who are already using this gateway, until we remove it completely.
	// Stripe is deprecating Sofort https://support.stripe.com/questions/sofort-is-being-deprecated-as-a-standalone-payment-method.
	if (
		! enabledPaymentMethodIds.includes( PAYMENT_METHOD_SOFORT ) &&
		availablePaymentMethods.includes( PAYMENT_METHOD_SOFORT )
	) {
		availablePaymentMethods.splice(
			availablePaymentMethods.indexOf( PAYMENT_METHOD_SOFORT ),
			1
		);
	}

	const onReorder = ( newOrderedPaymentMethodIds ) => {
		setOrderedPaymentMethodIds( newOrderedPaymentMethodIds );
	};

	const sortedPaymentMethodIds = usePaymentMethodsSortedByStoreCurrencySupport(
		availablePaymentMethods
	);

	return isChangingDisplayOrder ? (
		<DraggableList
			axis="y"
			values={ sortedPaymentMethodIds }
			onReorder={ onReorder }
		>
			{ sortedPaymentMethodIds.map( ( method ) => {
				// Skip giropay as it was deprecated by Jun, 30th 2024.
				if ( method === PAYMENT_METHOD_GIROPAY ) {
					return null;
				}

				// Remove APMs (legacy checkout) due deprecation by Stripe on Oct 31st, 2024.
				if (
					// eslint-disable-next-line camelcase
					wc_stripe_settings_params.are_apms_deprecated &&
					method !== PAYMENT_METHOD_CARD
				) {
					return null;
				}

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

				return (
					<DraggableListElement
						key={ method }
						value={ method }
						className={ classnames( {
							'has-overlay':
								! isAllowingManualCapture &&
								isManualCaptureEnabled,
						} ) }
					>
						<IconComponent
							className="drag-handle"
							icon={ dragHandle }
							size="10"
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
						<StyledFees id={ method } />
					</DraggableListElement>
				);
			} ) }
		</DraggableList>
	) : (
		<List>
			{ sortedPaymentMethodIds.map( ( method ) => {
				// Skip giropay as it was deprecated by Jun, 30th 2024.
				if ( method === PAYMENT_METHOD_GIROPAY ) {
					return null;
				}

				return (
					<PaymentMethod
						key={ method }
						method={ method }
						data={ data }
					/>
				);
			} ) }
		</List>
	);
};

export default GeneralSettingsSection;
