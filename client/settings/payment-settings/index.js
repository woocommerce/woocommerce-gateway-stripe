/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';
import CardBody from '../card-body';

const GeneralSettingsDescription = () => (
	<>
		<h2>{ __( 'General', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'Enable or disable Stripe on your store, enter ' +
					'activation keys, and turn on test mode ' +
					'to simulate transactions.',
				'woocommerce-gateway-stripe'
			) }
		</p>
	</>
);

const AccountDetailsDescription = () => (
	<>
		<h2>{ __( 'General', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'Connect the plugin to your Stripe account, view ' +
					'account overview, and edit business details. ',
				'woocommerce-gateway-stripe'
			) }
		</p>
	</>
);

const GeneralSettingsCard = () => {
	return (
		<Card>
			<CardBody>The general settings card goes here.</CardBody>
		</Card>
	);
};

const AccountDetailsCard = () => {
	return (
		<Card>
			<CardBody>The account details card goes here.</CardBody>
		</Card>
	);
};

const PaymentSettingsPanel = () => {
	return (
		<>
			<SettingsSection Description={ GeneralSettingsDescription }>
				<GeneralSettingsCard />
			</SettingsSection>
			<SettingsSection Description={ AccountDetailsDescription }>
				<AccountDetailsCard />
			</SettingsSection>
		</>
	);
};

export default PaymentSettingsPanel;
