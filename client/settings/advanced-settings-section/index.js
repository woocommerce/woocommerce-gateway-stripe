/* global wc_stripe_settings_params */
import React from 'react';
import styled from '@emotion/styled';
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import DebugMode from './debug-mode';
import DiagnosticsMode from './diagnostics-mode';
import { Card } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import OptimizedCheckoutFeature from 'wcstripe/settings/advanced-settings-section/optimized-checkout-feature';
import './style.scss';

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

// Vertical stack of cards within the right-hand column. Mirrors the spacing
// other settings sections use between sibling cards.
const CardStack = styled.div`
	display: flex;
	flex-direction: column;
	gap: 16px;
`;

const AdvancedSettings = ( { isOCEnabled, setIsOCEnabled } ) => {
	const isOCAvailable = wc_stripe_settings_params.is_oc_available; // eslint-disable-line camelcase
	return (
		<SettingsSection Description={ AdvancedSettingsDescription }>
			<LoadableSettingsSection numLines={ 10 }>
				<CardStack>
					<Card>
						<CardBody>
							<DebugMode />
						</CardBody>
					</Card>
					<Card>
						<CardBody>
							<DiagnosticsMode />
						</CardBody>
					</Card>
					<Card>
						<CardBody>
							<OptimizedCheckoutFeature
								isOCEnabled={ isOCEnabled }
								isOCAvailable={ isOCAvailable }
								setIsOCEnabled={ setIsOCEnabled }
							/>
						</CardBody>
					</Card>
				</CardStack>
			</LoadableSettingsSection>
		</SettingsSection>
	);
};

export default AdvancedSettings;
