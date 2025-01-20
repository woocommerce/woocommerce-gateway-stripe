import { __ } from '@wordpress/i18n';
import React from 'react';
import { Card, CheckboxControl } from '@wordpress/components';
import { useAmazonPayEnabledSettings } from 'wcstripe/data';
import CardBody from 'wcstripe/settings/card-body';
const AmazonPayEnableSection = () => {
	const [
		isAmazonPayEnabled,
		updateIsAmazonPayEnabled,
	] = useAmazonPayEnabledSettings();

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
			</CardBody>
		</Card>
	);
};

export default AmazonPayEnableSection;
