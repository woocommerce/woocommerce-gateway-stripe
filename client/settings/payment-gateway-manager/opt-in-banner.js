import { __, sprintf } from '@wordpress/i18n';
import React, { useContext } from 'react';
import { getQuery } from '@woocommerce/navigation';
import UpeOptInBanner from '../upe-opt-in-banner';
import UpeToggleContext from '../upe-toggle/context';
import BannerImage from './opt-in-banner-image';
import { gatewaysInfo } from './constants';

export default () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const { section } = getQuery();
	const info = gatewaysInfo[ section ];

	if ( isUpeEnabled ) {
		return null;
	}

	return (
		<UpeOptInBanner
			title={ __(
				'Enable the new Stripe payment management experience',
				'woocommerce-gateway-stripe'
			) }
			description={ sprintf(
				/* translators: %s: payment method name. */
				__(
					'Spend less time managing %s and other payment methods in an improved settings and checkout experience, now available to select merchants.',
					'woocommerce-gateway-stripe'
				),
				info.title
			) }
			Image={ BannerImage }
			data-testid="opt-in-banner"
		/>
	);
};
