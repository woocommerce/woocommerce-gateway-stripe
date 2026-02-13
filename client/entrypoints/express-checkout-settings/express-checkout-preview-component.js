/* global wc_stripe_express_checkout_settings_params */

import { useState, useMemo } from 'react';
import { Elements, ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { __ } from '@wordpress/i18n';
import { getDefaultBorderRadius } from 'wcstripe/express-checkout/utils';
import InlineNotice from 'components/inline-notice';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
	PAYMENT_METHOD_CARD,
} from 'wcstripe/stripe-utils/constants';

const buttonSizeToPxMap = {
	small: 40,
	default: 48,
	large: 56,
};

const ExpressCheckoutPreviewComponent = ( { buttonType, theme, size } ) => {
	const [ canRenderButtons, setCanRenderButtons ] = useState( true );

	/* eslint-disable camelcase */
	const stripePromise = useMemo( () => {
		return loadStripe( wc_stripe_express_checkout_settings_params.key, {
			locale: wc_stripe_express_checkout_settings_params.locale,
		} );
	}, [] );
	/* eslint-enable camelcase */

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
		paymentMethodTypes: [ PAYMENT_METHOD_CARD ],
	};

	const height = buttonSizeToPxMap[ size ] || buttonSizeToPxMap.medium;

	const mapThemeConfigToButtonTheme = ( paymentMethod, buttonTheme ) => {
		switch ( buttonTheme ) {
			case 'dark':
				return 'black';
			case 'light':
				return 'white';
			case 'light-outline':
				if (
					paymentMethod === EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY
				) {
					return 'white';
				}
				return 'white-outline';
			default:
				return 'black';
		}
	};

	const type = buttonType === 'default' ? 'plain' : buttonType;

	const buttonOptions = {
		buttonHeight: Math.min( Math.max( height, 40 ), 55 ),
		buttonTheme: {
			googlePay: mapThemeConfigToButtonTheme(
				EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
				theme
			),
			applePay: mapThemeConfigToButtonTheme(
				EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
				theme
			),
		},
		buttonType: {
			googlePay: type,
			applePay: type,
		},
		paymentMethods: {
			link: 'never',
			googlePay: 'always',
			applePay: 'always',
			amazonPay: 'never',
			klarna: 'never',
		},
		layout: { overflow: 'never' },
	};

	const onReady = ( { availablePaymentMethods } ) => {
		if ( availablePaymentMethods ) {
			setCanRenderButtons( true );
		} else {
			setCanRenderButtons( false );
		}
	};

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
			{ __(
				'Failed to preview the Apple Pay or Google Pay button. ' +
					'Ensure your store uses HTTPS on a publicly available domain ' +
					"and you're viewing this page in a Safari or Chrome browser. " +
					'Your device must be configured to use Apple Pay or Google Pay.',
				'woocommerce-gateway-stripe'
			) }
		</InlineNotice>
	);
};

export default ExpressCheckoutPreviewComponent;
