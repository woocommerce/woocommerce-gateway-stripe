import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import { useEnabledPaymentMethodIds } from 'wcstripe/data';
import { PAYMENT_METHOD_LINK } from 'wcstripe/stripe-utils/constants';
import CardBody from 'wcstripe/settings/card-body';

const LinkEnableSection = () => {
	const [ enabledMethodIds, updateEnabledMethodIds ] =
		useEnabledPaymentMethodIds();

	const isLinkEnabled = enabledMethodIds.includes( PAYMENT_METHOD_LINK );

	const updateIsLinkEnabled = ( isEnabled ) => {
		if ( isEnabled ) {
			updateEnabledMethodIds( [
				...enabledMethodIds,
				PAYMENT_METHOD_LINK,
			] );
		} else {
			updateEnabledMethodIds( [
				...enabledMethodIds.filter(
					( id ) => id !== PAYMENT_METHOD_LINK
				),
			] );
		}
	};

	return (
		<Card className="express-checkout-settings">
			<CardBody>
				<CheckboxControl
					checked={ isLinkEnabled }
					onChange={ updateIsLinkEnabled }
					label={ __(
						'Enable Link by Stripe',
						'woocommerce-gateway-stripe'
					) }
					help={ __(
						'When enabled, customers will be able to pay with Link by Stripe ' +
							'for a fast, simple, and secure checkout experience.',
						'woocommerce-gateway-stripe'
					) }
				/>
			</CardBody>
		</Card>
	);
};

export default LinkEnableSection;
