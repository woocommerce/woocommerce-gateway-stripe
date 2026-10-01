import { getSetting } from '@woocommerce/settings';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import styled from '@emotion/styled';
import clsx from 'clsx';
import { chevronDown, chevronUp } from '@wordpress/icons';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import PaymentMethod from './payment-method';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import getPaymentMethodUnavailableReason from 'utils/get-payment-method-unavailable-reason';
import {
	useGetOrderedPaymentMethodIds,
	useIsAdaptivePricingEnabled,
	useIsOCEnabled,
	useManualCapture,
} from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';
import { PAYMENT_METHOD_UNAVAILABLE_REASONS } from 'wcstripe/stripe-utils/constants';
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

const ReorderableList = styled.ul`
	margin: 0;

	> li {
		margin: 0;
		padding: 16px 24px 14px 24px;
		background-color: #fff;

		@media ( min-width: 660px ) {
			padding: 24px 24px 24px 24px;
		}

		&:not( :last-child ) {
			box-shadow: inset 0 -1px 0 #e8eaeb;
		}
	}
`;

const ReorderableListElement = styled.li`
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

	.move-buttons {
		display: flex;
		flex-direction: column;
		flex-shrink: 0;
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
 * Hook to group the payment methods based on whether the payment method is supported by the store currency.
 * The list shows unsupported payment methods at the end so irrelevant payment methods don't clutter the screen.
 *
 * @param {string[]} orderedPaymentMethodIds Ordered payment method IDs.
 * @return {string[][]} Payment method IDs grouped as available, plugin conflict and unavailable, in that order.
 */
const usePaymentMethodsGroupedByAvailability = ( orderedPaymentMethodIds ) => {
	const [ isAdaptivePricingEnabled ] = useIsAdaptivePricingEnabled();
	const [ isOCEnabled ] = useIsOCEnabled();
	const storeCurrencyCode = getSetting( 'currency' )?.code;
	const isAdaptivePricingSupported = isOCEnabled && isAdaptivePricingEnabled;

	return useMemo( () => {
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
			availablePaymentMethodIds,
			pluginConflictPaymentMethodIds,
			unavailablePaymentMethodIds,
		];
	}, [
		isAdaptivePricingSupported,
		orderedPaymentMethodIds,
		storeCurrencyCode,
	] );
};

const GeneralSettingsSection = ( { isChangingDisplayOrder } ) => {
	const [ isManualCaptureEnabled ] = useManualCapture();
	const { orderedPaymentMethodIds, setOrderedPaymentMethodIds } =
		useGetOrderedPaymentMethodIds();
	const { data } = useAccount();

	const availablePaymentMethods = orderedPaymentMethodIds;

	const paymentMethodGroups = usePaymentMethodsGroupedByAvailability(
		availablePaymentMethods
	);
	const sortedPaymentMethodIds = paymentMethodGroups.flat();

	// Unavailable methods always render after available ones, so a move can
	// only swap within a group. Methods without a mapped icon and label aren't
	// shown, so they're skipped or a move would look like a no-op.
	const visibleGroups = paymentMethodGroups.map( ( group ) =>
		group.filter(
			( method ) =>
				PaymentMethodsMap[ method ]?.Icon &&
				PaymentMethodsMap[ method ]?.label
		)
	);
	const getVisibleGroup = ( method ) =>
		visibleGroups.find( ( group ) => group.includes( method ) );

	const listRef = useRef();
	const [ focusAfterMove, setFocusAfterMove ] = useState( null );

	// React moves the row's DOM node on re-render, which can drop focus
	// from the button that was just pressed.
	useEffect( () => {
		if ( focusAfterMove ) {
			listRef.current
				?.querySelector( `[data-move="${ focusAfterMove }"]` )
				?.focus();
			setFocusAfterMove( null );
		}
	}, [ focusAfterMove ] );

	const moveMethod = ( method, offset ) => {
		const group = getVisibleGroup( method );
		const neighbour = group[ group.indexOf( method ) + offset ];
		if ( ! neighbour ) {
			return;
		}

		const next = [ ...orderedPaymentMethodIds ];
		const from = next.indexOf( method );
		const to = next.indexOf( neighbour );
		[ next[ from ], next[ to ] ] = [ next[ to ], next[ from ] ];
		setOrderedPaymentMethodIds( next );
		setFocusAfterMove( `${ method }-${ offset < 0 ? 'up' : 'down' }` );
	};

	return isChangingDisplayOrder ? (
		<ReorderableList ref={ listRef }>
			{ visibleGroups.flat().map( ( method ) => {
				const {
					Icon,
					label,
					allows_manual_capture: isAllowingManualCapture,
					supportsRecurring,
				} = PaymentMethodsMap[ method ];
				const group = getVisibleGroup( method );
				const isFirst = group[ 0 ] === method;
				const isLast = group[ group.length - 1 ] === method;

				return (
					<ReorderableListElement
						key={ method }
						className={ clsx( {
							'has-overlay':
								! isAllowingManualCapture &&
								isManualCaptureEnabled,
						} ) }
					>
						<div className="move-buttons">
							<Button
								icon={ chevronUp }
								size="small"
								data-move={ `${ method }-up` }
								label={ sprintf(
									/* translators: %s: payment method name, e.g. "Credit card / debit card". */
									__(
										'Move %s up',
										'woocommerce-gateway-stripe'
									),
									label
								) }
								disabled={ isFirst }
								// Keeps focus on the button when a row reaches either end.
								accessibleWhenDisabled
								onClick={ () => moveMethod( method, -1 ) }
							/>
							<Button
								icon={ chevronDown }
								size="small"
								data-move={ `${ method }-down` }
								label={ sprintf(
									/* translators: %s: payment method name, e.g. "Credit card / debit card". */
									__(
										'Move %s down',
										'woocommerce-gateway-stripe'
									),
									label
								) }
								disabled={ isLast }
								accessibleWhenDisabled
								onClick={ () => moveMethod( method, 1 ) }
							/>
						</div>
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
					</ReorderableListElement>
				);
			} ) }
		</ReorderableList>
	) : (
		<List>
			{ sortedPaymentMethodIds.map( ( method ) => (
				<PaymentMethod key={ method } method={ method } data={ data } />
			) ) }
		</List>
	);
};

export default GeneralSettingsSection;
