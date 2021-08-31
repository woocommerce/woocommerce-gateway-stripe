/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import ExpressCheckoutsCustomizer from './express-checkout-customizer';
import SettingsSection from '../settings-section';
import SettingsLayout from '../settings-layout';
import LoadableSettingsSection from '../../components/loadable-settings-section';
import SaveSettingsSection from '../save-settings-section';

const methods = {
	payment_request: {
		title: 'Express checkouts',
		description: () => (
			<>
				<h2>{ __( 'Express checkouts', 'woocommerce-payments' ) }</h2>
				<p>
					{ __(
						'Decide how buttons for digital wallets like Apple Pay and Google Pay are displayed in your store.',
						'woocommerce-payments'
					) }
				</p>
				<p>
					<ExternalLink href="https://developer.apple.com/design/human-interface-guidelines/apple-pay/overview/introduction/">
						{ __(
							'View Apple Pay Guidelines',
							'woocommerce-payments'
						) }
					</ExternalLink>
				</p>
				<p>
					<ExternalLink href="https://developers.google.com/pay/api/web/guides/brand-guidelines">
						{ __(
							'View Google Pay Guidelines',
							'woocommerce-payments'
						) }
					</ExternalLink>
				</p>
			</>
		),
	},
};

const ExpressCheckoutsSettings = ( { methodId } ) => {
	const method = methods[ methodId ];

	if ( ! method ) {
		return (
			<p>
				{ __(
					'Invalid payment method ID specified.',
					'woocommerce-payments'
				) }
			</p>
		);
	}

	const { title, description: Description } = method;

	return (
		<SettingsLayout>
			<h2 className="payment-method-settings__breadcrumbs">
				<a href={ '#' }>
					{ __( 'WooCommerce Stripe', 'woocommerce-payments' ) }
				</a>{ ' ' }
				&gt; <span>{ title }</span>
			</h2>

			<SettingsSection Description={ Description }>
				<LoadableSettingsSection numLines={ 30 }>
					<ExpressCheckoutsCustomizer />
				</LoadableSettingsSection>
			</SettingsSection>

			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default ExpressCheckoutsSettings;
