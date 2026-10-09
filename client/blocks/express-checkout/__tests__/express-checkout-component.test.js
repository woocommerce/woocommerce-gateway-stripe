import { render } from '@testing-library/react';
import { ExpressCheckoutElement } from '@stripe/react-stripe-js';
import ExpressCheckoutComponent from '../express-checkout-component';
import { useExpressCheckout } from '../hooks';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
} from 'wcstripe/stripe-utils/constants';

jest.mock( '@stripe/react-stripe-js', () => ( {
	ExpressCheckoutElement: jest.fn( () => <div /> ),
} ) );

jest.mock( '../hooks', () => ( {
	useExpressCheckout: jest.fn(),
} ) );

jest.mock( 'wcstripe/express-checkout/event-handler', () => ( {
	shippingAddressChangeHandler: jest.fn(),
	shippingRateChangeHandler: jest.fn(),
} ) );

const componentProps = {
	api: {},
	billing: {},
	shippingData: {},
	setExpressPaymentError: jest.fn(),
	onClick: jest.fn(),
	onClose: jest.fn(),
};

beforeEach( () => {
	jest.clearAllMocks();
} );

const getElementOptions = ( {
	expressPaymentMethod,
	buttonHeight,
	buttonTheme,
} ) => {
	useExpressCheckout.mockReturnValue( {
		buttonOptions: { buttonHeight, buttonTheme },
		onButtonClick: jest.fn(),
		onConfirm: jest.fn(),
		onCancel: jest.fn(),
		elements: {},
	} );

	render(
		<ExpressCheckoutComponent
			{ ...componentProps }
			expressPaymentMethod={ expressPaymentMethod }
		/>
	);

	expect( ExpressCheckoutElement ).toHaveBeenCalledTimes( 1 );
	return ExpressCheckoutElement.mock.calls[ 0 ][ 0 ].options;
};

// The merchant picks one theme for the whole row, which Stripe applies per method.
// Google Pay has no outlined variant, so both Light and Outline send it 'white'.
const whiteGooglePayThemes = [
	[ 'Light', { applePay: 'white', googlePay: 'white' } ],
	[ 'Outline', { applePay: 'white-outline', googlePay: 'white' } ],
];

describe.each( whiteGooglePayThemes )(
	'ExpressCheckoutComponent with the %s theme',
	( _themeName, buttonTheme ) => {
		// Above the 40px floor the clamp no longer hides a downward adjustment, so
		// these heights are the ones that expose a Google Pay/Apple Pay mismatch.
		it.each( [ 44, 48, 55 ] )(
			'renders Google Pay at the shared %dpx height',
			( buttonHeight ) => {
				const options = getElementOptions( {
					expressPaymentMethod:
						EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
					buttonHeight,
					buttonTheme,
				} );

				expect( options.buttonHeight ).toBe( buttonHeight );
			}
		);

		it.each( [ 44, 48, 55 ] )(
			'renders Apple Pay at the same shared %dpx height',
			( buttonHeight ) => {
				const options = getElementOptions( {
					expressPaymentMethod:
						EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
					buttonHeight,
					buttonTheme,
				} );

				expect( options.buttonHeight ).toBe( buttonHeight );
			}
		);
	}
);

describe( 'ExpressCheckoutComponent with the Dark theme', () => {
	const buttonTheme = { applePay: 'black', googlePay: 'black' };

	it( 'keeps the black Apple Pay compensation', () => {
		const options = getElementOptions( {
			expressPaymentMethod: EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
			buttonHeight: 48,
			buttonTheme,
		} );

		expect( options.buttonHeight ).toBe( 48.4 );
	} );

	it( 'clamps the compensated Apple Pay height to the 55px maximum', () => {
		const options = getElementOptions( {
			expressPaymentMethod: EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
			buttonHeight: 55,
			buttonTheme,
		} );

		expect( options.buttonHeight ).toBe( 55 );
	} );

	it( 'leaves Google Pay at the shared height', () => {
		const options = getElementOptions( {
			expressPaymentMethod: EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
			buttonHeight: 48,
			buttonTheme,
		} );

		expect( options.buttonHeight ).toBe( 48 );
	} );
} );

describe( 'ExpressCheckoutComponent height clamping', () => {
	it( 'clamps a below-minimum height to 40px', () => {
		const options = getElementOptions( {
			expressPaymentMethod: EXPRESS_PAYMENT_METHOD_SETTING_GOOGLE_PAY,
			buttonHeight: 32,
			buttonTheme: { applePay: 'white', googlePay: 'white' },
		} );

		expect( options.buttonHeight ).toBe( 40 );
	} );
} );

describe( 'ExpressCheckoutComponent availability', () => {
	const renderInBlockSlot = ( onAvailabilityChange ) => {
		useExpressCheckout.mockReturnValue( {
			buttonOptions: { buttonHeight: 48, buttonTheme: {} },
			onButtonClick: jest.fn(),
			onConfirm: jest.fn(),
			onCancel: jest.fn(),
			elements: {},
		} );

		const list = document.createElement( 'ul' );
		const slot = document.createElement( 'li' );
		slot.id = 'express-payment-method-express_checkout_element_applePay';
		list.appendChild( slot );
		document.body.appendChild( list );

		render(
			<ExpressCheckoutComponent
				{ ...componentProps }
				expressPaymentMethod={
					EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY
				}
				onAvailabilityChange={ onAvailabilityChange }
			/>,
			{ container: slot }
		);

		return { list, slot };
	};

	const fireReady = ( availablePaymentMethods ) =>
		ExpressCheckoutElement.mock.lastCall[ 0 ].onReady( {
			availablePaymentMethods,
		} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'reports an unavailable wallet without removing the block slot', () => {
		const onAvailabilityChange = jest.fn();
		const { list, slot } = renderInBlockSlot( onAvailabilityChange );

		fireReady( { applePay: false } );

		expect( onAvailabilityChange ).toHaveBeenLastCalledWith( false );
		expect( list.contains( slot ) ).toBe( true );
	} );

	it( 'reports an available wallet', () => {
		const onAvailabilityChange = jest.fn();
		renderInBlockSlot( onAvailabilityChange );

		fireReady( { applePay: true } );

		expect( onAvailabilityChange ).toHaveBeenLastCalledWith( true );
	} );
} );
