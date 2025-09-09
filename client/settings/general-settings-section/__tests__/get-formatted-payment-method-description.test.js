import { getFormattedPaymentMethodDescription } from '../get-formatted-payment-method-description';
import {
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_AFTERPAY_CLEARPAY,
	PAYMENT_METHOD_ALIPAY,
} from 'wcstripe/stripe-utils/constants';

describe( 'getFormattedPaymentMethodDescription', () => {
	it( 'should format Affirm description with USD currency', () => {
		const result = getFormattedPaymentMethodDescription(
			PAYMENT_METHOD_AFFIRM,
			'usd'
		);
		expect( result ).toBe(
			'Allow customers to pay over time. Available to all customers paying in USD. Purchases from 35 USD to 30,000 USD are eligible for Affirm financing.'
		);
	} );

	it( 'should format Affirm description with CAD currency', () => {
		const result = getFormattedPaymentMethodDescription(
			PAYMENT_METHOD_AFFIRM,
			'cad'
		);
		expect( result ).toBe(
			'Allow customers to pay over time. Available to all customers paying in CAD. Purchases from 50 CAD to 30,000 CAD are eligible for Affirm financing.'
		);
	} );

	it( 'should return interpolated description for Afterpay/Clearpay', () => {
		const result = getFormattedPaymentMethodDescription(
			PAYMENT_METHOD_AFTERPAY_CLEARPAY
		);
		expect( result ).toEqual(
			expect.arrayContaining( [
				'Allow customers to pay over time with Afterpay. ',
			] )
		);
	} );

	it( 'should return default description for other payment methods', () => {
		const result = getFormattedPaymentMethodDescription(
			PAYMENT_METHOD_ALIPAY
		);
		expect( result ).toBe(
			'Alipay is a popular wallet in China, operated by Ant Financial Services Group, a financial services provider affiliated with Alibaba.'
		);
	} );
} );
