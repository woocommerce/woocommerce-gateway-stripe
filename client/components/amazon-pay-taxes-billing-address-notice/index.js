import { getAdminLink } from '@woocommerce/settings';
import React from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useAmazonPayEnabledSettings } from 'wcstripe/data';

import './style.scss';

const AmazonPayTaxesBillingAddressNotice = ( {
	areTaxesBasedOnBillingAddress = false,
} ) => {
	const [ isAmazonPayEnabled ] = useAmazonPayEnabledSettings();

	if ( ! isAmazonPayEnabled ) {
		return null;
	}
	if ( ! areTaxesBasedOnBillingAddress ) {
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
		<Notice
			className="wc-stripe-amazon-pay-taxes-billing-address-notice"
			status="error"
			isDismissible={ false }
			actions={ actions }
		>
			{ interpolateComponents( {
				mixedString: __(
					'{{strong}}Amazon Pay does not support taxes based on the billing address.{{/strong}} The checkout button will not be visible to shoppers with this setting in effect.',
					'woocommerce-gateway-stripe'
				),
				components: {
					strong: <strong />,
				},
			} ) }
		</Notice>
	);
};

export default AmazonPayTaxesBillingAddressNotice;
