import { render } from '@testing-library/react';
import { SavedTokenHandler } from 'wcstripe/blocks/upe/saved-token-handler';
import { usePaymentCompleteHandler } from 'wcstripe/blocks/upe/hooks';

jest.mock( 'wcstripe/blocks/upe/hooks' );

describe( 'SavedTokenHandler', () => {
	const api = {};
	const stripe = {};
	const elements = {};
	const onCheckoutAfterProcessingWithSuccess = jest.fn();
	const emitResponse = {};

	it( 'renders without error and calls usePaymentCompleteHandler with correct args', () => {
		render(
			<SavedTokenHandler
				api={ api }
				stripe={ stripe }
				elements={ elements }
				eventRegistration={ { onCheckoutAfterProcessingWithSuccess } }
				emitResponse={ emitResponse }
			/>
		);

		expect( usePaymentCompleteHandler ).toHaveBeenCalledWith(
			api,
			stripe,
			elements,
			onCheckoutAfterProcessingWithSuccess,
			emitResponse,
			false
		);
	} );
} );
