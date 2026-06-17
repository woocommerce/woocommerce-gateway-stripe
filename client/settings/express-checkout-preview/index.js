import { useState, useMemo } from 'react';
import { Elements, ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { __ } from '@wordpress/i18n';
import { getDefaultBorderRadius } from 'wcstripe/express-checkout/utils';
import InlineNotice from 'components/inline-notice';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
} from 'wcstripe/stripe-utils/constants';

const buttonSizeToPxMap = {
	small: 40,
	default: 48,
	large: 56,
};

const mapThemeConfigToButtonTheme = ( paymentMethod, buttonTheme ) => {
	switch ( buttonTheme ) {
		case 'dark':
			return 'black';
		case 'light':
			return 'white';
		case 'light-outline':
			if ( paymentMethod === EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY ) {
				return 'white';
			}
			return 'white-outline';
		default:
			return 'black';
	}
};

/**
 * Shared admin preview of the Stripe Express Checkout Element, parameterized per
 * settings page (Express Checkout, Amazon Pay, Link). Admin-only; not used on any
 * checkout surface.
 *
 * @param {Object}   props
 * @param {Object}   props.params                          Localized params for the page, `{ key, locale }`.
 * @param {string[]} props.paymentMethodTypes              `paymentMethodTypes` for the Elements provider.
 * @param {Object}   props.paymentMethods                  `paymentMethods` flags for the ExpressCheckoutElement.
 * @param {string}   props.size                            Button size key (`small`/`default`/`large`).
 * @param {string}   [props.buttonType]                    Button type; when paired with `theme`, sets the button type/theme. Express Checkout page only.
 * @param {string}   [props.theme]                         Button theme; see `buttonType`.
 * @param {boolean}  [props.requireExpressCheckoutEnabled] Gate the preview behind `isExpressCheckoutEnabled`. Express Checkout page only.
 * @param {boolean}  [props.isExpressCheckoutEnabled]      Current express-checkout-enabled state; only consulted when the gate is required. Owned by the caller so this component stays free of page-specific data stores.
 * @param {string}   props.errorMessage                    Notice shown when no payment method can be previewed.
 */
const ExpressCheckoutPreview = ( {
	params,
	paymentMethodTypes,
	paymentMethods,
	size,
	buttonType,
	theme,
	requireExpressCheckoutEnabled = false,
	isExpressCheckoutEnabled = false,
	errorMessage,
} ) => {
	const [ canRenderButtons, setCanRenderButtons ] = useState( true );

	const stripePromise = useMemo( () => {
		return loadStripe( params.key, { locale: params.locale } );
	}, [ params.key, params.locale ] );

	const options = {
		mode: 'payment',
		amount: 1000,
		currency: 'usd',
		appearance: {
			variables: {
				borderRadius: `${ getDefaultBorderRadius() }px`,
				spacingUnit: '6px',
			},
		},
		paymentMethodTypes,
	};

	const height = buttonSizeToPxMap[ size ] || buttonSizeToPxMap.default;

	const buttonOptions = {
		// Stripe's ExpressCheckoutElement enforces a max buttonHeight of 55px.
		buttonHeight: Math.min( Math.max( height, 40 ), 55 ),
		paymentMethods,
		layout: { overflow: 'never' },
	};

	// The button type/theme controls only exist on the Express Checkout page; the
	// Amazon Pay and Link previews leave them unset so Stripe uses its defaults.
	if ( buttonType && theme ) {
		const type = buttonType === 'default' ? 'plain' : buttonType;
		buttonOptions.buttonType = {
			googlePay: type,
			applePay: type,
		};
		buttonOptions.buttonTheme = {
			googlePay: mapThemeConfigToButtonTheme(
				EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
				theme
			),
			applePay: mapThemeConfigToButtonTheme(
				EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
				theme
			),
		};
	}

	const onReady = ( { availablePaymentMethods } ) => {
		setCanRenderButtons( Boolean( availablePaymentMethods ) );
	};

	if ( requireExpressCheckoutEnabled && ! isExpressCheckoutEnabled ) {
		return (
			<InlineNotice icon status="warning" isDismissible={ false }>
				{ __(
					'The preview is only available when Apple Pay and Google Pay are enabled. ' +
						'Please enable Apple Pay and Google Pay to see the preview.',
					'woocommerce-gateway-stripe'
				) }
			</InlineNotice>
		);
	}

	if ( canRenderButtons ) {
		return (
			<div
				key={ `${ buttonType }-${ theme }` }
				style={ { minHeight: `${ height }px`, width: '100%' } }
			>
				<Elements stripe={ stripePromise } options={ options }>
					<ExpressCheckoutElement
						options={ buttonOptions }
						onClick={ () => {} }
						onReady={ onReady }
					/>
				</Elements>
			</div>
		);
	}

	return (
		<InlineNotice icon status="error" isDismissible={ false }>
			{ errorMessage }
		</InlineNotice>
	);
};

export default ExpressCheckoutPreview;
