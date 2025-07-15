import { __ } from '@wordpress/i18n';
import { React } from 'react';
import styled from '@emotion/styled';
import { ExternalLink } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import CardBody from 'wcstripe/settings/card-body';
import illustration from 'wcstripe/settings/payment-settings/promotional-banner/illustrations/oc.svg';
import {
	BannerIllustration,
	ButtonsRow,
	CardColumn,
	CardInner,
	DismissButton,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';

const BannerIllustrationBNPL = styled( BannerIllustration )`
	@media ( min-width: 600px ) {
		margin: 0 0 -40px 24px;
	}
`;

const ButtonsRowBNPL = styled( ButtonsRow )`
	@media ( min-width: 600px ) {
		margin-bottom: 0.7em;
	}
`;

const ColumnIllustration = styled( CardColumn )`
	@media ( max-width: 599px ) {
		text-align: center;
	}
`;

const TitleBNPL = styled.h4`
	margin-top: 0.6em !important;
`;

export const OCPromotionBanner = ( { setShowPromotionalBanner } ) => {
	const handleBannerDismiss = () => {
		apiFetch( {
			path: '/wc/v3/wc_stripe/settings/notice',
			method: 'POST',
			data: { wc_stripe_show_oc_promotion_banner: 'no' },
		} ).finally( () => {
			setShowPromotionalBanner( false );
		} );
		window.location.reload();
	};

	return (
		<CardBody>
			<CardInner>
				<CardColumn>
					<TitleBNPL>
						{ __(
							"Increase conversions with Stripe's Optimized Checkout Suite",
							'woocommerce-gateway-stripe'
						) }
					</TitleBNPL>
					<p>
						{ __(
							'Optimize your checkout for more sales by automatically displaying the most relevant payment methods for each customer.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				</CardColumn>
				<ColumnIllustration>
					<BannerIllustrationBNPL
						src={ illustration }
						alt={ __(
							'Try the Optimized Checkout Suite',
							'woocommerce-gateway-stripe'
						) }
					/>
				</ColumnIllustration>
			</CardInner>
			<ButtonsRowBNPL>
				<ExternalLink href="https://woocommerce.com/document/stripe/admin-experience/optimized-checkout-suite/">
					{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
				</ExternalLink>
				<DismissButton
					variant="secondary"
					onClick={ handleBannerDismiss }
					data-testid="dismiss"
				>
					{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
				</DismissButton>
			</ButtonsRowBNPL>
		</CardBody>
	);
};
