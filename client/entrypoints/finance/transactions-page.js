import React from 'react';
import PaymentIntentsTable from './payment-intents-table';
import { __ } from '@wordpress/i18n';
import '@wordpress/dataviews/build-style/style.css';
import './style.scss';

const TransactionsPage = () => (
	<div className="wc-stripe-transactions">
		<h1 className="wc-stripe-transactions__heading">
			{ __( 'Transactions', 'woocommerce-gateway-stripe' ) }
		</h1>
		<PaymentIntentsTable />
	</div>
);

export default TransactionsPage;
