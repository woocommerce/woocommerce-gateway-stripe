import { __ } from '@wordpress/i18n';
import React from 'react';
import AmazonPayIcon from '../../payment-method-icons/amazon-pay';
import AmazonPayEnableSection from './amazon-pay-enable-section';
import AmazonPaySettingsSection from './amazon-pay-settings-section';
import SettingsSection from 'wcstripe/settings/settings-section';
import SettingsLayout from 'wcstripe/settings/settings-layout';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import SaveSettingsSection from 'wcstripe/settings/save-settings-section';
import '../payment-request-settings/style.scss';

const EnableDescription = () => (
	<>
		<div className="express-checkout-settings__icon">
			<AmazonPayIcon size="medium" />
		</div>
		<p>
			{ __(
				'Decide how buttons for digital wallets Amazon Pay ' +
					'is displayed in your store. Depending on ' +
					'their web browser and their wallet configurations.',
				'woocommerce-gateway-stripe'
			) }
		</p>
	</>
);

const SettingsDescription = () => (
	<>
		<h2>{ __( 'Settings', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'Configure the display of Amazon Pay button on your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
	</>
);

const AmazonPayPage = () => {
	return (
		<SettingsLayout>
			<SettingsSection Description={ EnableDescription }>
				<LoadableSettingsSection numLines={ 30 }>
					<AmazonPayEnableSection />
				</LoadableSettingsSection>
			</SettingsSection>

			<SettingsSection Description={ SettingsDescription }>
				<LoadableSettingsSection numLines={ 30 }>
					<AmazonPaySettingsSection />
				</LoadableSettingsSection>
			</SettingsSection>

			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default AmazonPayPage;
