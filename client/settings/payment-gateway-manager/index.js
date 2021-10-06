import { __ } from '@wordpress/i18n';
import React from 'react';
import { ExternalLink } from '@wordpress/components';
import SettingsLayout from '../settings-layout';
import SettingsSection from '../settings-section';
import UpeOptInBanner from '../general-settings-section/upe-opt-in-banner';
import SaveSettingsSection from '../save-settings-section';

const GatewayDescription = () => {
	return (
		<>
			<h2>Gateway</h2>
			<p>
				{ __( 'Customer geography: .', 'woocommerce-gateway-stripe' ) }
			</p>
			<p>
				<ExternalLink href="?TODO">
					{ __(
						'Activate in your Stripe Dashboard',
						'woocommerce-gateway-stripe'
					) }
				</ExternalLink>
			</p>
			<p>
				<ExternalLink href="?TODO">
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
			<SettingsSection Description={ GatewayDescription } />
			<UpeOptInBanner />
			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default PaymentGatewayManager;
