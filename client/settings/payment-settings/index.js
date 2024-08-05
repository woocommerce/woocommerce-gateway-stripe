import { __ } from '@wordpress/i18n';
import { React, useContext, useState } from 'react';
import { ExternalLink } from '@wordpress/components';
import SettingsSection from '../settings-section';
import PaymentsAndTransactionsSection from '../payments-and-transactions-section';
import AdvancedSettingsSection from '../advanced-settings-section';
import CustomizationOptionsNotice from '../customization-options-notice';
import AccountDetailsSection from './account-details-section';
import GeneralSettingsSection from './general-settings-section';
import { AccountKeysModal } from './account-keys-modal';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import './style.scss';
import LoadableAccountSection from 'wcstripe/settings/loadable-account-section';
import PromotionalBannerSection from 'wcstripe/settings/payment-settings/promotional-banner-section';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
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
				{ __(
					'View Stripe plugin docs',
					'woocommerce-gateway-stripe'
				) }
			</ExternalLink>
		</p>
		<p>
			<ExternalLink href="https://woocommerce.com/my-account/contact-support/?select=18627">
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
	</>
);

const PaymentSettingsPanel = () => {
	// @todo - deconstruct modalType and setModalType from useModalType custom hook
	const [ modalType, setModalType ] = useState( '' );
	const [ keepModalContent, setKeepModalContent ] = useState( false );
	const [ showPromotionalBanner, setShowPromotionalBanner ] = useState(
		true
	);
	const { isUpeEnabled, setIsUpeEnabled } = useContext( UpeToggleContext );
	const { data } = useAccount();
	const isTestModeEnabled = Boolean( data.testmode );
	const oauthConnected = isTestModeEnabled
		? data?.oauth_connections?.test
		: data?.oauth_connections?.live;

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
			{ showPromotionalBanner && (
				<SettingsSection>
					<LoadableSettingsSection numLines={ 20 }>
						<LoadableAccountSection
							numLines={ 20 }
							keepContent={ keepModalContent }
						>
							<PromotionalBannerSection
								setShowPromotionalBanner={
									setShowPromotionalBanner
								}
								isUpeEnabled={ isUpeEnabled }
								setIsUpeEnabled={ setIsUpeEnabled }
								isConnectedViaOAuth={ oauthConnected }
								setModalType={ setModalType }
							/>
						</LoadableAccountSection>
					</LoadableSettingsSection>
				</SettingsSection>
			) }
			<SettingsSection Description={ GeneralSettingsDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<LoadableAccountSection
						numLines={ 20 }
						keepContent={ keepModalContent }
					>
						<GeneralSettingsSection
							setKeepModalContent={ setKeepModalContent }
						/>
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
