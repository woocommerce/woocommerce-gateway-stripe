/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';
import { __ } from '@wordpress/i18n';
import styled from '@emotion/styled';
import { Button, Card, ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Pill from '../../components/pill';
import AllPaymentMethodsIcon from '../../payment-method-icons/all-payment-methods';

const BannerWrapper = styled( Card )`
	display: flex;
	flex-flow: row;
	justify-content: center;
	margin: 12px 0;
	max-width: 680px;
`;

const InformationWrapper = styled.div`
	flex: 0 1 auto;
	padding: 24px;
`;

const Actions = styled.div`
	padding-top: 11px;
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-items: center;
`;

const ImageWrapper = styled.div`
	background: #f7f9fc;
	display: none;
	flex: 0 0 33%;

	img {
		padding: 24px;
	}

	@media ( min-width: 660px ) {
		display: flex;
	}
`;

const UPEOptInBanner = () => (
	<BannerWrapper>
		<InformationWrapper>
			<Pill>{ __( 'Early access', 'woocommerce-gateway-stripe' ) }</Pill>
			<h3>
				{ __(
					'Enable the new Stripe payment management experience',
					'woocommerce-gateway-stripe'
				) }
			</h3>
			<p>
				{ __(
					/* eslint-disable-next-line max-len */
					'Spend less time managing giropay and other payment methods in an improved settings and checkout experience, now available to select merchants.',
					'woocommerce-gateway-stripe'
				) }
			</p>
			<Actions>
				<span>
					<Button
						isPrimary
						href="?page=wc_stripe-onboarding_wizard"
						target="_blank"
					>
						{ __(
							'Enable in your store',
							'woocommerce-gateway-stripe'
						) }
					</Button>
				</span>
				<ExternalLink href="?TODO">
					{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
				</ExternalLink>
			</Actions>
		</InformationWrapper>
		<ImageWrapper>
			<AllPaymentMethodsIcon />
		</ImageWrapper>
	</BannerWrapper>
);

const bannerContainer = document.getElementById(
	'wc-stripe-upe-opt-in-banner'
);

if ( bannerContainer ) {
	ReactDOM.render( <UPEOptInBanner />, bannerContainer );
}

export default UPEOptInBanner;
