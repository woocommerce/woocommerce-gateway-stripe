import { waitForPaymentElementCompletion } from 'wcstripe/blocks/wait-for-payment-element-completion';

// #5490: On Blocks checkout the Payment Element can briefly re-mount (e.g. when
// a cart update flips the amount mode), resetting its completion state. The
// helper gives an in-flight (re)mount a short window to settle before a
// submission is rejected, reading completeness from a live ref.
describe( 'waitForPaymentElementCompletion', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'resolves true immediately when the element is already complete', async () => {
		const ref = { current: true };
		await expect( waitForPaymentElementCompletion( ref ) ).resolves.toBe(
			true
		);
	} );

	it( 'resolves true once the element becomes complete within the timeout', async () => {
		const ref = { current: false };
		const promise = waitForPaymentElementCompletion( ref, {
			timeoutMs: 1000,
			intervalMs: 50,
		} );

		// Element finishes (re)mounting after a couple of poll intervals.
		jest.advanceTimersByTime( 100 );
		ref.current = true;
		jest.advanceTimersByTime( 50 );

		await expect( promise ).resolves.toBe( true );
	} );

	it( 'resolves false when the element never completes before the timeout', async () => {
		const ref = { current: false };
		const promise = waitForPaymentElementCompletion( ref, {
			timeoutMs: 200,
			intervalMs: 50,
		} );

		jest.advanceTimersByTime( 200 );

		await expect( promise ).resolves.toBe( false );
	} );

	it( 'resolves false for a missing ref after the timeout', async () => {
		const promise = waitForPaymentElementCompletion( undefined, {
			timeoutMs: 100,
			intervalMs: 50,
		} );

		jest.advanceTimersByTime( 100 );

		await expect( promise ).resolves.toBe( false );
	} );
} );
