/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import SettingsLayout from '../settings-layout';
import SettingsSection from '../settings-section';
import PaymentRequestSection from '../payment-request-section';
import GeneralSettingsSection from '../general-settings-section';
import SaveSettingsSection from '../save-settings-section';

const PaymentMethodsDescription = () => (
	<>
		<h2>
			{ __(
				'Payments accepted on checkout',
				'woocommerce-gateway-stripe'
			) }
		</h2>
		<p>
			{ __(
				'Add and edit payments available to customers at checkout. ' +
					'Based on their device type, location, your customers will ' +
					'only see the most relevant payment methods.',
				'woocommerce-gateway-stripe'
			) }
		</p>
	</>
);

const PaymentRequestDescription = () => (
	<>
		<h2>{ __( 'Express checkouts', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'Let your customers use their favorite express payment methods and digital wallets for faster, more secure checkouts across different parts of your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
	</>
);

const SettingsManager = () => {
	return (
		<SettingsLayout>
			<SettingsSection Description={ PaymentMethodsDescription }>
				<GeneralSettingsSection />
			</SettingsSection>
			<SettingsSection Description={ PaymentRequestDescription }>
				<PaymentRequestSection />
			</SettingsSection>
			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default SettingsManager;
