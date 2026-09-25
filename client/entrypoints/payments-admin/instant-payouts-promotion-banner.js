import React, { useEffect, useState } from 'react';
import { arrowUp } from '@wordpress/icons';
import { formatStripeAmount } from './utils';
import { NAMESPACE } from 'wcstripe/data/constants';
import apiFetch from '@wordpress/api-fetch';
import { Button, Card, CardBody, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

function formatStripeAmountList( amounts ) {
	let amountListAsString = '';
	const numAmounts = amounts.length;
	for ( let i = 0; i < numAmounts; i++ ) {
		const amount = amounts[ i ];

		if ( i > 0 ) {
			amountListAsString +=
				i === numAmounts - 1
					? __( ' and ', 'woocommerce-gateway-stripe' )
					: ', ';
		}
		amountListAsString += formatStripeAmount(
			amount.amount,
			amount.currency
		);
	}
	return amountListAsString;
}

const InstantPayoutsPromotionBanner = () => {
	const [ state, setState ] = useState( {
		data: null,
		isLoading: true,
		isDismissed: false,
	} );
	const { data, isLoading, isDismissed } = state;
	const showBannerIfNoInstantPayoutsAvailable = true;

	useEffect( () => {
		apiFetch( {
			path: `${ NAMESPACE }/balance`,
		} )
			.then( ( response ) => {
				if ( ! response.instant_available ) {
					response.instantAvailableAmountMessage = __(
						'You currently have no instant payouts available.',
						'woocommerce-gateway-stripe'
					);
				} else {
					response.instantAvailableAmountMessage = sprintf(
						__(
							'You currently have %1$s available.',
							'woocommerce-gateway-stripe'
						),
						formatStripeAmountList( response.instant_available )
					);
				}

				setState( ( previous ) => ( {
					...previous,
					data: response,
				} ) );
			} )
			.finally( () => {
				setState( ( previous ) => ( {
					...previous,
					isLoading: false,
				} ) );
			} );
	}, [] );

	if ( isDismissed ) {
		return null;
	}

	if ( ! showBannerIfNoInstantPayoutsAvailable && isLoading ) {
		return null;
	}
	if (
		! showBannerIfNoInstantPayoutsAvailable &&
		( ! data || ! data.instant_available )
	) {
		return null;
	}

	return (
		<Card className="wc-stripe-instant-payouts-promotion-card">
			<CardBody className="wc-stripe-instant-payouts-promotion-card__body">
				<div className="wc-stripe-instant-payouts-promotion-card__content">
					<h2 className="wc-stripe-instant-payouts-promotion-card__title">
						{ __(
							'Improve your cash flow',
							'woocommerce-gateway-stripe'
						) }
					</h2>
					<p className="wc-stripe-instant-payouts-promotion-card__description">
						{ __(
							'With Instant Payouts, get access to your balance within minutes—even on weekends and holidays.',
							'woocommerce-gateway-stripe'
						) }{ ' ' }
						<span
							className="wc-stripe-instant-payouts-promotion-card__availability"
							aria-live="polite"
						>
							{ isLoading ? (
								<Spinner />
							) : (
								data?.instantAvailableAmountMessage
							) }
						</span>
					</p>
					<div className="wc-stripe-instant-payouts-promotion-card__actions">
						<Button
							className="wc-stripe-instant-payouts-promotion-card__payout-button"
							variant="secondary"
							href="https://dashboard.stripe.com/payouts/"
							target="_blank"
							rel="noreferrer"
							icon={ arrowUp }
							iconPosition="right"
							text={ __(
								'Pay out instantly',
								'woocommerce-gateway-stripe'
							) }
							__next40pxDefaultSize
						/>
						<Button
							variant="tertiary"
							onClick={ () =>
								setState( ( previous ) => ( {
									...previous,
									isDismissed: true,
								} ) )
							}
							__next40pxDefaultSize
						>
							{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
						</Button>
					</div>
				</div>
			</CardBody>
		</Card>
	);
};

export default InstantPayoutsPromotionBanner;
