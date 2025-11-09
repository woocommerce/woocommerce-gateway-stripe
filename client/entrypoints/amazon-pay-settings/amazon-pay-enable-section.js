/* global wc_stripe_amazon_pay_settings_params */

import React from 'react';
import AmazonPayTaxesBillingAddressNotice from 'wcstripe/components/amazon-pay-taxes-billing-address-notice';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import { useAmazonPayEnabledSettings } from 'wcstripe/data';
import CardBody from 'wcstripe/settings/card-body';

const AmazonPayEnableSection = () => {
	const [ isAmazonPayEnabled, updateIsAmazonPayEnabled ] =
		useAmazonPayEnabledSettings();

	const areTaxesBasedOnBillingAddress =
		!! wc_stripe_amazon_pay_settings_params?.taxes_based_on_billing; // eslint-disable-line camelcase

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

				<AmazonPayTaxesBillingAddressNotice
					areTaxesBasedOnBillingAddress={
						areTaxesBasedOnBillingAddress
					}
				/>
			</CardBody>
		</Card>
	);
};

export default AmazonPayEnableSection;
