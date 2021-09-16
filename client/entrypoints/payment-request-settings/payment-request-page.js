/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import PaymentRequestsSection from './payment-request-section';
import SettingsSection from 'wcstripe/settings/settings-section';
import SettingsLayout from 'wcstripe/settings/settings-layout';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import SaveSettingsSection from 'wcstripe/settings/save-settings-section';

const Description = () => (
	<>
		<h2>
			{ __( 'Google Pay / Apple Pay', 'woocommerce-gateway-stripe' ) }
		</h2>
		<p>
			{ __(
				'Decide how buttons for digital wallets Apple Pay and ' +
					'Google Pay are displayed in your store. Depending on ' +
					'their web browser and their wallet configurations, ' +
					'your customers will see either Apple Pay or Google Pay, ' +
					'but not both.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<p>
			<ExternalLink href="https://developer.apple.com/design/human-interface-guidelines/apple-pay/overview/introduction/">
				{ __(
					'View Apple Pay Guidelines',
					'woocommerce-gateway-stripe'
				) }
			</ExternalLink>
		</p>
		<p>
			<ExternalLink href="https://developers.google.com/pay/api/web/guides/brand-guidelines">
				{ __(
					'View Google Pay Guidelines',
					'woocommerce-gateway-stripe'
				) }
			</ExternalLink>
		</p>
	</>
);

const PaymentRequestsPage = () => {
	return (
		<SettingsLayout>
			<SettingsSection Description={ Description }>
				<LoadableSettingsSection numLines={ 30 }>
					<PaymentRequestsSection />
				</LoadableSettingsSection>
			</SettingsSection>

			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default PaymentRequestsPage;
