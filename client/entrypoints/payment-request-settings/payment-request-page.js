import { __ } from '@wordpress/i18n';
import React from 'react';
import PaymentRequestIcon from '../../payment-method-icons/payment-request';
import PaymentRequestsEnableSection from './payment-request-enable-section';
import PaymentRequestsSettingsSection from './payment-request-settings-section';
import SettingsSection from 'wcstripe/settings/settings-section';
import SettingsLayout from 'wcstripe/settings/settings-layout';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import SaveSettingsSection from 'wcstripe/settings/save-settings-section';
import './style.scss';

const EnableDescription = () => (
	<>
		<div className="express-checkout-settings__icon">
			<PaymentRequestIcon size="medium" />
		</div>
		<p>
			{ __(
				'Allow your customers to collect payments via WooPay.',
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
				'Configure the display of Apple Pay and Google Pay buttons on your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
	</>
);

const PaymentRequestsPage = () => {
	return (
		<SettingsLayout>
			<SettingsSection Description={ EnableDescription }>
				<LoadableSettingsSection numLines={ 30 }>
					<PaymentRequestsEnableSection />
				</LoadableSettingsSection>
			</SettingsSection>

			<SettingsSection Description={ SettingsDescription }>
				<LoadableSettingsSection numLines={ 30 }>
					<PaymentRequestsSettingsSection />
				</LoadableSettingsSection>
			</SettingsSection>

			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default PaymentRequestsPage;
