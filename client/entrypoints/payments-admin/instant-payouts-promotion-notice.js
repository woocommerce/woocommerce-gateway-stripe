import React, { useEffect, useState } from 'react';
import { arrowUp } from '@wordpress/icons';
import { formatStripeAmount } from './utils';
import { NAMESPACE } from 'wcstripe/data/constants';
import InlineNotice from 'wcstripe/components/inline-notice';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
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

const InstantPayoutsPromotionNotice = ( {
	showNoticeIfNoInstantPayoutsAvailable = true,
} ) => {
	const [ state, setState ] = useState( {
		data: null,
		isLoading: true,
		isDismissed: false,
		error: null,
	} );
	const { data, isLoading, isDismissed, error } = state;

	useEffect( () => {
		apiFetch( {
			path: `${ NAMESPACE }/balance`,
		} )
			.then( ( response ) => {
				const instantAvailableAmountMessage =
					response.instant_available?.length > 0
						? sprintf(
								__(
									'You currently have %1$s available.',
									'woocommerce-gateway-stripe'
								),
								formatStripeAmountList(
									response.instant_available
								)
						  )
						: __(
								'You currently have no instant payouts available.',
								'woocommerce-gateway-stripe'
						  );

				setState( ( previous ) => ( {
					...previous,
					data: { ...response, instantAvailableAmountMessage },
					error: null,
				} ) );
			} )
			.catch( ( fetchError ) => {
				setState( ( previous ) => ( {
					...previous,
					error: fetchError?.message ?? null,
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

	if ( ! showNoticeIfNoInstantPayoutsAvailable && isLoading ) {
		return null;
	}
	if (
		! showNoticeIfNoInstantPayoutsAvailable &&
		( ! data || ! data.instant_available )
	) {
		return null;
	}

	return (
		<Notice
			className="wc-stripe-instant-payouts-promotion-notice"
			status="info"
			spokenMessage={ __(
				'Improve your cash flow',
				'woocommerce-gateway-stripe'
			) }
			isDismissible={ false }
		>
			{ error && (
				<InlineNotice
					status="error"
					isDismissible
					onRemove={ () =>
						setState( ( previous ) => ( {
							...previous,
							error: null,
						} ) )
					}
				>
					{ error }
				</InlineNotice>
			) }
			<div className="wc-stripe-instant-payouts-promotion-notice__content">
				<h2 className="wc-stripe-instant-payouts-promotion-notice__title">
					{ __(
						'Improve your cash flow',
						'woocommerce-gateway-stripe'
					) }
				</h2>
				<p className="wc-stripe-instant-payouts-promotion-notice__description">
					{ __(
						'With Instant Payouts, get access to your balance within minutes—even on weekends and holidays.',
						'woocommerce-gateway-stripe'
					) }{ ' ' }
					<span
						className="wc-stripe-instant-payouts-promotion-notice__availability"
						aria-live="polite"
					>
						{ isLoading ? (
							<Spinner />
						) : (
							data?.instantAvailableAmountMessage
						) }
					</span>
				</p>
				<div className="wc-stripe-instant-payouts-promotion-notice__actions">
					<Button
						className="wc-stripe-instant-payouts-promotion-notice__payout-button"
						variant="secondary"
						href={
							data?.livemode === false
								? 'https://dashboard.stripe.com/test/payouts/'
								: 'https://dashboard.stripe.com/payouts/'
						}
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
		</Notice>
	);
};

export default InstantPayoutsPromotionNotice;
