/**
 * External dependencies
 */
import React from 'react';
import styled from '@emotion/styled';
import { __ } from '@wordpress/i18n';
import { Button, Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import StripeBanner from '../../payment-method-icons/stripe-banner';

const CardWrapper = styled( Card )`
	max-width: 560px;

	h2 {
		font-size: 16px;
	}

	img {
		width: 100%;
	}
`;

const InformationText = styled.p`
	color: #1e1e1e;
`;

const TermOfServicesText = styled.p`
	color: #757575;
	font-size: 12px;
	font-weight: 400;
	margin: 22px 0px 16px;
`;

const ButtonWrapper = styled.div`
	align-items: center;
	display: flex;
	flex-wrap: wrap;

	> :last-child {
		box-shadow: none;

		&:active,
		&:focus,
		&:hover {
			box-shadow: none !important;
			background: none !important;
		}

		@media ( max-width: 660px ) {
			padding-left: 0;
		}
	}
`;

const ConnectStripeAccount = () => (
	<CardWrapper>
		<StripeBanner />
		<CardBody>
			<h2>Get started with Stripe</h2>
			<InformationText>
				Connect or create a Stripe account to accept payments directly
				onsite, including Payment Request buttons (such as Apple Pay and
				Google Pay), iDeal, SEPA, Sofort, and more international payment
				methods.
			</InformationText>
			<TermOfServicesText>
				By clicking &quot;Create or connect an account&quot;, you agree
				to{ ' ' }
				<a
					href="https://stripe.com/ssa"
					target="_blank"
					rel="noreferrer"
				>
					Stripe’s Terms of service.
				</a>
			</TermOfServicesText>
			<ButtonWrapper>
				<Button
					isPrimary
					// eslint-disable-next-line no-alert, no-undef
					onClick={ () => alert( 'Modal will be implemented later' ) }
				>
					{ __(
						'Create or connect an account',
						'woocommerce-gateway-stripe'
					) }
				</Button>
				<Button
					isSecondary
					// eslint-disable-next-line no-alert, no-undef
					onClick={ () => alert( 'Modal will be implemented later' ) }
				>
					{ __(
						'Enter account keys (advanced)',
						'woocommerce-gateway-stripe'
					) }
				</Button>
			</ButtonWrapper>
		</CardBody>
	</CardWrapper>
);

export default ConnectStripeAccount;
