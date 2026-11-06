import { applyStyles } from 'wcstripe/optimized-checkout/apply-styles';

describe( 'applyStyles', () => {
	it( 'Correctly apply the required styles to HTML elements', () => {
		document.body.innerHTML = `
			<label class="wc-block-components-radio-control__option">
				<input type="radio" name="radio-control-wc-payment-method-options" value="stripe" />
			</label>
			<div id="radio-control-wc-payment-method-options-stripe__content"></div>
			<div id="radio-control-wc-payment-method-options-stripe__label"></div>
			<div class="wcstripe-payment-element">
				<iframe></iframe>
			</div>
		`;

		applyStyles();

		const paymentMethodOptions = document.querySelectorAll(
			'input[name=radio-control-wc-payment-method-options]'
		);
		expect( paymentMethodOptions.length ).toBe( 1 );

		const stripeContent = document.getElementById(
			'radio-control-wc-payment-method-options-stripe__content'
		);
		expect(
			stripeContent.classList.contains( 'optimized-checkout-element' )
		).toBe( true );

		const stripeLabel = document.getElementById(
			'radio-control-wc-payment-method-options-stripe__label'
		);
		expect(
			stripeLabel.classList.contains( 'optimized-checkout-element' )
		).toBe( true );

		const stripeIframe = document.querySelector(
			'.wcstripe-payment-element iframe'
		);
		expect( stripeIframe.style.margin ).toBe( '0px' );
	} );

	it( 'does not throw when the Stripe iframe has not rendered yet', () => {
		document.body.innerHTML = `
			<div id="radio-control-wc-payment-method-options-stripe__content"></div>
			<div id="radio-control-wc-payment-method-options-stripe__label"></div>
		`;

		expect( () => applyStyles() ).not.toThrow();
	} );

	it( 'does not throw when the OC content and label nodes are absent', () => {
		// A standalone non-deferred method (BLIK, ACSS) renders as its own payment
		// option, so the OC container's stripe__content / stripe__label nodes are
		// not in the DOM when its processor runs applyStyles().
		document.body.innerHTML =
			'<div class="wcstripe-payment-element"></div>';

		expect( () => applyStyles() ).not.toThrow();
	} );
} );
