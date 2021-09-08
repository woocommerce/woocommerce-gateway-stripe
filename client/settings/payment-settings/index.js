/**
 * External dependencies
 */
import { React, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardFooter,
	CheckboxControl,
	ExternalLink,
} from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import styled from '@emotion/styled';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import PaymentsAndTransactionsSection from '../payments-and-transactions-section';
import AdvancedSettingsSection from '../advanced-settings-section';
import CustomizationOptionsNotice from '../customization-options-notice';
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

const GeneralSettingsSection = () => {
	const [ enableStripe, setEnableStripe ] = useState( false );
	const [ enableTestMode, setEnableTestMode ] = useState( false );

	const toggleEnableStripe = () => setEnableStripe( ! enableStripe );
	const toggleEnableTestMode = () => setEnableTestMode( ! enableTestMode );

	const CheckboxDescription = styled.span`
		font-style: italic;
		display: block;
	`;

	const GeneralSettingsOptionWrapper = styled.div`
		&:first-child {
			margin-top: 26px;
		}
		&:not( :last-child ) {
			margin-bottom: 26px;
		}
		&:last-child {
			margin-bottom: 24px;
		}
	`;

	return (
		<Card>
			<CardBody>
				<GeneralSettingsOptionWrapper>
					<CheckboxControl
						checked={ enableStripe }
						onChange={ toggleEnableStripe }
						label={ __(
							'Enable Stripe',
							'woocommerce-gateway-stripe'
						) }
					/>
					<CheckboxDescription>
						{ __(
							'When enabled, payment methods powered by Stripe will appear on checkout.',
							'woocommerce-gateway-stripe'
						) }
					</CheckboxDescription>
				</GeneralSettingsOptionWrapper>

				<GeneralSettingsOptionWrapper>
					<CheckboxControl
						checked={ enableTestMode }
						onChange={ toggleEnableTestMode }
						label={ __(
							'Enable test mode',
							'woocommerce-gateway-stripe'
						) }
					/>
					<CheckboxDescription>
						{ interpolateComponents( {
							mixedString: __(
								'Use {{testCardNumbersLink /}} to simulate various transactions. {{learnMoreLink/}}',
								'woocommerce-gateway-stripe'
							),
							components: {
								testCardNumbersLink: (
									<a href="?TODO">test card numbers</a>
								),
								learnMoreLink: <a href="?TODO">Learn more</a>,
							},
						} ) }
					</CheckboxDescription>
				</GeneralSettingsOptionWrapper>
			</CardBody>
			<CardFooter>
				<Button isSecondary href="?TODO">
					{ __( 'Edit account keys', 'woocommerce-gateway-stripe' ) }
				</Button>
			</CardFooter>
		</Card>
	);
};

const AccountDetailsSection = () => {
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
