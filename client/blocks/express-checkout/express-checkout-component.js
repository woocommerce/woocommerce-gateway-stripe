import { ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { useExpressCheckout } from './hooks';
import { PAYMENT_METHOD_EXPRESS_CHECKOUT_ELEMENT } from './constants';
import {
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from 'wcstripe/express-checkout/event-handler';

const getPaymentMethodsOverride = ( enabledPaymentMethod ) => {
	const allDisabled = {
		amazonPay: 'never',
		applePay: 'never',
		googlePay: 'never',
		link: 'never',
		paypal: 'never',
	};

	const enabledParam = [ 'applePay', 'googlePay' ].includes(
		enabledPaymentMethod
	)
		? 'always'
		: 'auto';

	return {
		paymentMethods: {
			...allDisabled,
			[ enabledPaymentMethod ]: enabledParam,
		},
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
	const {
		buttonOptions,
		onButtonClick,
		onConfirm,
		onCancel,
		elements,
	} = useExpressCheckout( {
		api,
		billing,
		shippingData,
		onClick,
		onClose,
		setExpressPaymentError,
	} );

	const onShippingAddressChange = ( event ) =>
		shippingAddressChangeHandler( api, event, elements );

	const onShippingRateChange = ( event ) =>
		shippingRateChangeHandler( api, event, elements );

	const onElementsReady = ( event ) => {
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
	};

	return (
		<ExpressCheckoutElement
			options={ {
				...buttonOptions,
				...getPaymentMethodsOverride( expressPaymentMethod ),
			} }
			onClick={ onButtonClick }
			onConfirm={ onConfirm }
			onReady={ onElementsReady }
			onCancel={ onCancel }
			// onShippingAddressChange={ onShippingAddressChange }
			onShippingRateChange={ onShippingRateChange }
		/>
	);
};

export default ExpressCheckoutComponent;
