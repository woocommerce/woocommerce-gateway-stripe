<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Stripe_Currency_Code
 */
class WC_Stripe_Currency_Code {

	// Source: https://docs.stripe.com/currencies
	public const UNITED_STATES_DOLLAR                    = 'USD'; // United States Dollar.
	public const UNITED_ARAB_EMIRATES_DIRHAM             = 'AED'; // United Arab Emirates dirham.
	public const AFGHAN_AFGHANI                          = 'AFN'; // Afghan afghani.
	public const ALBANIAN_LEK                            = 'ALL'; // Albanian lek.
	public const ARMENIAN_DRAM                           = 'AMD'; // Armenian dram.
	public const NETHERLANDS_ANTILLEAN_GUILDER           = 'ANG'; // Netherlands Antillean guilder.
	public const ANGOLAN_KWANZA                          = 'AOA'; // Angolan kwanza.
	public const ARGENTINE_PESO                          = 'ARS'; // Argentine peso.
	public const AUSTRALIAN_DOLLAR                       = 'AUD'; // Australian dollar.
	public const ARUBAN_FLORIN                           = 'AWG'; // Aruban florin.
	public const AZERBAIJANI_MANAT                       = 'AZN'; // Azerbaijani manat.
	public const BOSNIA_AND_HERZEGOVINA_CONVERTIBLE_MARK = 'BAM'; // Bosnia and Herzegovina convertible mark.
	public const BARBADOS_DOLLAR                         = 'BBD'; // Barbados dollar.
	public const BANGLADESHI_TAKA                        = 'BDT'; // Bangladeshi taka.
	public const BULGARIAN_LEV                           = 'BGN'; // Bulgarian lev.
	public const BAHRAINI_DINAR                          = 'BHD'; // Bahraini dinar.
	public const BURUNDIAN_FRANC                         = 'BIF'; // Burundian franc.
	public const BERMUDIAN_DOLLAR                        = 'BMD'; // Bermudian dollar.
	public const BRUNEI_DOLLAR                           = 'BND'; // Brunei dollar.
	public const BOLIVIANO                               = 'BOB'; // Boliviano.
	public const BRAZILIAN_REAL                          = 'BRL'; // Brazilian real.
	public const BAHAMIAN_DOLLAR                         = 'BSD'; // Bahamian dollar.
	public const BOTSWANA_PULA                           = 'BWP'; // Botswana pula.
	public const NEW_BELARUSIAN_RUBLE                    = 'BYN'; // New Belarusian ruble.
	public const BELIZE_DOLLAR                           = 'BZD'; // Belize dollar.
	public const CANADIAN_DOLLAR                         = 'CAD'; // Canadian dollar.
	public const CONGOLESE_FRANC                         = 'CDF'; // Congolese franc.
	public const SWISS_FRANC                             = 'CHF'; // Swiss franc.
	public const CHILEAN_PESO                            = 'CLP'; // Chilean peso.
	public const CHINESE_YUAN                            = 'CNY'; // Renminbi (Chinese yuan).
	public const COLOMBIAN_PESO                          = 'COP'; // Colombian peso.
	public const COSTA_RICAN_COLON                       = 'CRC'; // Costa Rican colon.
	public const CAPE_VERDE_ESCUDO                       = 'CVE'; // Cape Verde escudo.
	public const CZECH_KORUNA                            = 'CZK'; // Czech koruna.
	public const DJIBOUTIAN_FRANC                        = 'DJF'; // Djiboutian franc.
	public const DANISH_KRONE                            = 'DKK'; // Danish krone.
	public const DOMINICAN_PESO                          = 'DOP'; // Dominican peso.
	public const ALGERIAN_DINAR                          = 'DZD'; // Algerian dinar.
	public const EGYPTIAN_POUND                          = 'EGP'; // Egyptian pound.
	public const ETHIOPIAN_BIRR                          = 'ETB'; // Ethiopian birr.
	public const EURO                                    = 'EUR'; // Euro.
	public const FIJI_DOLLAR                             = 'FJD'; // Fiji dollar.
	public const FALKLAND_ISLANDS_POUND                  = 'FKP'; // Falkland Islands pound.
	public const POUND_STERLING                          = 'GBP'; // Pound sterling.
	public const GEORGIAN_LARI                           = 'GEL'; // Georgian lari.
	public const GIBRALTAR_POUND                         = 'GIP'; // Gibraltar pound.
	public const GAMBIAN_DALASI                          = 'GMD'; // Gambian dalasi.
	public const GUINEAN_FRANC                           = 'GNF'; // Guinean franc.
	public const GUATEMALAN_QUETZAL                      = 'GTQ'; // Guatemalan quetzal.
	public const GUYANESE_DOLLAR                         = 'GYD'; // Guyanese dollar.
	public const HONG_KONG_DOLLAR                        = 'HKD'; // Hong Kong dollar.
	public const HONDURAN_LEMPIRA                        = 'HNL'; // Honduran lempira.
	public const HAITIAN_GOURDE                          = 'HTG'; // Haitian gourde.
	public const HUNGARIAN_FORINT                        = 'HUF'; // Hungarian forint.
	public const INDONESIAN_RUPIAH                       = 'IDR'; // Indonesian rupiah.
	public const ISRAELI_NEW_SHEKEL                      = 'ILS'; // Israeli new shekel.
	public const INDIAN_RUPEE                            = 'INR'; // Indian rupee.
	public const ICELANDIC_KRONA                         = 'ISK'; // Icelandic króna.
	public const JAMAICAN_DOLLAR                         = 'JMD'; // Jamaican dollar.
	public const JORDANIAN_DINAR                         = 'JOD'; // Jordanian dinar.
	public const JAPANESE_YEN                            = 'JPY'; // Japanese yen.
	public const KENYAN_SHILLING                         = 'KES'; // Kenyan shilling.
	public const KYRGYZSTANI_SOM                         = 'KGS'; // Kyrgyzstani som.
	public const CAMBODIAN_RIEL                          = 'KHR'; // Cambodian riel.
	public const COMORIAN_FRANC                          = 'KMF'; // Comorian franc.
	public const SOUTH_KOREAN_WON                        = 'KRW'; // South Korean won.
	public const KUWAITI_DINAR                           = 'KWD'; // Kuwaiti dinar.
	public const CAYMAN_ISLANDS_DOLLAR                   = 'KYD'; // Cayman Islands dollar.
	public const KAZAKHSTANI_TENGE                       = 'KZT'; // Kazakhstani tenge.
	public const LAO_KIP                                 = 'LAK'; // Lao kip.
	public const LEBANESE_POUND                          = 'LBP'; // Lebanese pound.
	public const SRI_LANKAN_RUPEE                        = 'LKR'; // Sri Lankan rupee.
	public const LIBERIAN_DOLLAR                         = 'LRD'; // Liberian dollar.
	public const LESOTHO_LOTI                            = 'LSL'; // Lesotho loti.
	public const MOROCCAN_DIRHAM                         = 'MAD'; // Moroccan dirham.
	public const MOLDOVAN_LEU                            = 'MDL'; // Moldovan leu.
	public const MALAGASY_ARIARY                         = 'MGA'; // Malagasy ariary.
	public const MACEDONIAN_DENAR                        = 'MKD'; // Macedonian denar.
	public const MYANMAR_KYAT                            = 'MMK'; // Myanmar kyat.
	public const MONGOLIAN_TOGROG                        = 'MNT'; // Mongolian tögrög.
	public const MACANESE_PATACA                         = 'MOP'; // Macanese pataca.
	public const MAURITIAN_RUPEE                         = 'MUR'; // Mauritian rupee.
	public const MALDIVIAN_RUFIYAA                       = 'MVR'; // Maldivian rufiyaa.
	public const MALAWIAN_KWACHA                         = 'MWK'; // Malawian kwacha.
	public const MEXICAN_PESO                            = 'MXN'; // Mexican peso.
	public const MALAYSIAN_RINGGIT                       = 'MYR'; // Malaysian ringgit.
	public const MOZAMBICAN_METICAL                      = 'MZN'; // Mozambican metical.
	public const NAMIBIAN_DOLLAR                         = 'NAD'; // Namibian dollar.
	public const NIGERIAN_NAIRA                          = 'NGN'; // Nigerian naira.
	public const NICARAGUAN_CORDOBA                      = 'NIO'; // Nicaraguan córdoba.
	public const NORWEGIAN_KRONE                         = 'NOK'; // Norwegian krone.
	public const NEPALESE_RUPEE                          = 'NPR'; // Nepalese rupee.
	public const NEW_ZEALAND_DOLLAR                      = 'NZD'; // New Zealand dollar.
	public const OMANI_RIAL                              = 'OMR'; // Omani rial.
	public const PANAMANIAN_BALBOA                       = 'PAB'; // Panamanian balboa.
	public const PERUVIAN_SOL                            = 'PEN'; // Peruvian sol.
	public const PAPUA_NEW_GUINEAN_KINA                  = 'PGK'; // Papua New Guinean kina.
	public const PHILIPPINE_PESO                         = 'PHP'; // Philippine peso.
	public const PAKISTANI_RUPEE                         = 'PKR'; // Pakistani rupee.
	public const POLISH_ZLOTY                            = 'PLN'; // Polish złoty.
	public const PARAGUAYAN_GUARANI                      = 'PYG'; // Paraguayan guaraní.
	public const QATARI_RIYAL                            = 'QAR'; // Qatari riyal.
	public const ROMANIAN_LEU                            = 'RON'; // Romanian leu.
	public const SERBIAN_DINAR                           = 'RSD'; // Serbian dinar.
	public const RUSSIAN_RUBLE                           = 'RUB'; // Russian ruble.
	public const RWANDAN_FRANC                           = 'RWF'; // Rwandan franc.
	public const SAUDI_RIYAL                             = 'SAR'; // Saudi riyal.
	public const SOLOMON_ISLANDS_DOLLAR                  = 'SBD'; // Solomon Islands dollar.
	public const SEYCHELLOIS_RUPEE                       = 'SCR'; // Seychellois rupee.
	public const SWEDISH_KRONA                           = 'SEK'; // Swedish krona.
	public const SINGAPORE_DOLLAR                        = 'SGD'; // Singapore dollar.
	public const SAINT_HELENA_POUND                      = 'SHP'; // Saint Helena pound.
	public const SIERRA_LEONEAN_LEONE                    = 'SLE'; // Sierra Leonean leone.
	public const SOMALI_SHILLING                         = 'SOS'; // Somali shilling.
	public const SURINAMESE_DOLLAR                       = 'SRD'; // Surinamese dollar.
	public const SAO_TOME_AND_PRINCIPE_DOBRA             = 'STD'; // São Tomé and Príncipe dobra.
	public const SWAZI_LILANGENI                         = 'SZL'; // Swazi lilangeni.
	public const THAI_BAHT                               = 'THB'; // Thai baht.
	public const TAJIKISTANI_SOMONI                      = 'TJS'; // Tajikistani somoni.
	public const TUNISIAN_DINAR                          = 'TND'; // Tunisian dinar.
	public const TONGAN_PAANGA                           = 'TOP'; // Tongan paʻanga.
	public const TURKISH_LIRA                            = 'TRY'; // Turkish lira.
	public const TRINIDAD_AND_TOBAGO_DOLLAR              = 'TTD'; // Trinidad and Tobago dollar.
	public const NEW_TAIWAN_DOLLAR                       = 'TWD'; // New Taiwan dollar.
	public const TANZANIAN_SHILLING                      = 'TZS'; // Tanzanian shilling.
	public const UKRAINIAN_HRYVNIA                       = 'UAH'; // Ukrainian hryvnia.
	public const UGANDAN_SHILLING                        = 'UGX'; // Ugandan shilling.
	public const URUGUAYAN_PESO                          = 'UYU'; // Uruguayan peso.
	public const UZBEKISTANI_SOM                         = 'UZS'; // Uzbekistani som.
	public const VIETNAMESE_DONG                         = 'VND'; // Vietnamese đồng.
	public const VANUATU_VATU                            = 'VUV'; // Vanuatu vatu.
	public const SAMOAN_TALA                             = 'WST'; // Samoan tala.
	public const CENTRAL_AFRICAN_CFA_FRANC               = 'XAF'; // Central African CFA franc.
	public const EAST_CARIBBEAN_DOLLAR                   = 'XCD'; // East Caribbean dollar.
	public const WEST_AFRICAN_CFA_FRANC                  = 'XOF'; // West African CFA franc.
	public const CFP_FRANC                               = 'XPF'; // CFP franc.
	public const YEMENI_RIAL                             = 'YER'; // Yemeni rial.
	public const SOUTH_AFRICAN_RAND                      = 'ZAR'; // South African rand.
	public const ZAMBIAN_KWACHA                          = 'ZMW'; // Zambian kwacha.

