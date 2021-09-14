/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	DropdownMenu,
	ExternalLink,
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import './style.scss';
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import AccountStatus from '../account-details';
import PaymentsAndTransactionsSection from '../payments-and-transactions-section';
import AdvancedSettingsSection from '../advanced-settings-section';
import CustomizationOptionsNotice from '../customization-options-notice';

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
		<h2>{ __( 'Account Details', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'View account overview and edit business details.',
				'woocommerce-gateway-stripe'
			) }
		</p>
	</>
);

const PaymentsAndTransactionsDescription = () => (
	<>
		<h2>
			{ __( 'Payments & transactions', 'woocommerce-gateway-stripe' ) }
		</h2>
		<p>
			{ __(
				'Configure optional payment settings and transaction details.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<ExternalLink href="?TODO">
			{ __(
				'View Frequently Asked Questions',
				'woocommerce-gateway-stripe'
			) }
		</ExternalLink>
	</>
);

const GeneralSettingsSection = () => {
	return (
		<Card>
			<CardBody>The general settings card goes here.</CardBody>
		</Card>
	);
};

const AccountSettingsDropdownMenu = () => {
	return (
		<DropdownMenu
			icon={ moreVertical }
			label={ __(
				'Edit details or disconnect account',
				'woocommerce-gateway-stripe'
			) }
			controls={ [
				{
					title: __( 'Edit Details', 'woocommerce-gateway-stripe' ),
					onClick: () => console.log( 'Edit my details' ),
				},
				{
					title: 'Disconnect',
					onClick: () => console.log( 'Disconnecting' ),
				},
			] }
		/>
	);
};

const accountStatusMock = {
	paymentsEnabled: true,
	depositsEnabled: true,
	email: 'hello@johndoe.com',
	accountLink: 'https://stripe.com/support',
};

const AccountDetailsSection = () => {
	return (
		<Card className="account-details">
			<CardHeader className="account-details__header">
				<h4 className="account-details__header">
					{ accountStatusMock.email }
				</h4>
				<AccountSettingsDropdownMenu />
			</CardHeader>
			<CardBody>
				<AccountStatus accountStatus={ accountStatusMock } />
			</CardBody>
		</Card>
	);
};

const PaymentSettingsPanel = () => {
	return (
		<>
			<SettingsSection Description={ GeneralSettingsDescription }>
				<GeneralSettingsSection />
				<CustomizationOptionsNotice />
			</SettingsSection>
			<SettingsSection Description={ AccountDetailsDescription }>
				<AccountDetailsSection />
			</SettingsSection>
			<SettingsSection Description={ PaymentsAndTransactionsDescription }>
				<PaymentsAndTransactionsSection />
			</SettingsSection>
			<AdvancedSettingsSection />
		</>
	);
};

export default PaymentSettingsPanel;
