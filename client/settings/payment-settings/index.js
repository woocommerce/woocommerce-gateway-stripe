import { __ } from '@wordpress/i18n';
import { React } from 'react';
import {
	Card,
	CardHeader,
	DropdownMenu,
	ExternalLink,
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import AccountStatus from '../account-details';
import PaymentsAndTransactionsSection from '../payments-and-transactions-section';
import AdvancedSettingsSection from '../advanced-settings-section';
import CustomizationOptionsNotice from '../customization-options-notice';
import GeneralSettingsSection from './general-settings-section';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import './style.scss';

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
		<p>
			<ExternalLink href="?TODO">
				{ __( 'View Stripe docs', 'woocommerce-gateway-stripe' ) }
			</ExternalLink>
		</p>
		<p>
			<ExternalLink href="?TODO">
				{ __( 'Get support', 'woocommerce-gateway-stripe' ) }
			</ExternalLink>
		</p>
	</>
);

const AccountDetailsDescription = () => (
	<>
		<h2>{ __( 'Account details', 'woocommerce-gateway-stripe' ) }</h2>
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
					// eslint-disable-next-line no-console
					onClick: () => console.log( 'Edit my details' ),
				},
				{
					title: 'Disconnect',
					// eslint-disable-next-line no-console
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
				<LoadableSettingsSection numLines={ 20 }>
					<GeneralSettingsSection />
				</LoadableSettingsSection>
				<CustomizationOptionsNotice />
			</SettingsSection>
			<SettingsSection Description={ AccountDetailsDescription }>
				<AccountDetailsSection />
			</SettingsSection>
			<SettingsSection Description={ PaymentsAndTransactionsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<PaymentsAndTransactionsSection />
				</LoadableSettingsSection>
			</SettingsSection>
			<AdvancedSettingsSection />
		</>
	);
};

export default PaymentSettingsPanel;
