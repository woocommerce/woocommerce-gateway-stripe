import React, { useEffect, useState } from 'react';
import { formatStripeAmount, getDefaultAccountCurrency } from './utils';
import { NAMESPACE } from 'wcstripe/data/constants';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Card, LinkButton, Stack } from '@wordpress/ui';

/**
 * Get the user message for the Instant Payouts promotion notice.
 * Note that we only promote Instant Payouts for the default account currency.
 *
 * @param {Object} response - The response from the API.
 * @return {string|null} The user message.
 */
const getUserMessage = ( response ) => {
	if ( ! response?.instant_available?.length ) {
		return null;
	}

	const defaultAccountCurrency = getDefaultAccountCurrency();
	if ( ! defaultAccountCurrency ) {
		return null;
	}

	const defaultCurrencyInstantAvailable = response?.instant_available?.find(
		( amount ) => amount?.currency === defaultAccountCurrency
	);
	if (
		! defaultCurrencyInstantAvailable ||
		! defaultCurrencyInstantAvailable.amount ||
		! Number.isInteger( defaultCurrencyInstantAvailable.amount )
	) {
		return null;
	}

	return sprintf(
		/* translators: %1$s: The amount of money available for an instant payout. e.g. $123.45, €123.45 */
		__(
			'You currently have %1$s available.',
			'woocommerce-gateway-stripe'
		),
		formatStripeAmount(
			defaultCurrencyInstantAvailable.amount,
			defaultCurrencyInstantAvailable.currency
		)
	);
};

const InstantPayoutsPromotionNotice = () => {
	const [ state, setState ] = useState( {
		data: null,
		isLoading: true,
		isDismissed: false,
		error: null,
		userMessage: null,
	} );
	const { data, isLoading, isDismissed, error, userMessage } = state;

	useEffect( () => {
		apiFetch( {
			path: `${ NAMESPACE }/balance`,
		} )
			.then( ( response ) => {
				const newUserMessage = getUserMessage( response );

				setState( ( previous ) => ( {
					...previous,
					data: response,
					error: null,
					userMessage: newUserMessage,
				} ) );
			} )
			.catch( ( fetchError ) => {
				setState( ( previous ) => ( {
					...previous,
					data: null,
					error: fetchError?.message ?? null,
					userMessage: null,
				} ) );
			} )
			.finally( () => {
				setState( ( previous ) => ( {
					...previous,
					isLoading: false,
				} ) );
			} );
	}, [] );

	if ( isDismissed || isLoading || error ) {
		return null;
	}
	if ( ! data || ! data.instant_available || ! userMessage ) {
		return null;
	}

	return (
		<Card.Root className="wc-stripe-instant-payouts-promotion-notice">
			<Card.Header>
				<Card.Title>
					{ __(
						'Improve your cash flow',
						'woocommerce-gateway-stripe'
					) }
				</Card.Title>
			</Card.Header>
			<Card.Content className="wc-stripe-instant-payouts-promotion-notice__content">
				<div>
					{ __(
						'With Instant Payouts, get access to your balance within minutes — even on weekends and holidays.',
						'woocommerce-gateway-stripe'
					) }{ ' ' }
					<span
						className="wc-stripe-instant-payouts-promotion-notice__availability"
						aria-live="polite"
					>
						{ userMessage }
					</span>
				</div>
				<Stack direction="row" gap="lg">
					<LinkButton
						variant="outline"
						openInNewTab
						href={
							data?.livemode === true
								? 'https://dashboard.stripe.com/payouts/'
								: 'https://dashboard.stripe.com/test/payouts/'
						}
						rel="noreferrer"
					>
						{ __(
							'Pay out instantly',
							'woocommerce-gateway-stripe'
						) }
					</LinkButton>
					<Button
						variant="minimal"
						onClick={ () =>
							setState( ( previous ) => ( {
								...previous,
								isDismissed: true,
							} ) )
						}
					>
						{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
					</Button>
				</Stack>
			</Card.Content>
		</Card.Root>
	);
};

export default InstantPayoutsPromotionNotice;
