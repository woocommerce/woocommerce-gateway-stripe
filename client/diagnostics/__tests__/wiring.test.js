const mockAttachExpress = jest.fn();
const mockAttach = jest.fn();
const mockRecordBlocksPaymentSetupStart = jest.fn();
const mockRecordBlocksPaymentSetupEnd = jest.fn();
const mockAroundStripeCall = jest.fn();
const mockAttachAfterReady = jest.fn();
const mockRecordExpressEvent = jest.fn();

jest.mock( 'wcstripe/diagnostics/recorder', () => ( {
	getRecorder: () => ( {
		attachExpress: mockAttachExpress,
		attach: mockAttach,
		recordBlocksPaymentSetupStart: mockRecordBlocksPaymentSetupStart,
		recordBlocksPaymentSetupEnd: mockRecordBlocksPaymentSetupEnd,
		aroundStripeCall: mockAroundStripeCall,
		attachAfterReady: mockAttachAfterReady,
		recordExpressEvent: mockRecordExpressEvent,
	} ),
} ) );

import { diagnostics } from 'wcstripe/diagnostics/wiring';

describe( 'diagnostics wiring helpers', () => {
	beforeEach( () => {
		mockAttachExpress.mockClear();
		mockAttach.mockClear();
		mockRecordBlocksPaymentSetupStart.mockClear();
		mockRecordBlocksPaymentSetupEnd.mockClear();
		mockAroundStripeCall.mockClear();
		mockAttachAfterReady.mockClear();
		mockRecordExpressEvent.mockClear();
		delete window.wcStripeDiag;
	} );

	describe( 'attachExpress', () => {
		it( 'calls recorder.attachExpress with the button when wcStripeDiag is active', () => {
			window.wcStripeDiag = { active: true };
			const eceButton = { id: 'fake-ece' };

			diagnostics.attachExpress( eceButton );

			expect( mockAttachExpress ).toHaveBeenCalledTimes( 1 );
			expect( mockAttachExpress ).toHaveBeenCalledWith( eceButton );
		} );

		it( 'does nothing when wcStripeDiag is absent', () => {
			diagnostics.attachExpress( { id: 'fake-ece' } );

			expect( mockAttachExpress ).not.toHaveBeenCalled();
		} );

		it( 'does nothing when wcStripeDiag.active is false', () => {
			window.wcStripeDiag = { active: false };
			diagnostics.attachExpress( { id: 'fake-ece' } );

			expect( mockAttachExpress ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'attach', () => {
		it( 'calls recorder.attach with element and kind when active', () => {
			window.wcStripeDiag = { active: true };
			const element = { id: 'fake-element' };

			diagnostics.attach( element, 'card' );

			expect( mockAttach ).toHaveBeenCalledTimes( 1 );
			expect( mockAttach ).toHaveBeenCalledWith( element, 'card' );
		} );

		it( 'does nothing when wcStripeDiag is absent', () => {
			diagnostics.attach( { id: 'fake' }, 'card' );

			expect( mockAttach ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'blocksPaymentSetupStart / End', () => {
		it( 'returns a handle from start and forwards it to end with the result, when active', () => {
			window.wcStripeDiag = { active: true };
			const fakeHandle = { startMs: 1234, site: 'payment_processor' };
			mockRecordBlocksPaymentSetupStart.mockReturnValue( fakeHandle );

			const handle =
				diagnostics.blocksPaymentSetupStart( 'payment_processor' );
			expect( mockRecordBlocksPaymentSetupStart ).toHaveBeenCalledWith(
				'payment_processor'
			);
			expect( handle ).toBe( fakeHandle );

			diagnostics.blocksPaymentSetupEnd( handle, { type: 'success' } );
			expect( mockRecordBlocksPaymentSetupEnd ).toHaveBeenCalledWith(
				fakeHandle,
				{ type: 'success' }
			);
		} );

		it( 'start returns null and end is a no-op when wcStripeDiag is absent', () => {
			const handle =
				diagnostics.blocksPaymentSetupStart( 'payment_processor' );
			expect( handle ).toBeNull();
			expect( mockRecordBlocksPaymentSetupStart ).not.toHaveBeenCalled();

			diagnostics.blocksPaymentSetupEnd( handle, { type: 'success' } );
			expect( mockRecordBlocksPaymentSetupEnd ).not.toHaveBeenCalled();
		} );

		it( 'end is a no-op when given a null handle (defensive)', () => {
			window.wcStripeDiag = { active: true };
			diagnostics.blocksPaymentSetupEnd( null, { type: 'success' } );

			expect( mockRecordBlocksPaymentSetupEnd ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'aroundStripeCall', () => {
		it( 'delegates to recorder.aroundStripeCall when active', async () => {
			window.wcStripeDiag = { active: true };
			const expected = { paymentMethod: { id: 'pm_x', type: 'card' } };
			mockAroundStripeCall.mockResolvedValue( expected );

			const fn = jest.fn();
			const result = await diagnostics.aroundStripeCall(
				'createPaymentMethod',
				fn
			);

			expect( mockAroundStripeCall ).toHaveBeenCalledWith(
				'createPaymentMethod',
				fn
			);
			expect( result ).toBe( expected );
		} );

		it( 'calls fn() directly and bypasses the recorder when wcStripeDiag is absent', async () => {
			const expected = { paymentMethod: { id: 'pm_y' } };
			const fn = jest.fn().mockResolvedValue( expected );

			const result = await diagnostics.aroundStripeCall(
				'createPaymentMethod',
				fn
			);

			expect( fn ).toHaveBeenCalledTimes( 1 );
			expect( mockAroundStripeCall ).not.toHaveBeenCalled();
			expect( result ).toBe( expected );
		} );
	} );

	describe( 'attachAfterReady', () => {
		it( 'delegates to recorder.attachAfterReady when active', () => {
			window.wcStripeDiag = { active: true };
			const element = { id: 'fake' };

			diagnostics.attachAfterReady( element, 'payment' );

			expect( mockAttachAfterReady ).toHaveBeenCalledTimes( 1 );
			expect( mockAttachAfterReady ).toHaveBeenCalledWith(
				element,
				'payment'
			);
		} );

		it( 'does nothing when wcStripeDiag is absent', () => {
			diagnostics.attachAfterReady( { id: 'fake' }, 'payment' );
			expect( mockAttachAfterReady ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'recordExpressEvent', () => {
		it( 'delegates to recorder.recordExpressEvent when active', () => {
			window.wcStripeDiag = { active: true };
			const payload = { expressPaymentType: 'apple_pay' };

			diagnostics.recordExpressEvent( 'click', payload );

			expect( mockRecordExpressEvent ).toHaveBeenCalledTimes( 1 );
			expect( mockRecordExpressEvent ).toHaveBeenCalledWith(
				'click',
				payload
			);
		} );

		it( 'does nothing when wcStripeDiag is absent', () => {
			diagnostics.recordExpressEvent( 'click', {} );
			expect( mockRecordExpressEvent ).not.toHaveBeenCalled();
		} );

		it( 'does nothing when wcStripeDiag.active is false', () => {
			window.wcStripeDiag = { active: false };
			diagnostics.recordExpressEvent( 'click', {} );
			expect( mockRecordExpressEvent ).not.toHaveBeenCalled();
		} );
	} );
} );
