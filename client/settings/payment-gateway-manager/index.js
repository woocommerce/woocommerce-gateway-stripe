import { __ } from '@wordpress/i18n';
import React from 'react';
import { ExternalLink } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import SettingsLayout from '../settings-layout';
import SettingsSection from '../settings-section';
import LoadableSettingsSection from '../loadable-settings-section';
import PaymentGatewaySection from '../payment-gateway-section';
import UpeOptInBanner from '../general-settings-section/upe-opt-in-banner';
import SavePaymentGatewaySection from '../save-payment-gateway-section';
import { gatewaysInfo } from './constants';

const GatewayDescription = () => {
	const { section } = getQuery();
	const info = gatewaysInfo[ section ];
	return (
		<>
			<h2>{ info.title }</h2>
			<p>{ info.geography }</p>
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
				<ExternalLink href={ info.guide } target="_blank">
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
			<SettingsSection>
				<UpeOptInBanner />
			</SettingsSection>
			<SavePaymentGatewaySection />
		</SettingsLayout>
	);
};

export default PaymentGatewayManager;
