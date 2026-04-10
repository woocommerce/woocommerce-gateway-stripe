import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import { useExpressCheckoutEnabledSettings } from 'wcstripe/data';
import CardBody from 'wcstripe/settings/card-body';
const ExpressCheckoutEnableSection = () => {
	const [ isExpressCheckoutEnabled, updateIsExpressCheckoutEnabled ] =
		useExpressCheckoutEnabledSettings();

	return (
		<Card className="express-checkout-settings">
			<CardBody>
				<CheckboxControl
					checked={ isExpressCheckoutEnabled }
					onChange={ updateIsExpressCheckoutEnabled }
					label={ __(
						'Enable Apple Pay / Google Pay',
						'woocommerce-gateway-stripe'
					) }
					help={ __(
						'When enabled, customers who have configured Apple Pay or Google Pay enabled devices ' +
							'will be able to pay with their respective choice of Wallet.',
						'woocommerce-gateway-stripe'
					) }
				/>
			</CardBody>
		</Card>
	);
};

export default ExpressCheckoutEnableSection;
