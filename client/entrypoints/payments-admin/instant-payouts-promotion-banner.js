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
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isDismissed, setIsDismissed ] = useState( false );

	useEffect( () => {
		apiFetch( {
			path: `${ NAMESPACE }/balance`,
		} )
			.then( ( response ) => {
				response.instant_available = [
					{
						amount: 40000,
						currency: 'usd',
					},
					{
						amount: 30000,
						currency: 'eur',
					},
					{
						amount: 25000,
						currency: 'ron',
					},
				];
				response.instantAvailableAmountMessage = sprintf(
					__(
						'You currently have %1$s available.',
						'woocommerce-gateway-stripe'
					),
					formatStripeAmountList( response.instant_available )
				);
				setData( response );
			} )
			.finally( () => setIsLoading( false ) );
	}, [] );

	if ( isDismissed ) {
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
							onClick={ () => setIsDismissed( true ) }
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
