/**
 * ISO 3166-1 alpha-2 country codes for the European Economic Area.
 *
 * Mirrors the PHP list in WC_Stripe_Helper::get_european_economic_area_countries().
 *
 * @see https://www.gov.uk/eu-eea
 */
export const EEA_COUNTRIES = [
	'AT', // Austria
	'BE', // Belgium
	'BG', // Bulgaria
	'HR', // Croatia
	'CY', // Cyprus
	'CZ', // Czech Republic
	'DK', // Denmark
	'EE', // Estonia
	'FI', // Finland
	'FR', // France
	'DE', // Germany
	'GR', // Greece
	'HU', // Hungary
	'IE', // Ireland
	'IS', // Iceland
	'IT', // Italy
	'LV', // Latvia
	'LI', // Liechtenstein
	'LT', // Lithuania
	'LU', // Luxembourg
	'MT', // Malta
	'NO', // Norway
	'NL', // Netherlands
	'PL', // Poland
	'PT', // Portugal
	'RO', // Romania
	'SK', // Slovakia
	'SI', // Slovenia
	'ES', // Spain
	'SE', // Sweden
];

/**
 * Returns true if the provided country code is within the EEA.
 *
 * @param {string} country ISO 3166-1 alpha-2 country code.
 * @return {boolean} True if the country code is within the EEA, false otherwise.
 */
export const isEeaCountry = ( country ) =>
	Boolean( country ) && EEA_COUNTRIES.includes( country.toUpperCase() );
