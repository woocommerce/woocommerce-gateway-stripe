import { applySinglePaymentElementStyles } from 'wcstripe/classic/upe/apply-single-payment-element-styles';

describe( 'applySinglePaymentElementStyles', () => {
	it( 'Correctly apply the required styles to HTML elements', () => {
		document.body.innerHTML = `
			<input type="radio" name="payment_method" value="stripe" />
			<label for="payment_method_stripe">Stripe</label>
		`;

		applySinglePaymentElementStyles();

		const stripeLabel = document.querySelector(
			'label[for=payment_method_stripe]'
		);
		expect( stripeLabel.style.display ).toBe( 'none' );
	} );
} );
