import { render, screen } from '@testing-library/react';
import { PaymentElement, useCheckout } from '@stripe/react-stripe-js/checkout';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';

jest.mock( '@stripe/react-stripe-js/checkout', () => ( {
	PaymentElement: jest.fn(),
	useCheckout: jest.fn(),
} ) );

describe( 'CheckoutForm', () => {
	PaymentElement.mockReturnValue( <div>Payment Element</div> );

	const api = {
		checkoutSessionsCreateSession: jest
			.fn()
			.mockResolvedValue( { client_secret: 'test_secret' } ),
	};
	const errorMessage = '';

	const onPaymentSetup = jest.fn();
	const onCheckoutSuccess = jest.fn();
	const onCheckoutFail = jest.fn();
	const eventRegistration = {
		onPaymentSetup,
		onCheckoutSuccess,
		onCheckoutFail,
	};

	const emitResponse = jest.fn();

	const billing = {
		billingAddress: {
			first_name: 'John',
			last_name: 'Doe',
			address_1: '123 Main St',
			address_2: '',
			city: 'Anytown',
			state: 'CA',
			postcode: '12345',
			country: 'US',
		},
	};

	it( 'should render loading state', () => {
		useCheckout.mockReturnValue( {
			checkoutState: {
				type: 'loading',
			},
		} );

		render(
			<CheckoutForm
				api={ api }
				eventRegistration={ eventRegistration }
				emitResponse={ emitResponse }
				errorMessage={ errorMessage }
				billing={ billing }
			/>
		);

		expect( screen.getByText( 'Loading...' ) ).toBeInTheDocument();
	} );

	it( 'should render error state', () => {
		useCheckout.mockReturnValue( {
			checkoutState: {
				type: 'error',
				error: {
					message: 'Test error',
				},
			},
		} );

		render(
			<CheckoutForm
				api={ api }
				eventRegistration={ eventRegistration }
				emitResponse={ emitResponse }
				errorMessage={ errorMessage }
				billing={ billing }
			/>
		);

		expect( screen.getByText( 'Error: Test error' ) ).toBeInTheDocument();
	} );

	it( 'should render the payment element', () => {
		useCheckout.mockReturnValue( {
			checkoutState: {
				type: 'success',
				checkout: {
					id: 'test_checkout_id',
				},
			},
		} );

		render(
			<CheckoutForm
				api={ api }
				eventRegistration={ eventRegistration }
				emitResponse={ emitResponse }
				errorMessage={ errorMessage }
				billing={ billing }
			/>
		);

		expect( screen.getByText( 'Payment Element' ) ).toBeInTheDocument();
	} );
} );
