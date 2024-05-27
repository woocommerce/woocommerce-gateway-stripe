import { useContext } from '@wordpress/element';
import UpeToggleContext from '../settings/upe-toggle/context';
import PaymentMethodsMap from '../payment-methods-map';

const accountCountry =
	window.wc_stripe_settings_params?.account_country || 'US';

// When UPE is disabled returns the list of all the currencies supported by AliPay.
// When UPE is enabled returns the specific currencies AliPay supports for the corresponding Stripe account based on location.
// Documentation: https://stripe.com/docs/payments/alipay#supported-currencies.
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
		case 'UK':
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
		case 'UK':
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

export const usePaymentMethodCurrencies = ( paymentMethodId ) => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	if ( paymentMethodId === 'alipay' ) {
		return getAliPayCurrencies( isUpeEnabled );
	} else if ( paymentMethodId === 'wechat_pay' ) {
		return getWechatPayCurrencies();
	}

	return PaymentMethodsMap[ paymentMethodId ]?.currencies || [];
};

export default usePaymentMethodCurrencies;
