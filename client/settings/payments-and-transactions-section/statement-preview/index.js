import { __ } from '@wordpress/i18n';
import React from 'react';
import { Icon } from '@wordpress/components';
import CurrencyFactory from '@woocommerce/currency';
import './style.scss';
import { CreditCardIcon } from './icons/creditCard';
import { CashAppIcon } from './icons/cashApp.js';
import { BankIcon } from './icons/bank.js';

const icons = {
	creditCard: CreditCardIcon,
	cashApp: CashAppIcon,
	bank: BankIcon,
};

const StatementPreview = ( { title, icon, text, className = '' } ) => {
	const StatementIcon = ( { icon: thisIcon } ) => {
		const iconSVG = icons?.[ thisIcon ] ? icons[ thisIcon ] : icons.bank;
		return <Icon icon={ iconSVG } />;
	};

	const currencySettings = CurrencyFactory( {
		symbol: '$',
		symbolPosition: 'left',
		precision: 2,
		decimalSeparator: '.',
		...window?.wcSettings?.currency,
	} );

	// Handles formatting the preview amount according to the store's currency settings.
	const transactionAmount = currencySettings.formatAmount( 20 );

	return (
		<div className={ `statement-preview ${ className }` }>
			<div className="statement-icon-and-title">
				<StatementIcon icon={ icon } /> <p>{ title }</p>
			</div>
			<span>{ __( 'Transaction', 'woocommerce-gateway-stripe' ) }</span>
			<span>{ __( 'Amount', 'woocommerce-gateway-stripe' ) }</span>
			<hr />
			<span className="transaction-detail description">{ text }</span>
			<span className="transaction-detail amount">
				{ transactionAmount }
			</span>
		</div>
	);
};

export default StatementPreview;
