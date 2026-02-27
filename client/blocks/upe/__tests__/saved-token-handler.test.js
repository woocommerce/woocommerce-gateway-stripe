import { render } from '@testing-library/react';
import { SavedTokenHandler } from 'wcstripe/blocks/upe/saved-token-handler';
import { usePaymentCompleteHandler } from 'wcstripe/blocks/upe/hooks';

jest.mock( 'wcstripe/blocks/upe/hooks' );

describe( 'SavedTokenHandler', () => {
	const api = {};
	const stripe = {};
	const elements = {};
	const emitResponse = {};
	const onCheckoutSuccess = jest.fn();

	beforeEach( () => {
		usePaymentCompleteHandler.mockImplementation( () => {} );
	} );

	it( 'renders without errors', () => {
		expect( () =>
			render(
				<SavedTokenHandler
					api={ api }
					stripe={ stripe }
					elements={ elements }
					eventRegistration={ { onCheckoutSuccess } }
					emitResponse={ emitResponse }
				/>
			)
		).not.toThrow();
	} );

	it( 'calls usePaymentCompleteHandler with onCheckoutSuccess', () => {
		render(
			<SavedTokenHandler
				api={ api }
				stripe={ stripe }
				elements={ elements }
				eventRegistration={ { onCheckoutSuccess } }
				emitResponse={ emitResponse }
			/>
		);

		expect( usePaymentCompleteHandler ).toHaveBeenCalledWith(
			api,
			stripe,
			elements,
			onCheckoutSuccess,
			emitResponse,
			false
		);
	} );

	it( 'does not save the payment when handling a saved token', () => {
		render(
			<SavedTokenHandler
				api={ api }
				stripe={ stripe }
				elements={ elements }
				eventRegistration={ { onCheckoutSuccess } }
				emitResponse={ emitResponse }
			/>
		);

		const [ , , , , , shouldSavePayment ] =
			usePaymentCompleteHandler.mock.calls[ 0 ];
		expect( shouldSavePayment ).toBe( false );
	} );
} );
