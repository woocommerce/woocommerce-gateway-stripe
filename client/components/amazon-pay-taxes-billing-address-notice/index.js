import { getAdminLink } from '@woocommerce/settings';
import React from 'react';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useAmazonPayEnabledSettings } from 'wcstripe/data';

const AmazonPayTaxesBillingAddressNotice = ( {
	areTaxesBasedOnBillingAddress = false,
	noticeStatus = 'error',
	showUpdateSettingsLink = true,
} ) => {
	const [ isAmazonPayEnabled ] = useAmazonPayEnabledSettings();

	if ( ! isAmazonPayEnabled ) {
		return null;
	}
	if ( ! areTaxesBasedOnBillingAddress ) {
		return null;
	}

	const actions = showUpdateSettingsLink
		? [
				{
					label: __(
						'Update tax settings',
						'woocommerce-gateway-stripe'
					),
					url: getAdminLink( 'admin.php?page=wc-settings&tab=tax' ),
					variant: 'secondary',
				},
		  ]
		: [];

	return (
		<Notice
			status={ noticeStatus }
			isDismissible={ false }
			actions={ actions }
		>
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

export default AmazonPayTaxesBillingAddressNotice;
