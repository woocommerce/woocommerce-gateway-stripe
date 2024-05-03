import { __, sprintf } from '@wordpress/i18n';
import React, { useContext, useState } from 'react';
import styled from '@emotion/styled';
import classnames from 'classnames';
import { Button } from '@wordpress/components';
import { Icon as IconComponent, dragHandle } from '@wordpress/icons';
import { Reorder } from 'framer-motion';
import UpeToggleContext from '../upe-toggle/context';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import CustomizePaymentMethod from './customize-payment-method';
import PaymentMethodCheckbox from './payment-method-checkbox';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useGetOrderedPaymentMethodIds,
	useManualCapture,
} from 'wcstripe/data';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';
import { useAliPayCurrencies } from 'utils/use-alipay-currencies';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';

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

	&.disabled {
		opacity: 0.6;
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
	const { description, acceptsDomesticPaymentsOnly } = PaymentMethodsMap[
		method
	];

	if ( acceptsDomesticPaymentsOnly ) {
		return sprintf( description, accountDefaultCurrency?.toUpperCase() );
	}

	return description;
};

const GeneralSettingsSection = ( {
	isChangingDisplayOrder,
	onSaveChanges,
} ) => {
	const storeCurrency = window?.wcSettings?.currency?.code;
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const [ customizationStatus, setCustomizationStatus ] = useState( {} );
	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();
	const capabilities = useGetCapabilities();
	const [ isManualCaptureEnabled ] = useManualCapture();
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const {
		orderedPaymentMethodIds,
		setOrderedPaymentMethodIds,
	} = useGetOrderedPaymentMethodIds();
	const { data } = useAccount();
	const isTestModeEnabled = Boolean( data.testmode );
	const alipayCurrencies = useAliPayCurrencies();

	// Hide payment methods that are not part of the account capabilities if UPE is enabled in live mode.
	// Show all methods in test mode.
	// Show Multibanco in both test mode and live mode as it is currently using the Sources API and do not need capability check.
	const availablePaymentMethods = isUpeEnabled
		? availablePaymentMethodIds
				.filter(
					( method ) =>
						isTestModeEnabled ||
						method === 'multibanco' ||
						capabilities.hasOwnProperty( `${ method }_payments` )
				)
				.filter( ( id ) => id !== 'link' )
		: orderedPaymentMethodIds;

	// Remove Sofort if it's not enabled. Hide from the new merchants and keep it for the old ones who are already using this gateway, until we remove it completely.
	// Stripe is deprecating Sofort https://support.stripe.com/questions/sofort-is-being-deprecated-as-a-standalone-payment-method.
	if (
		! enabledPaymentMethodIds.includes( 'sofort' ) &&
		availablePaymentMethods.includes( 'sofort' )
	) {
		availablePaymentMethods.splice(
			availablePaymentMethods.indexOf( 'sofort' ),
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
				const {
					Icon,
					label,
					allows_manual_capture: isAllowingManualCapture,
				} = PaymentMethodsMap[ method ];
				const paymentMethodCurrencies =
					method === 'alipay'
						? alipayCurrencies
						: PaymentMethodsMap[ method ]?.currencies || [];
				const isCurrencySupported =
					method === 'card' ||
					paymentMethodCurrencies.includes( storeCurrency );

				return (
					<div key={ method }>
						<ListElement
							key={ method }
							className={ classnames( {
								'has-overlay':
									! isAllowingManualCapture &&
									isManualCaptureEnabled,
								expanded: customizationStatus[ method ],
								disabled: ! isCurrencySupported,
							} ) }
						>
							<PaymentMethodCheckbox
								id={ method }
								label={ label }
								isAllowingManualCapture={
									isAllowingManualCapture
								}
								isCurrencySupported={ isCurrencySupported }
								paymentMethodCurrencies={
									paymentMethodCurrencies
								}
							/>
							<PaymentMethodWrapper>
								<PaymentMethodDescription
									Icon={ Icon }
									description={ getFormattedPaymentMethodDescription(
										method,
										data.account?.default_currency
									) }
									label={ label }
								/>
								<StyledFees id={ method } />
							</PaymentMethodWrapper>
							{ ! isUpeEnabled &&
								isCurrencySupported &&
								! customizationStatus[ method ] && (
									<Button
										variant="secondary"
										onClick={ () =>
											setCustomizationStatus( {
												...customizationStatus,
												[ method ]: true,
											} )
										}
									>
										{ __(
											'Customize',
											'woocommerce-gateway-stripe'
										) }
									</Button>
								) }
						</ListElement>
						{ ! isUpeEnabled &&
							isCurrencySupported &&
							customizationStatus[ method ] && (
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
