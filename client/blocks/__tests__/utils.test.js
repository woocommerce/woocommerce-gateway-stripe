import { render } from '@testing-library/react';
import {
	extractOrderAttributionData,
	populateOrderAttributionInputs,
} from 'wcstripe/blocks/utils';

describe( 'Blocks Utils', () => {
	describe( 'extractOrderAttributionData', () => {
		it( 'order attribution wrapper not found', () => {
			const data = extractOrderAttributionData();
			expect( data ).toStrictEqual( {} );
		} );

		it( 'order attribution wrapper exists', () => {
			render(
				<wc-order-attribution-inputs>
					<input name="foo" defaultValue="bar" />
					<input name="baz" defaultValue="qux" />
				</wc-order-attribution-inputs>
			);

			const data = extractOrderAttributionData();
			expect( data ).toStrictEqual( {
				foo: 'bar',
				baz: 'qux',
			} );
		} );
	} );

	describe( 'populateOrderAttributionInputs', () => {
		test( 'order attribution global present', () => {
			global.wc_order_attribution = {
				params: {
					allowTracking: true,
				},
				setOrderTracking: jest.fn(),
			};

			populateOrderAttributionInputs();

			expect(
				global.wc_order_attribution.setOrderTracking
			).toHaveBeenCalledWith( true );
		} );
	} );
} );
