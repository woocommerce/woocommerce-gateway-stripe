/* global wc_stripe_amazon_pay_settings_params */

import { getAdminLink } from '@woocommerce/settings';
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl, Notice } from '@wordpress/components';
import { useAmazonPayEnabledSettings } from 'wcstripe/data';
import CardBody from 'wcstripe/settings/card-body';

const AmazonPayTaxesBasedOnBillingAddressNotice = () => {
	// eslint-disable-next-line camelcase
	if ( ! wc_stripe_amazon_pay_settings_params?.taxes_based_on_billing ) {
		return null;
	}

	const actions = [
		{
			label: __( 'Update tax settings', 'woocommerce-gateway-stripe' ),
			url: getAdminLink( 'admin.php?page=wc-settings&tab=tax' ),
			variant: 'secondary',
		},
	];

	return (
		<Notice status="error" isDismissible={ false } actions={ actions }>
			<p>
				<strong>
					{ __(
						'Amazon Pay does not support taxes based on the billing address. The checkout button will not be visible to shoppers with this setting in effect.',
						'woocommerce-gateway-stripe'
					) }
				</strong>
			</p>
		</Notice>
	);
};

const AmazonPayEnableSection = () => {
	const [ isAmazonPayEnabled, updateIsAmazonPayEnabled ] =
		useAmazonPayEnabledSettings();

	return (
		<Card className="express-checkout-settings">
			<CardBody>
				<CheckboxControl
					checked={ isAmazonPayEnabled }
					onChange={ updateIsAmazonPayEnabled }
					label={ __(
						'Enable Amazon Pay',
						'woocommerce-gateway-stripe'
					) }
					help={ __(
						'When enabled, customers who have configured Amazon Pay enabled devices ' +
							'will be able to pay with their respective choice of Wallet.',
						'woocommerce-gateway-stripe'
					) }
				/>

				<AmazonPayTaxesBasedOnBillingAddressNotice />
			</CardBody>
		</Card>
	);
};

export default AmazonPayEnableSection;
