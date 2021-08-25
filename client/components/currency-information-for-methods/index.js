/**
 * External dependencies
 */
import React, { useContext } from 'react';
import _ from 'lodash';
import { sprintf, __, _n } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
//TODO
// import { useCurrencies, useEnabledCurrencies } from '../../data';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import InlineNotice from '../inline-notice';
import PaymentMethodsMap from '../../payment-methods-map';

const ListToCommaSeparatedSentencePartConverter = ( items ) => {
	if ( 1 === items.length ) {
		return items[ 0 ];
	} else if ( 2 === items.length ) {
		return items.join( ' ' + __( 'and', 'woocommerce-payments' ) + ' ' );
	}
	const lastItem = items.pop();
	return (
		items.join( ', ' ) +
		__( ', and', 'woocommerce-payments' ) +
		' ' +
		lastItem
	);
};

//TODO: these should come from data
const useCurrencies = () => {
	return {
		isLoading: false,
		currencies: {}
	}
};
const useEnabledCurrencies = () => {
	return {
		enabledCurrencies: {}
	};
};


const CurrencyInformationForMethods = ( { selectedMethods } ) => {
	const {
		isLoading: isLoadingCurrencyInformation,
		currencies: currencyInfo,
	} = useCurrencies();
	const { enabledCurrencies } = useEnabledCurrencies();

	if ( isLoadingCurrencyInformation ) {
		return null;
	}

	const enabledCurrenciesIds = Object.values( enabledCurrencies ).map(
		( currency ) => currency.id
	);

	let paymentMethodsWithMissingCurrencies = [];
	let missingCurrencyLabels = [];
	const missingCurrencies = [];

	selectedMethods.map( ( paymentMethod ) => {
		if ( 'undefined' !== typeof PaymentMethodsMap[ paymentMethod ] ) {
			PaymentMethodsMap[ paymentMethod ].currencies.map( ( currency ) => {
				if (
					! enabledCurrenciesIds.includes( currency.toLowerCase() )
				) {
					missingCurrencies.push( currency );

					paymentMethodsWithMissingCurrencies.push(
						PaymentMethodsMap[ paymentMethod ].label
					);

					const missingCurrencyInfo =
						currencyInfo.available[ currency ] || null;

					const missingCurrencyLabel =
						null != missingCurrencyInfo
							? missingCurrencyInfo.name +
							  ' (' +
							  ( undefined !== missingCurrencyInfo.symbol
									? missingCurrencyInfo.symbol
									: currency.toUpperCase() ) +
							  ')'
							: currency.toUpperCase();

					missingCurrencyLabels.push( missingCurrencyLabel );
				}
				return currency;
			} );
		}
		return paymentMethod;
	} );

	missingCurrencyLabels = _.uniq( missingCurrencyLabels );
	paymentMethodsWithMissingCurrencies = _.uniq(
		paymentMethodsWithMissingCurrencies
	);

	if ( 0 < missingCurrencyLabels.length ) {
		return (
			<InlineNotice status="info" isDismissible={ false }>
				{ interpolateComponents( {
					mixedString: sprintf(
						__(
							"%s %s %s additional %s, so {{strong}}we'll add %s to your store{{/strong}}. " +
								'You can view & manage currencies later in settings.',
							'woocommerce-payments'
						),
						ListToCommaSeparatedSentencePartConverter(
							paymentMethodsWithMissingCurrencies
						),
						_n(
							'requires',
							'require',
							paymentMethodsWithMissingCurrencies.length,
							'woocommerce-payments'
						),
						1 === missingCurrencyLabels.length ? 'an' : '',
						_n(
							'currency',
							'currencies',
							missingCurrencyLabels.length,
							'woocommerce-payments'
						),
						ListToCommaSeparatedSentencePartConverter(
							missingCurrencyLabels
						)
					),
					components: {
						strong: <strong />,
					},
				} ) }
			</InlineNotice>
		);
	}
	return null;
};

const CurrencyInformationForMethodsWrapper = ( props ) => {
	const {
		featureFlags: { multiCurrency },
	} = useContext( WCPaySettingsContext );

	// Prevents loading currency data when the feature flag is disabled.
	if ( ! multiCurrency ) return null;

	return <CurrencyInformationForMethods { ...props } />;
};

export default CurrencyInformationForMethodsWrapper;
