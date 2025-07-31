import { trackOCToggle } from '../track-oc-toggle';
import { recordEvent } from 'wcstripe/tracking';

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'trackOCToggle', () => {
	it( 'should track enablement', () => {
		trackOCToggle( true, 'test' );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_optimized_checkout_enabled',
			{
				source: 'test',
			}
		);
	} );
	it( 'should track disablement', () => {
		trackOCToggle( false, 'test' );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_optimized_checkout_disabled',
			{
				source: 'test',
			}
		);
	} );
} );
