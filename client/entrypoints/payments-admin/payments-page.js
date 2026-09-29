import React from 'react';
import PayoutsTable from './payouts-table';
import { Page } from '@wordpress/admin-ui';
import { __ } from '@wordpress/i18n';
import '@wordpress/dataviews/build-style/style.css';
import './style.scss';

const PaymentsPage = () => {
	return (
		<Page
			title={ __( 'Stripe Payouts', 'woocommerce-gateway-stripe' ) }
			className="wc-stripe-payments"
		>
			<PayoutsTable />
		</Page>
	);
};

export default PaymentsPage;
