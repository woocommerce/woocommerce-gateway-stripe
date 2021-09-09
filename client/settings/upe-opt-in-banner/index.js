/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import styled from '@emotion/styled';
import { Button, Card, ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Pill from '../../components/pill';

const StyledPill = styled( Pill )`
	border-color: #007cba;
	color: #007cba;
`;

const BannerWrapper = styled( Card )`
	display: flex;
	flex-flow: row;
	justify-content: center;
`;

const InformationWrapper = styled.div`
	display: flex;
	flex-direction: column;
	justify-content: center;
	flex: 0 1 auto;
	padding: 45px 24px;

	h3 {
		margin-bottom: 8px;
	}

	p {
		margin-top: 0;
		margin-bottom: 24px;
	}
`;

const Actions = styled.div`
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-items: center;
`;

const ImageWrapper = styled.div`
	background: #f7f9fc;
	display: none;
	flex: 0 0 45%;

	img {
		max-width: 100%;
		width: auto;
		height: auto;
	}

	@media ( min-width: 660px ) {
		display: flex;
		justify-content: center;
		align-items: center;
	}
`;

const UpeOptInBanner = ( { title, description, Image, ...props } ) => (
	<BannerWrapper { ...props }>
		<InformationWrapper>
			<StyledPill>
				{ __( 'Early access', 'woocommerce-gateway-stripe' ) }
			</StyledPill>
			<h3>{ title }</h3>
			<p>{ description }</p>
			<Actions>
				<Button isSecondary href="?page=wc_stripe-onboarding_wizard">
					{ __(
						'Enable in your store',
						'woocommerce-gateway-stripe'
					) }
				</Button>
				<ExternalLink href="?TODO">
					{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
				</ExternalLink>
			</Actions>
		</InformationWrapper>
		<ImageWrapper>
			<Image />
		</ImageWrapper>
	</BannerWrapper>
);

export default UpeOptInBanner;
