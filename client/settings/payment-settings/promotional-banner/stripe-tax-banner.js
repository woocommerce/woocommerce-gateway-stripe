import { React } from 'react';
import styled from '@emotion/styled';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import CardBody from 'wcstripe/settings/card-body';
import illustration from 'wcstripe/settings/payment-settings/promotional-banner/illustrations/oc.svg';
import {
	BannerIllustration,
	ButtonsRow,
	CardColumn,
	CardInner,
	DismissButton,
	MainCTALink,
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
	font-weight: 500;
`;

export const StripeTaxBanner = ( { setShowPromotionalBanner } ) => {
	const handleBannerDismiss = () => {
		apiFetch( {
			path: '/wc/v3/wc_stripe/settings/notice',
			method: 'POST',
			data: { wc_stripe_show_stripe_tax_banner: 'no' },
		} ).finally( () => {
			setShowPromotionalBanner( false );
		} );
	};

	const handleButtonClick = () => {
		// TODO: track clicks
		window.location.assign(
			'https://woocommerce.com/products/stripe-tax/'
		);
	};

	return (
		<CardBody>
			<CardInner>
				<CardColumn>
					<TitleBNPL>
						{ __(
							'Automate tax compliance with Stripe Tax',
							'woocommerce-gateway-stripe'
						) }
					</TitleBNPL>
					<p>
						{ __( '', 'woocommerce-gateway-stripe' ) }
						{ interpolateComponents( {
							mixedString: __(
								'Automatically calculate and collect sales tax, VAT, and GST wherever you sell. {{docLink}}Learn more{{/docLink}} about how Stripe Tax keeps you compliant.',
								'woocommerce-gateway-stripe'
							),
							components: {
								docLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a
										target="_blank"
										rel="noreferrer"
										href="https://stripe.com/tax"
									/>
								),
							},
						} ) }
					</p>
				</CardColumn>
				<ColumnIllustration>
					<BannerIllustrationBNPL
						src={ illustration }
						alt={ __(
							'Get Stripe Tax',
							'woocommerce-gateway-stripe'
						) }
					/>
				</ColumnIllustration>
			</CardInner>
			<ButtonsRowBNPL>
				<MainCTALink variant="secondary" onClick={ handleButtonClick }>
					{ __( 'Get Stripe Tax', 'woocommerce-gateway-stripe' ) }
				</MainCTALink>
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
