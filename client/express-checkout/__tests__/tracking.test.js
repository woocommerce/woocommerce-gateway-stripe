import { trackExpressCheckoutButtonClick } from 'wcstripe/express-checkout/tracking';
import { recordEvent } from 'wcstripe/tracking';

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'Express checkout tracking class', () => {
	describe( 'trackExpressCheckoutButtonClick', () => {
		test( 'no event found to track', () => {
			trackExpressCheckoutButtonClick( 'foo', 'bar' );
			expect( recordEvent ).not.toBeCalled();
		} );
		test( 'should track checkout button click', () => {
			trackExpressCheckoutButtonClick( 'google_pay', 'bar' );
			expect( recordEvent ).toBeCalled();
		} );
	} );
} );
