import { __ } from '@wordpress/i18n';
import React from 'react';
import { ExternalLink } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import SettingsLayout from '../settings-layout';
import SettingsSection from '../settings-section';
import LoadableSettingsSection from '../loadable-settings-section';
import PaymentGatewaySection from '../payment-gateway-section';
import UpeOptInBanner from '../general-settings-section/upe-opt-in-banner';
import SaveSettingsSection from '../save-settings-section';
import { gatewaysDescriptions } from './constants';

const GatewayDescription = () => {
	const { section } = getQuery();
	const description = gatewaysDescriptions[ section ];
	return (
		<>
			<h2>{ description.title }</h2>
			<p>{ description.geography }</p>
			<p>
				<ExternalLink
					href="https://dashboard.stripe.com/account/payments/settings"
					target="_blank"
				>
					{ __(
						'Activate in your Stripe Dashboard',
						'woocommerce-gateway-stripe'
					) }
				</ExternalLink>
			</p>
			<p>
				<ExternalLink href={ description.guide } target="_blank">
					{ __(
						'Payment Method Guide',
						'woocommerce-gateway-stripe'
					) }
				</ExternalLink>
			</p>
		</>
	);
};

const PaymentGatewayManager = () => {
	return (
		<SettingsLayout>
			<SettingsSection Description={ GatewayDescription }>
				<LoadableSettingsSection numLines={ 34 }>
					<PaymentGatewaySection />
				</LoadableSettingsSection>
			</SettingsSection>
			<UpeOptInBanner />
			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default PaymentGatewayManager;
