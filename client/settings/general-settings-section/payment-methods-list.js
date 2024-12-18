/* global wc_stripe_settings_params */
import { __, sprintf } from '@wordpress/i18n';
import React, { useState } from 'react';
import styled from '@emotion/styled';
import classnames from 'classnames';
import { Button } from '@wordpress/components';
import { Icon as IconComponent, dragHandle } from '@wordpress/icons';
import { Reorder } from 'framer-motion';
import interpolateComponents from 'interpolate-components';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import CustomizePaymentMethod from './customize-payment-method';
import PaymentMethodCheckbox from './payment-method-checkbox';
import {
	useEnabledPaymentMethodIds,
	useGetOrderedPaymentMethodIds,
	useManualCapture,
} from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';
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

const CustomizeButton = styled( Button )`
	margin-left: auto;
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

const GeneralSettingsSection = ( {
	isChangingDisplayOrder,
	onSaveChanges,
} ) => {
	const [ customizationStatus, setCustomizationStatus ] = useState( {} );
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

	const onSaveCustomization = ( method, customizationData = null ) => {
		setCustomizationStatus( {
			...customizationStatus,
			[ method ]: false,
		} );

		if ( data ) {
			onSaveChanges(
				'individual_payment_method_settings',
				customizationData
			);
		}
	};

	return isChangingDisplayOrder ? (
		<DraggableList
			axis="y"
			values={ availablePaymentMethods }
			onReorder={ onReorder }
		>
			{ availablePaymentMethods.map( ( method ) => {
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
				} = PaymentMethodsMap[ method ];

				return (
					<DraggableListElement
						key={ method }
						value={ method }
						className={ classnames( {
							'has-overlay':
								! isAllowingManualCapture &&
								isManualCaptureEnabled,
							expanded: customizationStatus[ method ],
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
			{ availablePaymentMethods.map( ( method ) => {
				// Skip giropay as it was deprecated by Jun, 30th 2024.
				if ( method === PAYMENT_METHOD_GIROPAY ) {
					return null;
				}

				const {
					Icon,
					label,
					allows_manual_capture: isAllowingManualCapture,
				} = PaymentMethodsMap[ method ];

				// Remove APMs (legacy checkout) due deprecation by Stripe on Oct 31st, 2024.
				const deprecated =
					// eslint-disable-next-line camelcase
					wc_stripe_settings_params.are_apms_deprecated &&
					method !== PAYMENT_METHOD_CARD;

				return (
					<div key={ method }>
						<ListElement
							key={ method }
							className={ classnames( {
								'has-overlay':
									! isAllowingManualCapture &&
									isManualCaptureEnabled,
								expanded: customizationStatus[ method ],
							} ) }
						>
							<PaymentMethodCheckbox
								id={ method }
								label={ label }
								isAllowingManualCapture={
									isAllowingManualCapture
								}
								disabled={ deprecated }
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
									{ __(
										'Customize',
										'woocommerce-gateway-stripe'
									) }
								</CustomizeButton>
							) }
						</ListElement>
						{ customizationStatus[ method ] && (
							<CustomizePaymentMethod
								method={ method }
								onClose={ ( customizationData ) =>
									onSaveCustomization(
										method,
										customizationData
									)
								}
							/>
						) }
					</div>
				);
			} ) }
		</List>
	);
};

export default GeneralSettingsSection;
