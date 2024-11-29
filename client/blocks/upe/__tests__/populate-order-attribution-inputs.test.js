import { populateOrderAttributionInputs } from 'wcstripe/blocks/upe/populate-order-attribution-inputs';

describe( 'Unified Payment Element (Blocks)', () => {
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