	// ... add more currencies as needed.
	// crypto currencies.
	public const BITCOIN = 'BTC'; // Bitcoin.

	/**
	 * List of currency codes that do not have a decimal component (i.e., no cents).
	 *
	 * Source: https://stripe.com/docs/currencies#zero-decimal
	 *
	 * @var string[]
	 */
	public const NO_DECIMAL_CURRENCY_CODES = [
		self::BURUNDIAN_FRANC,
		self::CHILEAN_PESO,
		self::DJIBOUTIAN_FRANC,
		self::GUINEAN_FRANC,
		self::JAPANESE_YEN,
		self::COMORIAN_FRANC,
		self::SOUTH_KOREAN_WON,
		self::MALAGASY_ARIARY,
		self::PARAGUAYAN_GUARANI,
		self::RWANDAN_FRANC,
		self::VIETNAMESE_DONG,
		self::VANUATU_VATU,
		self::CENTRAL_AFRICAN_CFA_FRANC,
		self::WEST_AFRICAN_CFA_FRANC,
		self::CFP_FRANC,
	];

	/**
	 * List of currency codes that have three decimal places.
	 *
	 * @var string[]
	 */
	public const THREE_DECIMAL_CURRENCY_CODES = [
		self::BAHRAINI_DINAR,
		self::JORDANIAN_DINAR,
		self::KUWAITI_DINAR,
		self::OMANI_RIAL,
		self::TUNISIAN_DINAR,
	];
}
