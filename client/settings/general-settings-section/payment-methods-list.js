import { getSetting } from '@woocommerce/settings';
import React, { useMemo } from 'react';
import styled from '@emotion/styled';
import classnames from 'classnames';
import { Icon as IconComponent, dragHandle } from '@wordpress/icons';
import { Reorder } from 'framer-motion';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import PaymentMethod from './payment-method';
import getPaymentMethodUnavailableReason from 'utils/get-payment-method-unavailable-reason';
import {
	useEnabledPaymentMethodIds,
	useGetOrderedPaymentMethodIds,
	useIsAdaptivePricingEnabled,
	useIsOCEnabled,
	useManualCapture,
} from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';
import {
	PAYMENT_METHOD_GIROPAY,
	PAYMENT_METHOD_SOFORT,
	PAYMENT_METHOD_UNAVAILABLE_REASONS,
} from 'wcstripe/stripe-utils/constants';
import { getFormattedPaymentMethodDescription } from 'wcstripe/settings/general-settings-section/get-formatted-payment-method-description';

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
const usePaymentMethodsSortedByAvailability = ( orderedPaymentMethodIds ) => {
	const [ isAdaptivePricingEnabled ] = useIsAdaptivePricingEnabled();
	const [ isOCEnabled ] = useIsOCEnabled();
	const storeCurrencyCode = getSetting( 'currency' )?.code;
	const isAdaptivePricingSupported = isOCEnabled && isAdaptivePricingEnabled;

	const sortedPaymentMethodIds = useMemo( () => {
		const availablePaymentMethodIds = [];
		const pluginConflictPaymentMethodIds = [];
		const unavailablePaymentMethodIds = [];

		orderedPaymentMethodIds.forEach( ( paymentMethodId ) => {
			const unavailableReason = getPaymentMethodUnavailableReason( {
				paymentMethodId,
				storeCurrencyCode,
				isAdaptivePricingSupported,
			} );
			if ( unavailableReason === null ) {
				availablePaymentMethodIds.push( paymentMethodId );
			} else if (
				unavailableReason ===
				PAYMENT_METHOD_UNAVAILABLE_REASONS.OFFICIAL_PLUGIN_CONFLICT
			) {
				pluginConflictPaymentMethodIds.push( paymentMethodId );
			} else {
				unavailablePaymentMethodIds.push( paymentMethodId );
			}
		} );

		return [
			...availablePaymentMethodIds,
			...pluginConflictPaymentMethodIds,
			...unavailablePaymentMethodIds,
		];
	}, [
		isAdaptivePricingSupported,
		orderedPaymentMethodIds,
		storeCurrencyCode,
	] );

	return sortedPaymentMethodIds;
};

const GeneralSettingsSection = ( { isChangingDisplayOrder } ) => {
	const [ isManualCaptureEnabled ] = useManualCapture();
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const { orderedPaymentMethodIds, setOrderedPaymentMethodIds } =
		useGetOrderedPaymentMethodIds();
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

	const sortedPaymentMethodIds = usePaymentMethodsSortedByAvailability(
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
