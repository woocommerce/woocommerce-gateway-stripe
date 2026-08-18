import { render, screen } from '@testing-library/react';
import SavedTokenCheckoutForm from 'wcstripe/blocks/checkout-sessions/saved-token-checkout-form';

jest.mock( '@stripe/react-stripe-js/checkout', () => ( {
	CurrencySelectorElement: jest.fn( () => (
		<div data-testid="currency-selector-element" />
	) ),
	useCheckout: jest
		.fn()
		.mockReturnValue( { type: 'success', checkout: { id: 'cs_test_1' } } ),
} ) );

jest.mock( 'wcstripe/blocks/checkout-sessions/hooks', () => ( {
	useCheckoutSuccessHandler: jest.fn(),
	usePaymentFailHandler: jest.fn(),
	useSavedTokenPaymentSetupHandler: jest.fn(),
	useCheckoutSessionTotalsSync: jest.fn(),
} ) );

describe( 'SavedTokenCheckoutForm', () => {
	const renderForm = ( token ) =>
		render(
			<SavedTokenCheckoutForm
				emitResponse={ {} }
				eventRegistration={ {
					onPaymentSetup: jest.fn(),
					onCheckoutSuccess: jest.fn(),
					onCheckoutFail: jest.fn(),
				} }
				billing={ {} }
				isLoggedIn={ true }
				isPayerPhoneRequired={ false }
				shippingData={ {} }
				cartData={ {} }
				LoadingMask={ null }
				setShouldLoadStripeElements={ jest.fn() }
				token={ token }
				savedPaymentMethodId="pm_saved_card_12"
			/>
		);

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'portals the currency selector into the selected token row, after its label', () => {
		document.body.innerHTML = `
			<div class="wc-block-components-radio-control">
				<label for="radio-control-wc-payment-method-saved-tokens-12">
					<input id="radio-control-wc-payment-method-saved-tokens-12" type="radio" />
				</label>
				<label for="radio-control-wc-payment-method-saved-tokens-34">
					<input id="radio-control-wc-payment-method-saved-tokens-34" type="radio" />
				</label>
			</div>
		`;

		renderForm( 12 );

		const container = document.querySelector(
			'.wc-stripe-saved-token-currency-selector'
		);
		expect( container ).not.toBeNull();
		expect(
			container.previousElementSibling.querySelector(
				'#radio-control-wc-payment-method-saved-tokens-12'
			)
		).not.toBeNull();
		expect(
			container.querySelector(
				'[data-testid="wc-stripe-currency-selector"]'
			)
		).not.toBeNull();
	} );

	it( 'renders the selector inline when the token row cannot be found', () => {
		renderForm( 99 );

		expect(
			document.querySelector( '.wc-stripe-saved-token-currency-selector' )
		).toBeNull();
		expect(
			screen.getByTestId( 'wc-stripe-currency-selector' )
		).toBeInTheDocument();
	} );

	it( 'removes the portal container on unmount', () => {
		document.body.innerHTML = `
			<label for="radio-control-wc-payment-method-saved-tokens-12">
				<input id="radio-control-wc-payment-method-saved-tokens-12" type="radio" />
			</label>
		`;

		const { unmount } = renderForm( 12 );
		expect(
			document.querySelector( '.wc-stripe-saved-token-currency-selector' )
		).not.toBeNull();

		unmount();
		expect(
			document.querySelector( '.wc-stripe-saved-token-currency-selector' )
		).toBeNull();
	} );
} );
