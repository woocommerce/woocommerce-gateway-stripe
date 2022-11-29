import { __ } from '@wordpress/i18n';
import React, { useContext } from 'react';
import { ExternalLink } from '@wordpress/components';
import SettingsSection from '../settings-section';
import PaymentRequestSection from '../payment-request-section';
import GeneralSettingsSection from '../general-settings-section';
import LoadableSettingsSection from '../loadable-settings-section';
import UpeToggleContext from '../upe-toggle/context';
import CustomizationOptionsNotice from '../customization-options-notice';

const PaymentMethodsDescription = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	return (
		<>
			<h2>
				{ __(
					'Payments accepted on checkout',
					'woocommerce-gateway-stripe'
				) }
			</h2>

			{ isUpeEnabled && (
				<p>
					{ __(
						'Select payments available to customers at checkout. ' +
							'Based on their device type, location, and purchase history, ' +
							'your customers will only see the most relevant payment methods.',
						'woocommerce-gateway-stripe'
					) }
				</p>
			) }
		</>
	);
};

const PaymentRequestDescription = () => (
	<>
		<h2>{ __( 'Express checkouts', 'woocommerce-gateway-stripe' ) }</h2>
		<p>
			{ __(
				'Let your customers use their favorite express payment methods and digital wallets for faster, more secure checkouts across different parts of your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<ExternalLink href="https://woocommerce.com/document/stripe/#express-checkouts">
			{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
		</ExternalLink>
	</>
);

const PaymentMethodsPanel = () => {
	return (
		<>
			<SettingsSection Description={ PaymentMethodsDescription }>
				<GeneralSettingsSection />
				<CustomizationOptionsNotice />
			</SettingsSection>
			<SettingsSection Description={ PaymentRequestDescription }>
				<LoadableSettingsSection numLines={ 20 }>
					<PaymentRequestSection />
				</LoadableSettingsSection>
			</SettingsSection>
		</>
	);
};

export default PaymentMethodsPanel;
