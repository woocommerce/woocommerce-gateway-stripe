import { __ } from '@wordpress/i18n';
import React from 'react';
import { Card } from '@wordpress/components';
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import DebugMode from './debug-mode';
import ExperimentalFeatures from './experimental-features';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import SinglePaymentElementFeature from 'wcstripe/settings/advanced-settings-section/single-payment-element-feature';
import { useIsUpeEnabled } from 'wcstripe/data';

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
	const [ isUpeEnabled ] = useIsUpeEnabled();
	return (
		<SettingsSection Description={ AdvancedSettingsDescription }>
			<LoadableSettingsSection numLines={ 10 }>
				<Card>
					<CardBody>
						<DebugMode />
						<ExperimentalFeatures />
						{ isUpeEnabled && <SinglePaymentElementFeature /> }
					</CardBody>
				</Card>
			</LoadableSettingsSection>
		</SettingsSection>
	);
};

export default AdvancedSettings;
