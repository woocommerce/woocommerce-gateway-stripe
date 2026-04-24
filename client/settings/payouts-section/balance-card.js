import React from 'react';
import styled from '@emotion/styled';
import { formatAmount } from './format-currency';
import {
	Card,
	CardHeader,
	CardBody,
	Spinner,
	Notice,
	Button,
	Flex,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useBalance } from 'wcstripe/data/payouts';

const Heading = styled.h2`
	margin: 0;
	font-size: 16px;
	font-weight: 600;
`;

const Columns = styled.div`
	display: flex;
	flex-wrap: wrap;
	gap: 32px;
`;

const Column = styled.div`
	min-width: 160px;

	h3 {
		margin: 0 0 8px 0;
		font-size: 13px;
		color: #50575e;
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	.amount {
		font-size: 20px;
		font-weight: 600;
		display: block;
		margin-bottom: 4px;
	}
`;

const renderAmounts = ( amounts ) => {
	if ( ! amounts?.length ) {
		return <span className="amount">{ formatAmount( 0, 'usd' ) }</span>;
	}

	return amounts.map( ( entry ) => (
		<span key={ entry.currency } className="amount">
			{ formatAmount( entry.amount, entry.currency ) }
		</span>
	) );
};

const BalanceCard = () => {
	const { balance, isLoading, error, refresh } = useBalance();

	return (
		<Card>
			<CardHeader>
				<Flex justify="space-between" align="center">
					<Heading>
						{ __( 'Stripe balance', 'woocommerce-gateway-stripe' ) }
					</Heading>
					<Button
						variant="secondary"
						onClick={ refresh }
						disabled={ isLoading }
					>
						{ __( 'Refresh', 'woocommerce-gateway-stripe' ) }
					</Button>
				</Flex>
			</CardHeader>
			<CardBody>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }

				{ isLoading && ! balance && <Spinner /> }

				{ balance && (
					<Columns>
						<Column>
							<h3>
								{ __(
									'Available',
									'woocommerce-gateway-stripe'
								) }
							</h3>
							{ renderAmounts( balance.available ) }
						</Column>
						<Column>
							<h3>
								{ __(
									'Pending',
									'woocommerce-gateway-stripe'
								) }
							</h3>
							{ renderAmounts( balance.pending ) }
						</Column>
						{ balance.instant_available?.length > 0 && (
							<Column>
								<h3>
									{ __(
										'Instant available',
										'woocommerce-gateway-stripe'
									) }
								</h3>
								{ renderAmounts( balance.instant_available ) }
							</Column>
						) }
					</Columns>
				) }
			</CardBody>
		</Card>
	);
};

export default BalanceCard;
