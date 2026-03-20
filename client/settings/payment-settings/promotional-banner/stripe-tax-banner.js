import { React } from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import styled from '@emotion/styled';
import { external } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import CardBody from 'wcstripe/settings/card-body';
import illustration from 'wcstripe/settings/payment-settings/promotional-banner/illustrations/stripe-tax.svg';
import {
	BannerIllustration,
	ButtonsRow,
	CardColumn,
	CardInner,
	DismissButton,
	MainCTALink,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';
import { recordEvent } from 'wcstripe/tracking';

const BannerIllustrationStripeTax = styled( BannerIllustration )`
	width: 80px;
	margin: 10px;

	@media ( min-width: 600px ) {
		margin-bottom: -30px;
	}
`;

const ButtonsRowStripeTax = styled( ButtonsRow )`
	@media ( min-width: 600px ) {
		margin-bottom: 0.7em;
	}
`;

const ColumnIllustration = styled( CardColumn )`
	@media ( max-width: 599px ) {
		text-align: center;
	}
`;

const TitleStripeTax = styled.h4`
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
		recordEvent( 'wcstripe_stripe_tax_banner_button_click', {} );
	};

	return (
		<CardBody>
			<CardInner>
				<CardColumn>
					<TitleStripeTax>
						{ __(
							'Automate tax compliance with Stripe Tax',
							'woocommerce-gateway-stripe'
						) }
					</TitleStripeTax>
					<p>
						{ interpolateComponents( {
							mixedString: __(
								'Automatically calculate and collect sales tax, value-added tax (VAT), and goods and services tax (GST) wherever you sell. {{docLink}}Learn more{{/docLink}} about how Stripe Tax keeps you compliant.',
								'woocommerce-gateway-stripe'
							),
							components: {
								docLink: (
									<ExternalLink href="https://stripe.com/tax" />
								),
							},
						} ) }
					</p>
				</CardColumn>
				<ColumnIllustration>
					<BannerIllustrationStripeTax
						src={ illustration }
						alt={ __(
							'Get Stripe Tax',
							'woocommerce-gateway-stripe'
						) }
					/>
				</ColumnIllustration>
			</CardInner>
			<ButtonsRowStripeTax>
				<MainCTALink
					variant="secondary"
					onClick={ handleButtonClick }
					href="https://woocommerce.com/products/stripe-tax/"
					target="_blank"
					icon={ external }
					iconPosition="right"
				>
					{ __( 'Get Stripe Tax', 'woocommerce-gateway-stripe' ) }
				</MainCTALink>
				<DismissButton
					variant="secondary"
					onClick={ handleBannerDismiss }
					data-testid="dismiss"
				>
					{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
				</DismissButton>
			</ButtonsRowStripeTax>
		</CardBody>
	);
};
