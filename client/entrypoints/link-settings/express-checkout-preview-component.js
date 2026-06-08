/* global wc_stripe_link_settings_params */

import { useState, useMemo } from 'react';
import { Elements, ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { __ } from '@wordpress/i18n';
import { getDefaultBorderRadius } from 'wcstripe/express-checkout/utils';
import InlineNotice from 'components/inline-notice';
import { PAYMENT_METHOD_LINK } from 'wcstripe/stripe-utils/constants';

const buttonSizeToPxMap = {
	small: 40,
	default: 48,
	large: 56,
};

const ExpressCheckoutPreviewComponent = ( { size } ) => {
	const [ canRenderButtons, setCanRenderButtons ] = useState( true );

	/* eslint-disable camelcase */
	const stripePromise = useMemo( () => {
		return loadStripe( wc_stripe_link_settings_params.key, {
			locale: wc_stripe_link_settings_params.locale,
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
		paymentMethodTypes: [ PAYMENT_METHOD_LINK, 'card' ],
	};

	const height = buttonSizeToPxMap[ size ] || buttonSizeToPxMap.default;

	const buttonOptions = {
		// Stripe's ExpressCheckoutElement enforces a max buttonHeight of 55px.
		buttonHeight: Math.min( Math.max( height, 40 ), 55 ),
		paymentMethods: {
			link: 'auto',
			amazonPay: 'never',
			googlePay: 'never',
			applePay: 'never',
			klarna: 'never',
		},
		layout: { overflow: 'never' },
	};

	const onReady = ( availablePaymentMethods ) => {
		if ( availablePaymentMethods ) {
			setCanRenderButtons( true );
		} else {
			setCanRenderButtons( false );
		}
	};

	if ( canRenderButtons ) {
		return (
			<div style={ { minHeight: `${ height }px`, width: '100%' } }>
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
				'Failed to preview the Link by Stripe button. ' +
					'Ensure your store uses HTTPS on a publicly available domain.',
				'woocommerce-gateway-stripe'
			) }
		</InlineNotice>
	);
};

export default ExpressCheckoutPreviewComponent;
