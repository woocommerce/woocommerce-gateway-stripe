import React, { useEffect, useState, useMemo, useCallback } from 'react';
import InstantPayoutsPromotionNotice from './instant-payouts-promotion-notice';
import PayoutsTable from './payouts-table';
import {
	getInstantPayoutsMessage,
	getPayoutsDashboardUrl,
	isInstantPayoutsBannerDismissed,
} from './utils';
import { Page } from '@wordpress/admin-ui';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { LinkButton } from '@wordpress/ui';
import { NAMESPACE } from 'wcstripe/data/constants';
import { dismissNotice } from 'wcstripe/utils';
import '@wordpress/dataviews/build-style/style.css';
import './style.scss';

const INSTANT_PAYOUTS_BANNER_NOTICE_KEY =
	'wc_stripe_show_instant_payouts_banner';

const PaymentsPage = () => {
	const [ balance, setBalance ] = useState( null );
	const [ isBannerDismissed, setIsBannerDismissed ] = useState(
		isInstantPayoutsBannerDismissed
	);

	useEffect( () => {
		apiFetch( { path: `${ NAMESPACE }/balance` } )
			.then( setBalance )
			// Without a balance there is nothing to promote, so the page renders without the banner or CTA.
			.catch( () => setBalance( null ) );
	}, [] );

	const instantPayoutsMessage = useMemo(
		() => getInstantPayoutsMessage( balance ),
		[ balance ]
	);
	const payoutsUrl = useMemo(
		() => getPayoutsDashboardUrl( balance ),
		[ balance ]
	);

	const handleDismissBanner = useCallback( () => {
		setIsBannerDismissed( true );
		dismissNotice( INSTANT_PAYOUTS_BANNER_NOTICE_KEY );
	}, [ setIsBannerDismissed ] );

	// Once the banner is dismissed, Instant Payouts stay reachable from the page header.
	const actions = useMemo( () => {
		if ( instantPayoutsMessage && isBannerDismissed ) {
			return (
				<LinkButton variant="outline" href={ payoutsUrl } openInNewTab>
					{ __(
						'View Instant Payouts',
						'woocommerce-gateway-stripe'
					) }
				</LinkButton>
			);
		}
		return null;
	}, [ instantPayoutsMessage, isBannerDismissed, payoutsUrl ] );

	return (
		<Page
			title={ __( 'Stripe Payouts', 'woocommerce-gateway-stripe' ) }
			className="wc-stripe-payments"
			actions={ actions }
		>
			{ instantPayoutsMessage && ! isBannerDismissed && (
				<InstantPayoutsPromotionNotice
					message={ instantPayoutsMessage }
					payoutsUrl={ payoutsUrl }
					onDismiss={ handleDismissBanner }
				/>
			) }
			<PayoutsTable />
		</Page>
	);
};

export default PaymentsPage;
