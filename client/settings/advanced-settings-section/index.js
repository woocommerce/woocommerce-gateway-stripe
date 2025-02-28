import { __ } from '@wordpress/i18n';
import React from 'react';
import { Card } from '@wordpress/components';
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import DebugMode from './debug-mode';
import ExperimentalFeatures from './experimental-features';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';

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
	return (
		<SettingsSection Description={ AdvancedSettingsDescription }>
			<LoadableSettingsSection numLines={ 10 }>
				<Card>
					<CardBody>
						<DebugMode />
						<ExperimentalFeatures />
					</CardBody>
				</Card>
			</LoadableSettingsSection>
		</SettingsSection>
	);
};

export default AdvancedSettings;
