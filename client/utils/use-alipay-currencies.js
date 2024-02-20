import { useContext } from '@wordpress/element';
import UpeToggleContext from '../settings/upe-toggle/context';
import { useAccount } from 'wcstripe/data/account';

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
		case 'AUS':
			upeCurrencies = [ 'AUD', 'CNY' ];
			break;
		case 'Canada':
			upeCurrencies = [ 'CAD', 'CNY' ];
			break;
		case 'UK':
			upeCurrencies = [ 'GBP', 'CNY' ];
			break;
		case 'Hongkong':
			upeCurrencies = [ 'HKD', 'CNY' ];
			break;
		case 'Japan':
			upeCurrencies = [ 'JPY', 'CNY' ];
			break;
		case 'Malaysia':
			upeCurrencies = [ 'MYR', 'CNY' ];
			break;
		case 'NZ':
			upeCurrencies = [ 'NZD', 'CNY' ];
			break;
		case 'Singapore':
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
