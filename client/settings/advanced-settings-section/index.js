/* global wc_stripe_settings_params */
import { __ } from '@wordpress/i18n';
import React from 'react';
import { Card } from '@wordpress/components';
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import DebugMode from './debug-mode';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import SinglePaymentElementFeature from 'wcstripe/settings/advanced-settings-section/single-payment-element-feature';

const AdvancedSettingsDescription = () => (
	<>
		<h2>{ __( 'Advanced settings', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'Enable and configure advanced features for your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
	</>
);

const AdvancedSettings = () => {
	const isOcAvailable = wc_stripe_settings_params.is_oc_available; // eslint-disable-line camelcase
	return (
		<SettingsSection Description={ AdvancedSettingsDescription }>
			<LoadableSettingsSection numLines={ 10 }>
				<Card>
					<CardBody>
						<DebugMode />
						{ isOcAvailable && <SinglePaymentElementFeature /> }
					</CardBody>
				</Card>
			</LoadableSettingsSection>
		</SettingsSection>
	);
};

export default AdvancedSettings;
