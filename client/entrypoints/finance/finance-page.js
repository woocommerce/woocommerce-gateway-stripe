import React from 'react';
import PaymentIntentsTable from './payment-intents-table';
import PayoutsTable from './payouts-table';
import { __ } from '@wordpress/i18n';
import '@wordpress/dataviews/build-style/style.css';
import './style.scss';

const FinancePage = ( { context = 'payouts' } ) => {
	const title =
		context === 'payouts'
			? __( 'Payouts', 'woocommerce-gateway-stripe' )
			: __( 'Transactions', 'woocommerce-gateway-stripe' );
	return (
		<div className="wc-stripe-finance">
			<h1 className="wc-stripe-finance__heading">{ title }</h1>
			{ context === 'payouts' ? (
				<PayoutsTable />
			) : (
				<PaymentIntentsTable />
			) }
		</div>
	);
};

export default FinancePage;
