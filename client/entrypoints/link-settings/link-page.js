import React from 'react';
import LinkIcon from '../../payment-method-icons/link';
import LinkEnableSection from './link-enable-section';
import LinkSettingsSection from './link-settings-section';
import { __ } from '@wordpress/i18n';
import SettingsSection from 'wcstripe/settings/settings-section';
import SettingsLayout from 'wcstripe/settings/settings-layout';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import SaveSettingsSection from 'wcstripe/settings/save-settings-section';
import '../express-checkout-settings/style.scss';

const EnableDescription = () => (
	<>
		<div className="express-checkout-settings__icon">
			<LinkIcon size="medium" />
		</div>
		<p>
			{ __(
				'Decide how the Link by Stripe button ' +
					'is displayed in your store.',
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
				'Configure the display of Link by Stripe button on your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
	</>
);

const LinkPage = () => {
	return (
		<SettingsLayout>
			<SettingsSection Description={ EnableDescription }>
				<LoadableSettingsSection numLines={ 30 }>
					<LinkEnableSection />
				</LoadableSettingsSection>
			</SettingsSection>

			<SettingsSection Description={ SettingsDescription }>
				<LoadableSettingsSection numLines={ 30 }>
					<LinkSettingsSection />
				</LoadableSettingsSection>
			</SettingsSection>

			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default LinkPage;
