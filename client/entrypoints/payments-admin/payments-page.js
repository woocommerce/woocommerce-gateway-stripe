import React, { useEffect, useMemo, useRef } from 'react';
import PayoutsTable from './payouts-table';
import { Page } from '@wordpress/admin-ui';
import { __ } from '@wordpress/i18n';
import '@wordpress/dataviews/build-style/style.css';
import './style.scss';

const PaymentsPage = () => {
	const actions = useMemo( () => {
		return [
			<div
				id="wc-stripe-payments-action-div"
				key="wc-stripe-payments-action-div"
			/>,
		];
	}, [] );

	const actionDivRef = useRef( null );

	useEffect( () => {
		if ( ! actionDivRef.current ) {
			const divById = document.getElementById(
				'wc-stripe-payments-action-div'
			);
			if ( divById ) {
				actionDivRef.current = divById;
			}
		}
	}, [] );

	return (
		<Page
			title={ __( 'Stripe Payouts', 'woocommerce-gateway-stripe' ) }
			className="wc-stripe-payments"
			actions={ actions }
		>
			<PayoutsTable actionDivRef={ actionDivRef } />
		</Page>
	);
};

export default PaymentsPage;
