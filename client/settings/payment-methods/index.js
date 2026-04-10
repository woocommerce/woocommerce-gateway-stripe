/* global wc_stripe_settings_params */
import React from 'react';
import SettingsSection from '../settings-section';
import PaymentRequestSection from '../payment-request-section';
import GeneralSettingsSection from '../general-settings-section';
import LoadableSettingsSection from '../loadable-settings-section';
import DisplayOrderCustomizationNotice from '../display-order-customization-notice';
import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import AmazonPayTaxesBillingAddressNotice from 'wcstripe/components/amazon-pay-taxes-billing-address-notice';
import PromotionalBanner from 'wcstripe/settings/payment-settings/promotional-banner';
import OptimizedCheckoutNotice from 'wcstripe/settings/optimized-checkout-notice';

const PaymentMethodsDescription = () => {
	return (
		<>
			<h2>
				{ __(
					'Payments accepted on checkout',
					'woocommerce-gateway-stripe'
				) }
			</h2>

			<p>
				{ __(
					'Select payments available to customers at checkout. ' +
						'Based on their device type, location, and purchase history, ' +
						'your customers will only see the most relevant payment methods.',
					'woocommerce-gateway-stripe'
				) }
			</p>
		</>
	);
};

const PaymentRequestDescription = () => (
	<>
		<h2>{ __( 'Express checkouts', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'Let your customers use their favorite express payment methods and digital wallets for faster, more secure checkouts across different parts of your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<ExternalLink href="https://woocommerce.com/document/stripe/customer-experience/express-checkouts/">
			{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
		</ExternalLink>
	</>
);

const AmazonPayTaxesBasedOnBillingAddressSection = () => {
	const areTaxesBasedOnBillingAddress =
		!! wc_stripe_settings_params?.taxes_based_on_billing; // eslint-disable-line camelcase

	return (
		<SettingsSection>
			<AmazonPayTaxesBillingAddressNotice
				areTaxesBasedOnBillingAddress={ areTaxesBasedOnBillingAddress }
			/>
		</SettingsSection>
	);
};

const PaymentMethodsPanel = ( {
	onSaveChanges,
	setShowPromotionalBanner,
	showPromotionalBanner,
	promotionalBannerType,
	isOCEnabled,
	setIsOCEnabled,
} ) => {
	return (
		<>
			<AmazonPayTaxesBasedOnBillingAddressSection />
			{ showPromotionalBanner && (
				<SettingsSection>
					<PromotionalBanner
						setShowPromotionalBanner={ setShowPromotionalBanner }
						setIsOCEnabled={ setIsOCEnabled }
						promotionalBannerType={ promotionalBannerType }
					/>
				</SettingsSection>
			) }
			<SettingsSection Description={ PaymentMethodsDescription }>
				<DisplayOrderCustomizationNotice isOCEnabled={ isOCEnabled } />
				<OptimizedCheckoutNotice isOCEnabled={ isOCEnabled } />
				<GeneralSettingsSection onSaveChanges={ onSaveChanges } />
			</SettingsSection>
			<SettingsSection Description={ PaymentRequestDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<PaymentRequestSection />
				</LoadableSettingsSection>
			</SettingsSection>
		</>
	);
};

export default PaymentMethodsPanel;
