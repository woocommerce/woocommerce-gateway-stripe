import { useContext } from '@wordpress/element';
import UpeToggleContext from '../settings/upe-toggle/context';
import { useAccount } from 'wcstripe/data/account';

// When UPE is disabled returns the list of all the currencies supported by AliPay.
// When UPE is enabled returns the specific currencies AliPay supports for the corresponding Stripe account based on location.
// Documentation: https://stripe.com/docs/payments/alipay#supported-currencies.
export const useAliPayCurrencies = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const { data } = useAccount();

	let upeCurrencies = [];

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

	switch ( data?.account?.country ) {
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
	if ( EuroSupportedCountries.includes( data?.account?.country ) ) {
		upeCurrencies = [ 'EUR', 'CNY' ];
	}

	return upeCurrencies;
};

export default useAliPayCurrencies;
