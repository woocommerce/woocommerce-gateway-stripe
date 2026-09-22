import React, { useCallback } from 'react';
import PayoutsTable from './payouts-table';
import BalanceBanner from './balance-banner';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import '@wordpress/dataviews/build-style/style.css';
import './style.scss';

const PaymentsPage = () => {
	const title = 'Stripe';

	const tabs = [
		{
			name: 'payouts',
			title: __( 'Payouts', 'woocommerce-gateway-stripe' ),
		},
	];

	const renderTabContent = useCallback( ( tab ) => {
		switch ( tab.name ) {
			case 'payouts':
				return <PayoutsTable />;
			default:
				return null;
		}
	}, [] );

	return (
		<div className="wc-stripe-payments">
			<h1 className="wc-stripe-payments__heading">{ title }</h1>
			<BalanceBanner />
			<TabPanel tabs={ tabs } className="wc-stripe-payments__tabs">
				{ renderTabContent }
			</TabPanel>
		</div>
	);
};

export default PaymentsPage;
