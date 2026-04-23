const mockAttachExpress = jest.fn();
const mockWrapStripe = jest.fn();
const mockAttach = jest.fn();
const mockRecordBlocksPaymentSetupStart = jest.fn();
const mockRecordBlocksPaymentSetupEnd = jest.fn();
const mockAroundStripeCall = jest.fn();

jest.mock( 'wcstripe/diagnostics/recorder', () => ( {
	getRecorder: () => ( {
		attachExpress: mockAttachExpress,
		wrapStripe: mockWrapStripe,
		attach: mockAttach,
		recordBlocksPaymentSetupStart: mockRecordBlocksPaymentSetupStart,
		recordBlocksPaymentSetupEnd: mockRecordBlocksPaymentSetupEnd,
		aroundStripeCall: mockAroundStripeCall,
	} ),
} ) );

import {
	diagAttach,
	diagAttachExpress,
	diagBlocksPaymentSetupStart,
	diagBlocksPaymentSetupEnd,
	diagAroundStripeCall,
} from 'wcstripe/diagnostics/wiring';

describe( 'diagnostics wiring helpers', () => {
	beforeEach( () => {
		mockAttachExpress.mockClear();
		mockWrapStripe.mockClear();
		mockAttach.mockClear();
		mockRecordBlocksPaymentSetupStart.mockClear();
		mockRecordBlocksPaymentSetupEnd.mockClear();
		mockAroundStripeCall.mockClear();
		delete window.wcStripeDiag;
	} );

	describe( 'diagAttachExpress', () => {
		it( 'calls recorder.attachExpress with the button when wcStripeDiag is active', () => {
			window.wcStripeDiag = { active: true };
			const eceButton = { id: 'fake-ece' };

			diagAttachExpress( eceButton );

			expect( mockAttachExpress ).toHaveBeenCalledTimes( 1 );
			expect( mockAttachExpress ).toHaveBeenCalledWith( eceButton );
		} );

		it( 'does nothing when wcStripeDiag is absent', () => {
			diagAttachExpress( { id: 'fake-ece' } );

			expect( mockAttachExpress ).not.toHaveBeenCalled();
		} );

		it( 'does nothing when wcStripeDiag.active is false', () => {
			window.wcStripeDiag = { active: false };
			diagAttachExpress( { id: 'fake-ece' } );

			expect( mockAttachExpress ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'diagAttach', () => {
		it( 'calls recorder.attach with element, kind, and surface when active', () => {
			window.wcStripeDiag = { active: true };
			const element = { id: 'fake-element' };

			diagAttach( element, 'card', 'classic' );

			expect( mockAttach ).toHaveBeenCalledTimes( 1 );
			expect( mockAttach ).toHaveBeenCalledWith(
				element,
				'card',
				'classic'
			);
		} );

		it( 'does nothing when wcStripeDiag is absent', () => {
			diagAttach( { id: 'fake' }, 'card', 'classic' );

			expect( mockAttach ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'diagBlocksPaymentSetupStart / End', () => {
		it( 'returns a handle from start and forwards it to end with the result, when active', () => {
			window.wcStripeDiag = { active: true };
			const fakeHandle = { startMs: 1234, site: 'payment_processor' };
			mockRecordBlocksPaymentSetupStart.mockReturnValue( fakeHandle );

			const handle = diagBlocksPaymentSetupStart( 'payment_processor' );
			expect( mockRecordBlocksPaymentSetupStart ).toHaveBeenCalledWith(
				'payment_processor'
			);
			expect( handle ).toBe( fakeHandle );

			diagBlocksPaymentSetupEnd( handle, { type: 'success' } );
			expect( mockRecordBlocksPaymentSetupEnd ).toHaveBeenCalledWith(
				fakeHandle,
				{ type: 'success' }
			);
		} );

		it( 'start returns null and end is a no-op when wcStripeDiag is absent', () => {
			const handle = diagBlocksPaymentSetupStart( 'payment_processor' );
			expect( handle ).toBeNull();
			expect( mockRecordBlocksPaymentSetupStart ).not.toHaveBeenCalled();

			diagBlocksPaymentSetupEnd( handle, { type: 'success' } );
			expect( mockRecordBlocksPaymentSetupEnd ).not.toHaveBeenCalled();
		} );

		it( 'end is a no-op when given a null handle (defensive)', () => {
			window.wcStripeDiag = { active: true };
			diagBlocksPaymentSetupEnd( null, { type: 'success' } );

			expect( mockRecordBlocksPaymentSetupEnd ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'diagAroundStripeCall', () => {
		it( 'delegates to recorder.aroundStripeCall when active', async () => {
			window.wcStripeDiag = { active: true };
			const expected = { paymentMethod: { id: 'pm_x', type: 'card' } };
			mockAroundStripeCall.mockResolvedValue( expected );

			const fn = jest.fn();
			const result = await diagAroundStripeCall(
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

			const result = await diagAroundStripeCall(
				'createPaymentMethod',
				fn
			);

			expect( fn ).toHaveBeenCalledTimes( 1 );
			expect( mockAroundStripeCall ).not.toHaveBeenCalled();
			expect( result ).toBe( expected );
		} );
	} );
} );
