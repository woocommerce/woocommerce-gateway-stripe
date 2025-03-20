import { applySinglePaymentElementStyles } from 'wcstripe/blocks/upe/apply-single-payment-element-styles';

describe( 'applySinglePaymentElementStyles', () => {
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

		applySinglePaymentElementStyles();

		const paymentMethodOptions = document.querySelectorAll(
			'input[name=radio-control-wc-payment-method-options]'
		);
		expect( paymentMethodOptions.length ).toBe( 1 );

		const stripeContent = document.getElementById(
			'radio-control-wc-payment-method-options-stripe__content'
		);
		expect(
			stripeContent.classList.contains( 'single-payment-element' )
		).toBe( true );

		const stripeLabel = document.getElementById(
			'radio-control-wc-payment-method-options-stripe__label'
		);
		expect(
			stripeLabel.classList.contains( 'single-payment-element' )
		).toBe( true );

		const stripeIframe = document.querySelector(
			'.wcstripe-payment-element iframe'
		);
		expect( stripeIframe.style.margin ).toBe( '0px' );
	} );
} );
