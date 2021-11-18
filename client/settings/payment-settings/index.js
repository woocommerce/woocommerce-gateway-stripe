import { __ } from '@wordpress/i18n';
import { React, useState } from 'react';
import {
	Card,
	CardHeader,
	DropdownMenu,
	ExternalLink,
} from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import Pill from '../../components/pill';
import AccountStatus from '../account-details';
import PaymentsAndTransactionsSection from '../payments-and-transactions-section';
import AdvancedSettingsSection from '../advanced-settings-section';
import CustomizationOptionsNotice from '../customization-options-notice';
import GeneralSettingsSection from './general-settings-section';
import DisconnectStripeConfirmationModal from './disconnect-stripe-confirmation-modal';
import { AccountKeysModal } from './account-keys-modal';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import './style.scss';
import { useTestMode } from 'wcstripe/data';
import LoadableAccountSection from 'wcstripe/settings/loadable-account-section';
import { useAccount } from 'wcstripe/data/account';

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
			<ExternalLink href="https://woocommerce.com/document/stripe/">
				{ __( 'View Stripe docs', 'woocommerce-gateway-stripe' ) }
			</ExternalLink>
		</p>
		<p>
			<ExternalLink href="https://woocommerce.com/contact-us/">
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
		<ExternalLink href="https://woocommerce.com/document/stripe/#faq">
			{ __(
				'View Frequently Asked Questions',
				'woocommerce-gateway-stripe'
			) }
		</ExternalLink>
	</>
);

// @todo - remove setModalType as prop
const AccountSettingsDropdownMenu = ( {
	setModalType,
	setKeepModalContent,
} ) => {
	// @todo - deconstruct setModalType from useModalType custom hook
	const [ isTestModeEnabled ] = useTestMode();
	const [
		isConfirmationModalVisible,
		setIsConfirmationModalVisible,
	] = useState( false );

	return (
		<>
			<DropdownMenu
				icon={ moreVertical }
				label={ __(
					'Edit details or disconnect account',
					'woocommerce-gateway-stripe'
				) }
				controls={ [
					{
						title: __(
							'Edit account keys',
							'woocommerce-gateway-stripe'
						),
						onClick: () =>
							setModalType( isTestModeEnabled ? 'test' : 'live' ),
					},
					{
						title: __( 'Disconnect', 'woocommerce-gateway-stripe' ),
						onClick: () => setIsConfirmationModalVisible( true ),
					},
				] }
			/>
			{ isConfirmationModalVisible && (
				<DisconnectStripeConfirmationModal
					onClose={ () => setIsConfirmationModalVisible( false ) }
					setKeepModalContent={ setKeepModalContent }
				/>
			) }
		</>
	);
};

// @todo - remove setModalType as prop
const AccountDetailsSection = ( { setModalType, setKeepModalContent } ) => {
	const { data } = useAccount();
	const isTestModeEnabled = Boolean( data.testmode );

	return (
		<Card className="account-details">
			<CardHeader className="account-details__header">
				<div>
					{ data.account?.email && (
						<h4 className="account-details__header">
							{ data.account.email }
						</h4>
					) }
					{ isTestModeEnabled && (
						<Pill>
							{ __( 'Test Mode', 'woocommerce-gateway-stripe' ) }
						</Pill>
					) }
				</div>
				<AccountSettingsDropdownMenu
					setModalType={ setModalType }
					setKeepModalContent={ setKeepModalContent }
				/>
			</CardHeader>
			<CardBody>
				<AccountStatus />
			</CardBody>
		</Card>
	);
};

const PaymentSettingsPanel = () => {
	// @todo - deconstruct modalType and setModalType from useModalType custom hook
	const [ modalType, setModalType ] = useState( '' );
	const [ keepModalContent, setKeepModalContent ] = useState( false );

	const handleModalDismiss = () => {
		setModalType( '' );
	};

	return (
		<>
			{ modalType && (
				<AccountKeysModal
					type={ modalType }
					onClose={ handleModalDismiss }
					setKeepModalContent={ setKeepModalContent }
				/>
			) }
			<SettingsSection Description={ GeneralSettingsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<LoadableAccountSection
						numLines={ 20 }
						keepContent={ keepModalContent }
					>
						<GeneralSettingsSection setModalType={ setModalType } />
					</LoadableAccountSection>
				</LoadableSettingsSection>
				<CustomizationOptionsNotice />
			</SettingsSection>
			<SettingsSection Description={ AccountDetailsDescription }>
				<LoadableAccountSection
					numLines={ 20 }
					keepContent={ keepModalContent }
				>
					<AccountDetailsSection
						setModalType={ setModalType }
						setKeepModalContent={ setKeepModalContent }
					/>
				</LoadableAccountSection>
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
