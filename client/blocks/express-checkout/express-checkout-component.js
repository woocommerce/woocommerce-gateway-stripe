import { ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { useExpressCheckout } from './hooks';
import { PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT } from './constants';
import { useCallback, useMemo } from '@wordpress/element';
import {
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from 'wcstripe/express-checkout/event-handler';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
} from 'wcstripe/stripe-utils/constants';
import { diagnostics } from 'wcstripe/diagnostics/wiring';

const getPaymentMethodsOverride = ( enabledPaymentMethod ) => {
	const allDisabled = {
		amazonPay: 'never',
		applePay: 'never',
		googlePay: 'never',
		link: 'never',
		paypal: 'never',
	};

	const enabledParam = [
		EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
		EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
	].includes( enabledPaymentMethod )
		? 'always'
		: 'auto';

	return {
		paymentMethods: {
			...allDisabled,
			[ enabledPaymentMethod ]: enabledParam,
		},
	};
};

// Visual adjustments to horizontally align the buttons. Returns a copy — mutating
// the memoised buttonOptions would re-apply these deltas every render.
const adjustButtonHeights = ( buttonOptions, expressPaymentMethod ) => {
	let buttonHeight = buttonOptions.buttonHeight;

	// Apple Pay has a nearly imperceptible height difference. We increase it by 0.4px here.
	if (
		buttonOptions.buttonTheme.applePay === 'black' &&
		expressPaymentMethod === EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY
	) {
		buttonHeight = buttonHeight + 0.4;
	}

	return {
		...buttonOptions,
		// Clamp the button height to the allowed range 40px to 55px.
		buttonHeight: Math.max( 40, Math.min( buttonHeight, 55 ) ),
	};
};

const ExpressCheckoutComponent = ( {
	api,
	billing,
	shippingData,
	setExpressPaymentError,
	onClick,
	onClose,
	expressPaymentMethod = '',
} ) => {
	const { buttonOptions, onButtonClick, onConfirm, onCancel, elements } =
		useExpressCheckout( {
			api,
			billing,
			shippingData,
			onClick,
			onClose,
			setExpressPaymentError,
			expressPaymentMethod,
		} );

	const onShippingAddressChange = useCallback(
		( event ) => shippingAddressChangeHandler( event, elements ),
		[ elements ]
	);

	const onShippingRateChange = useCallback(
		( event ) => shippingRateChangeHandler( event, elements ),
		[ elements ]
	);

	const onElementsReady = useCallback(
		( event ) => {
			const paymentMethodContainer = document.getElementById(
				`express-payment-method-${ PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT }_${ expressPaymentMethod }`
			);

			const availablePaymentMethods = event.availablePaymentMethods || {};

			if (
				paymentMethodContainer &&
				! availablePaymentMethods[ expressPaymentMethod ]
			) {
				paymentMethodContainer.remove();
			}
		},
		[ expressPaymentMethod ]
	);

	const handleClick = useCallback(
		( event ) => {
			diagnostics.recordExpressEvent( 'click', event );
			return onButtonClick( event );
		},
		[ onButtonClick ]
	);

	const handleConfirm = useCallback(
		( event ) => {
			diagnostics.recordExpressEvent( 'confirm', event );
			return onConfirm( event );
		},
		[ onConfirm ]
	);

	const handleCancel = useCallback(
		( event ) => {
			diagnostics.recordExpressEvent( 'cancel', event );
			return onCancel( event );
		},
		[ onCancel ]
	);

	const handleShippingAddressChange = useCallback(
		( event ) => {
			diagnostics.recordExpressEvent( 'shippingaddresschange', event );
			return onShippingAddressChange( event );
		},
		[ onShippingAddressChange ]
	);

	const handleShippingRateChange = useCallback(
		( event ) => {
			diagnostics.recordExpressEvent( 'shippingratechange', event );
			return onShippingRateChange( event );
		},
		[ onShippingRateChange ]
	);

	// Stable across cart ticks; only rebuilds when styling or method changes.
	const elementOptions = useMemo(
		() => ( {
			...adjustButtonHeights( buttonOptions, expressPaymentMethod ),
			...getPaymentMethodsOverride( expressPaymentMethod ),
		} ),
		[ buttonOptions, expressPaymentMethod ]
	);

	return (
		<ExpressCheckoutElement
			options={ elementOptions }
			onClick={ handleClick }
			onConfirm={ handleConfirm }
			onReady={ onElementsReady }
			onCancel={ handleCancel }
			onShippingAddressChange={ handleShippingAddressChange }
			onShippingRateChange={ handleShippingRateChange }
		/>
	);
};

export default ExpressCheckoutComponent;
