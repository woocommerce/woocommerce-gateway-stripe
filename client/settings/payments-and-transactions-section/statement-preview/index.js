/**
 * External dependencies
 */
import { Icon } from '@wordpress/components';
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';

const icons = {
	creditCard: () => {
		return (
			<svg
				width="17"
				height="16"
				viewBox="0 0 17 16"
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
				role="img"
				aria-label="credit card icon"
			>
				<mask
					id="mask-cc"
					style={ { maskType: 'alpha' } }
					maskUnits="userSpaceOnUse"
					x="1"
					y="2"
					width="14"
					height="12"
				>
					<path
						fillRule="evenodd"
						clipRule="evenodd"
						d="M13.3647 2.66669H2.69808C1.95808 2.66669 1.37141 3.26002 1.37141 4.00002L1.36475 12C1.36475 12.74 1.95808 13.3334 2.69808 13.3334H13.3647C14.1047 13.3334 14.6981 12.74 14.6981 12V4.00002C14.6981 3.26002 14.1047 2.66669 13.3647 2.66669ZM13.3647 12H2.69808V8.00002H13.3647V12ZM2.69808 5.33335H13.3647V4.00002H2.69808V5.33335Z"
						fill="white"
					/>
				</mask>
				<g mask="url(#mask-cc)">
					<rect x="0.0314941" width="16" height="16" fill="#1E1E1E" />
				</g>
			</svg>
		);
	},
	bank: () => {
		return (
			<svg
				width="17"
				height="16"
				viewBox="0 0 17 16"
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
				role="img"
				aria-label="bank icon"
			>
				<mask
					id="mask-bank"
					style={ { maskType: 'alpha' } }
					maskUnits="userSpaceOnUse"
					x="1"
					y="1"
					width="14"
					height="14"
				>
					<path
						fillRule="evenodd"
						clipRule="evenodd"
						d="M1.69812 4.66665L8.03145 1.33331L14.3648 4.66665V5.99998H1.69812V4.66665ZM8.03145 2.83998L11.5048 4.66665H4.55812L8.03145 2.83998ZM3.36479 7.33331H4.69812V12H3.36479V7.33331ZM8.69812 7.33331V12H7.36479V7.33331H8.69812ZM14.3648 14.6666V13.3333H1.69812V14.6666H14.3648ZM11.3648 7.33331H12.6981V12H11.3648V7.33331Z"
						fill="white"
					/>
				</mask>
				<g mask="url(#mask-bank)">
					<rect x="0.0314941" width="16" height="16" fill="#1E1E1E" />
				</g>
			</svg>
		);
	},
};

const StatementPreview = ( { title, icon, text, className = '' } ) => {
	const StatementIcon = ( { icon: thisIcon } ) => {
		const iconSVG = icons?.[ thisIcon ] ? icons[ thisIcon ] : icons.bank;
		return <Icon icon={ iconSVG } />;
	};

	// Handles formatting the preview amount according to the store's currency settings.
	const decimals = '0'.repeat( wcSettings.currency.precision );
	const transactionAmount =
		wcSettings.currency.symbolPosition === 'left'
			? `${ wcSettings.currency.symbol }20${ wcSettings.currency.decimalSeparator }${ decimals }`
			: `20${ wcSettings.currency.decimalSeparator }${ decimals }${ wcSettings.currency.symbol }`;

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
