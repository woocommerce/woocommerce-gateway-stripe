/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import styled from '@emotion/styled';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';
import PaymentRequestSection from '../payment-request-section';
import GeneralSettingsSection from '../general-settings-section';
import ApplePayIcon from '../../payment-method-icons/apple-pay';
import GooglePayIcon from '../../payment-method-icons/google-pay';
import LoadableSettingsSection from '../loadable-settings-section';
import UpeToggleContext from '../upe-toggle/context';

const IconsWrapper = styled.ul`
	li {
		display: inline-block;
		margin: 0 5px 0 0;
	}
`;

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
		<IconsWrapper>
			<li>
				<ApplePayIcon />
			</li>
			<li>
				<GooglePayIcon />
			</li>
		</IconsWrapper>
		<p>
			{ __(
				'Let your customers use their favorite express payment methods and digital wallets for faster, more secure checkouts across different parts of your store.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<ExternalLink href="?TODO">
			{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
		</ExternalLink>
	</>
);

const PaymentMethodsPanel = () => {
	return (
		<>
			<SettingsSection Description={ PaymentMethodsDescription }>
				<GeneralSettingsSection />
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
