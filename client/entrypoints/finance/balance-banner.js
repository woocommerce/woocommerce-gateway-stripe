import React, { useEffect, useState } from 'react';
import { formatStripeAmount } from './utils';
import { NAMESPACE } from 'wcstripe/data/constants';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const BalanceSection = ( { action, balances, label } ) => (
	<section className="wc-stripe-balance-card__section">
		<h3 className="wc-stripe-balance-card__label">{ label }</h3>
		<div className="wc-stripe-balance-card__content">
			<ul className="wc-stripe-balance-card__amounts">
				{ balances.length > 0 ? (
					balances.map( ( item ) => (
						<li
							key={ item.currency }
							className="wc-stripe-balance-card__amount"
						>
							{ formatStripeAmount( item.amount, item.currency ) }
						</li>
					) )
				) : (
					<li className="wc-stripe-balance-card__amount wc-stripe-balance-card__amount--empty">
						—
					</li>
				) }
			</ul>
			{ action }
		</div>
	</section>
);

const BalanceBanner = () => {
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );

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
				];
				setData( response );
			} )
			.finally( () => setIsLoading( false ) );
	}, [] );

	return (
		<Card className="wc-stripe-balance-card">
			<CardHeader className="wc-stripe-balance-card__header">
				<h2>{ __( 'Balance', 'woocommerce-gateway-stripe' ) }</h2>
			</CardHeader>
			<CardBody className="wc-stripe-balance-card__body">
				{ isLoading ? (
					<div className="wc-stripe-balance-card__loading">
						<Spinner />
					</div>
				) : (
					<div className="wc-stripe-balance-card__grid">
						{ data?.instant_available && (
							<BalanceSection
								label={ __(
									'Instantly available',
									'woocommerce-gateway-stripe'
								) }
								balances={ data.instant_available }
								action={
									<Button
										variant="secondary"
										href="https://dashboard.stripe.com/payouts/"
										target="_blank"
										rel="noreferrer"
										__next40pxDefaultSize
									>
										{ __(
											'View Stripe dashboard',
											'woocommerce-gateway-stripe'
										) }
									</Button>
								}
							/>
						) }
						<BalanceSection
							label={ __(
								'Available',
								'woocommerce-gateway-stripe'
							) }
							balances={ data?.available ?? [] }
						/>
						<BalanceSection
							label={ __(
								'Pending',
								'woocommerce-gateway-stripe'
							) }
							balances={ data?.pending ?? [] }
						/>
					</div>
				) }
			</CardBody>
		</Card>
	);
};

export default BalanceBanner;
