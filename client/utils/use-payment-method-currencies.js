import { useContext } from '@wordpress/element';
import UpeToggleContext from '../settings/upe-toggle/context';
import PaymentMethodsMap from '../payment-methods-map';
import {
	PAYMENT_METHOD_ALIPAY,
	PAYMENT_METHOD_KLARNA,
	PAYMENT_METHOD_WECHAT_PAY,
	PAYMENT_METHOD_AMAZON_PAY,
} from 'wcstripe/stripe-utils/constants';

const accountCountry =
	window.wc_stripe_settings_params?.account_country || 'US';

// When UPE is disabled returns the list of all the currencies supported by AliPay.
// When UPE is enabled returns the specific currencies AliPay supports for the corresponding Stripe account based on location.
// Documentation: https://docs.stripe.com/payments/alipay#supported-currencies.
const getAliPayCurrencies = ( isUpeEnabled ) => {
	if ( ! isUpeEnabled ) {
		return [
			'AUD',
			'CAD',
			'CNY',
			'EUR',
			'GBP',
			'HKD',
			'JPY',
			'MYR',
			'NZD',
			'USD',
		];
	}

	let upeCurrencies = [];
	switch ( accountCountry ) {
		case 'AU':
			upeCurrencies = [ 'AUD', 'CNY' ];
			break;
		case 'CA':
			upeCurrencies = [ 'CAD', 'CNY' ];
			break;
		case 'GB':
			upeCurrencies = [ 'GBP', 'CNY' ];
			break;
		case 'HK':
			upeCurrencies = [ 'HKD', 'CNY' ];
			break;
		case 'JP':
			upeCurrencies = [ 'JPY', 'CNY' ];
			break;
		case 'MY':
			upeCurrencies = [ 'MYR', 'CNY' ];
			break;
		case 'NZ':
			upeCurrencies = [ 'NZD', 'CNY' ];
			break;
		case 'SG':
			upeCurrencies = [ 'SGD', 'CNY' ];
			break;
		case 'US':
			upeCurrencies = [ 'USD', 'CNY' ];
			break;
		default:
			upeCurrencies = [ 'CNY' ];
	}

	const EuroSupportedCountries = [
		'AT',
		'BE',
		'BG',
		'CY',
		'CZ',
		'DK',
		'EE',
		'FI',
		'FR',
		'DE',
		'GR',
		'IE',
		'IT',
		'LV',
		'LT',
		'LU',
		'MT',
		'NL',
		'NO',
		'PT',
		'RO',
		'SK',
		'SI',
		'ES',
		'SE',
		'CH',
	];
	if ( EuroSupportedCountries.includes( accountCountry ) ) {
		upeCurrencies = [ 'EUR', 'CNY' ];
	}

	return upeCurrencies;
};

// Returns the specific currencies WeChat Pay supports for the corresponding Stripe account based on location.
// Documentation: https://docs.stripe.com/payments/wechat-pay/accept-a-payment?ui=direct-api#supported-currencies.
const getWechatPayCurrencies = () => {
	let upeCurrencies = [];
	switch ( accountCountry ) {
		case 'AU':
			upeCurrencies = [ 'AUD', 'CNY' ];
			break;
		case 'CA':
			upeCurrencies = [ 'CAD', 'CNY' ];
			break;
		case 'CH':
			upeCurrencies = [ 'CHF', 'CNY', 'EUR' ];
			break;
		case 'DK':
			upeCurrencies = [ 'DKK', 'CNY', 'EUR' ];
			break;
		case 'HK':
			upeCurrencies = [ 'HKD', 'CNY' ];
			break;
		case 'JP':
			upeCurrencies = [ 'JPY', 'CNY' ];
			break;
		case 'NO':
			upeCurrencies = [ 'NOK', 'CNY', 'EUR' ];
			break;
		case 'SE':
			upeCurrencies = [ 'SEK', 'CNY', 'EUR' ];
			break;
		case 'SG':
			upeCurrencies = [ 'SGD', 'CNY' ];
			break;
		case 'GB':
			upeCurrencies = [ 'GBP', 'CNY' ];
			break;
		case 'US':
			upeCurrencies = [ 'USD', 'CNY' ];
			break;
		default:
			upeCurrencies = [ 'CNY' ];
	}

	const EuroSupportedCountries = [
		'AT',
		'BE',
		'FI',
		'FR',
		'DE',
		'IE',
		'IT',
		'LU',
		'NL',
		'PT',
		'ES',
	];

	if ( EuroSupportedCountries.includes( accountCountry ) ) {
		upeCurrencies = [ 'EUR', 'CNY' ];
	}

	return upeCurrencies;
};

// Returns the specific currencies Klarna supports for the corresponding Stripe account based on location.
// Documentation: https://docs.stripe.com/payments/klarna#:~:text=Merchant%20country%20availability.
const getKlarnaCurrencies = () => {
	// Accounts can transact in their local currency.
	switch ( accountCountry ) {
		case 'AU':
			return [ 'AUD' ];
		case 'CA':
			return [ 'CAD' ];
		case 'NZ':
			return [ 'NZD' ];
		case 'US':
			return [ 'USD' ];
	}

	const eeaCountries = [
		'AT', // Austria
		'BE', // Belgium
		'HR', // Croatia
		'CY', // Cyprus
		'CZ', // Czech Republic
		'DK', // Denmark
		'EE', // Estonia
		'FI', // Finland
		'FR', // France
		'DE', // Germany
		'GR', // Greece
		'IE', // Ireland
		'IT', // Italy
		'LV', // Latvia
		'LT', // Lithuania
		'LU', // Luxembourg
		'MT', // Malta
		'NL', // Netherlands
		'NO', // Norway
		'PL', // Poland
		'PT', // Portugal
		'RO', // Romania
		'SK', // Slovakia
		'SI', // Slovenia
		'ES', // Spain
		'SE', // Sweden
		'CH', // Switzerland
		'GB', // United Kingdom
	];

	// Countries located in the EEA, Switzerland and the UK can also transact in any EU based currencies including NOK, PLN, DKK etc.
	if ( eeaCountries.includes( accountCountry ) ) {
		return [ 'EUR', 'SEK', 'PLN', 'CHF', 'CZK', 'DKK', 'GBP', 'NOK' ];
	}

	// Throw an error if the country is not recognized.
	throw new Error(
		`Unable to determine Klarna currencies for: ${ accountCountry }`
	);
};

const getAmazonPayCurrencies = () => {
	switch ( accountCountry ) {
		case 'US':
			return [ 'USD' ];
		default:
			return [ 'USD' ];
	}
};

export const usePaymentMethodCurrencies = ( paymentMethodId ) => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	switch ( paymentMethodId ) {
		case PAYMENT_METHOD_ALIPAY:
			return getAliPayCurrencies( isUpeEnabled );
		case PAYMENT_METHOD_WECHAT_PAY:
			return getWechatPayCurrencies();
		case PAYMENT_METHOD_KLARNA:
			return getKlarnaCurrencies();
		case PAYMENT_METHOD_AMAZON_PAY:
			return getAmazonPayCurrencies();
		default:
			return PaymentMethodsMap[ paymentMethodId ]?.currencies || [];
	}
};

export default usePaymentMethodCurrencies;
