/* global wc_stripe_settings_params */
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { React } from 'react';
import interpolateComponents from 'interpolate-components';
import { ExternalLink } from '@wordpress/components';
import CardBody from 'wcstripe/settings/card-body';
import illustration from 'wcstripe/settings/payment-settings/promotional-banner/illustrations/default.svg';
import { recordEvent } from 'wcstripe/tracking';
import {
	BannerIllustration,
	ButtonsRow,
	CardColumn,
	CardInner,
	DismissButton,
	MainCTALink,
	NewPill,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';

export const NewCheckoutExperienceAPMsBanner = ( {
	setShowPromotionalBanner,
	setIsUpeEnabled,
} ) => {
	const { createErrorNotice, createSuccessNotice } = useDispatch(
		'core/notices'
	);

	const handleBannerDismiss = () => {
		setShowPromotionalBanner( false );
	};

	const handleButtonClick = () => {
		const callback = async () => {
			try {
				await setIsUpeEnabled( true );

				recordEvent( 'wcstripe_legacy_experience_disabled', {
					source: 'payment-methods-tab-notice',
				} );

				createSuccessNotice(
					__(
						'New checkout experience enabled',
						'woocommerce-gateway-stripe'
					)
				);

				window.location.reload();
			} catch ( err ) {
				createErrorNotice(
					__(
						'There was an error. Please reload the page and try again.',
						'woocommerce-gateway-stripe'
					)
				);
			}
		};

		// creating a separate callback so that the UI isn't blocked by the async call.
		callback();
	};

	let newCheckoutExperienceAPMsBannerDescription = '';
	// eslint-disable-next-line camelcase
	if ( wc_stripe_settings_params.are_apms_deprecated ) {
		newCheckoutExperienceAPMsBannerDescription = __(
			'Stripe ended support for non-card payment methods in the {{StripeLegacyLink}}legacy checkout on October 29, 2024{{/StripeLegacyLink}}. To continue accepting non-card payments, you must enable the new checkout experience or remove non-card payment methods from your checkout to avoid payment disruptions.',
			'woocommerce-gateway-stripe'
		);
	} else {
		newCheckoutExperienceAPMsBannerDescription = __(
			'Stripe will end support for non-card payment methods in the {{StripeLegacyLink}}legacy checkout on October 29, 2024{{/StripeLegacyLink}}. To continue accepting non-card payments, you must enable the new checkout experience or remove non-card payment methods from your checkout to avoid payment disruptions.',
			'woocommerce-gateway-stripe'
		);
	}

	return (
		<CardBody data-testid="new-checkout-apms-banner">
			<CardInner>
				<CardColumn>
					<NewPill>
						{ __( 'New', 'woocommerce-gateway-stripe' ) }
					</NewPill>
					<h4>
						{ __(
							'Enable the new Stripe checkout to continue accepting non-card payments',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<p>
						{ interpolateComponents( {
							mixedString: newCheckoutExperienceAPMsBannerDescription,
							components: {
								StripeLegacyLink: (
									<ExternalLink href="https://support.stripe.com/topics/shutdown-of-the-legacy-sources-api-for-non-card-payment-methods" />
								),
							},
						} ) }
					</p>
				</CardColumn>
				<CardColumn>
					<BannerIllustration
						src={ illustration }
						alt={ __(
							'New Checkout',
							'woocommerce-gateway-stripe'
						) }
					/>
				</CardColumn>
			</CardInner>
			<ButtonsRow>
				<MainCTALink variant="secondary" onClick={ handleButtonClick }>
					{ __(
						'Enable the new checkout',
						'woocommerce-gateway-stripe'
					) }
				</MainCTALink>
				<DismissButton
					variant="secondary"
					onClick={ handleBannerDismiss }
					data-testid="dismiss"
				>
					{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
				</DismissButton>
			</ButtonsRow>
		</CardBody>
	);
};
