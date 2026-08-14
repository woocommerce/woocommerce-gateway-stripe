import React from 'react';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DiagnosticsTraces from '../diagnostics-traces';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const NOTICE_ID = 'wc-stripe/diagnostics-copy-traces';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
} ) );

// Stub @wordpress/components so we test against label text and click
// handlers without booting the full Gutenberg component tree (Modal
// portals, isBusy spinners, focus management).
jest.mock( '@wordpress/components', () => ( {
	// Strip Gutenberg-specific style props (isPrimary/isSecondary/
	// isDestructive) before they hit the DOM so React doesn't warn about
	// unknown HTML attributes; the rest are forwarded so the assertions can
	// match on text, disabled, and aria-label.
	Button: ( props ) => {
		const {
			children,
			disabled,
			onClick,
			isBusy,
			'aria-label': ariaLabel,
		} = props;
		return (
			<button
				type="button"
				disabled={ disabled }
				onClick={ onClick }
				data-busy={ isBusy ? 'true' : 'false' }
				aria-label={ ariaLabel }
			>
				{ children }
			</button>
		);
	},
} ) );

jest.mock( 'wcstripe/components/confirmation-modal', () => ( {
	__esModule: true,
	default: ( { children, actions, title } ) => (
		<div role="dialog" aria-label={ title }>
			<div>{ title }</div>
			{ children }
			<div>{ actions }</div>
		</div>
	),
} ) );

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

let createSuccessNotice;
let createErrorNotice;
let removeNotice;

const traceFixture = ( overrides = {} ) => ( {
	id: 'tr_default',
	status: 'pending',
	created_at: 1700000000,
	updated_at: 1700000010,
	meta: {},
	events: [],
	...overrides,
} );

const tracesResponse = ( traces ) => ( {
	traces,
	count: traces.length,
} );

describe( 'DiagnosticsTraces', () => {
	beforeEach( () => {
		// `mockReset` (not `clearAllMocks`) clears the
		// `mockResolvedValueOnce` queue too — without this, leftover
		// queued resolutions from one test bleed into the next.
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( { traces: [], count: 0 } );
		jest.clearAllMocks();
		Object.assign( navigator, {
			clipboard: { writeText: jest.fn().mockResolvedValue() },
		} );
		createSuccessNotice = jest.fn();
		createErrorNotice = jest.fn();
		removeNotice = jest.fn();
		useDispatch.mockImplementation( ( storeName ) =>
			storeName === 'core/notices'
				? { createSuccessNotice, createErrorNotice, removeNotice }
				: {}
		);
	} );

	it( 'renders the empty state with recording-aware copy when no traces are stored', async () => {
		apiFetch.mockResolvedValueOnce( tracesResponse( [] ) );
		const { unmount } = render(
			<DiagnosticsTraces isRecording={ true } />
		);
		expect(
			await screen.findByText(
				/Traces will appear here as customers reach checkout/i
			)
		).toBeInTheDocument();
		unmount();

		apiFetch.mockResolvedValueOnce( tracesResponse( [] ) );
		render( <DiagnosticsTraces isRecording={ false } /> );
		expect(
			await screen.findByText( /Turn on diagnostics to start capturing/i )
		).toBeInTheDocument();
	} );

	it( 'renders toolbar with recording indicator and per-trace rows when traces exist', async () => {
		apiFetch.mockResolvedValueOnce(
			tracesResponse( [
				traceFixture( {
					id: 'tr_a',
					status: 'failed',
					meta: { order_id: '123' },
				} ),
				traceFixture( { id: 'tr_b', status: 'completed' } ),
			] )
		);

		render( <DiagnosticsTraces isRecording={ true } /> );

		expect( await screen.findByText( 'Recording' ) ).toBeInTheDocument();
		expect( screen.getByText( /2 of 10 captured/ ) ).toBeInTheDocument();
		expect( screen.getByText( '1 failed' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'trace-row-tr_a' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'trace-row-tr_b' ) ).toBeInTheDocument();
	} );

	it( 'switches the recording indicator to "Not recording" when toggle is off', async () => {
		apiFetch.mockResolvedValueOnce(
			tracesResponse( [ traceFixture( { id: 'tr_a' } ) ] )
		);
		render( <DiagnosticsTraces isRecording={ false } /> );
		expect(
			await screen.findByText( 'Not recording' )
		).toBeInTheDocument();
	} );

	it( 'filters the visible list when "Failed only" is selected', async () => {
		apiFetch.mockResolvedValueOnce(
			tracesResponse( [
				traceFixture( { id: 'tr_a', status: 'failed' } ),
				traceFixture( { id: 'tr_b', status: 'completed' } ),
			] )
		);

		render( <DiagnosticsTraces isRecording={ true } /> );
		await screen.findByTestId( 'trace-row-tr_a' );

		await userEvent.click( screen.getByText( 'Failed only' ) );

		expect( screen.getByTestId( 'trace-row-tr_a' ) ).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'trace-row-tr_b' )
		).not.toBeInTheDocument();
	} );

	it( 'bulk Copy writes the visible payload to the clipboard', async () => {
		apiFetch.mockResolvedValueOnce(
			tracesResponse( [
				traceFixture( { id: 'tr_a', status: 'failed' } ),
				traceFixture( { id: 'tr_b', status: 'completed' } ),
			] )
		);
		render( <DiagnosticsTraces isRecording={ true } /> );
		await screen.findByTestId( 'trace-row-tr_a' );

		await userEvent.click( screen.getByText( 'Copy all' ) );

		await waitFor( () =>
			expect( navigator.clipboard.writeText ).toHaveBeenCalled()
		);
		const payload = JSON.parse(
			navigator.clipboard.writeText.mock.calls[ 0 ][ 0 ]
		);
		expect( payload ).toHaveLength( 2 );
		expect( removeNotice ).toHaveBeenCalledWith( NOTICE_ID );
		expect( createSuccessNotice ).toHaveBeenCalledWith(
			'Copied 2 traces to clipboard.',
			{ id: NOTICE_ID }
		);
	} );

	it( 'per-row Copy writes the single trace JSON to the clipboard', async () => {
		const trace = traceFixture( { id: 'tr_a', status: 'failed' } );
		apiFetch.mockResolvedValueOnce( tracesResponse( [ trace ] ) );
		render( <DiagnosticsTraces isRecording={ true } /> );
		const row = await screen.findByTestId( 'trace-row-tr_a' );

		await userEvent.click(
			within( row ).getByLabelText( /Copy trace tr_a/ )
		);

		await waitFor( () =>
			expect( navigator.clipboard.writeText ).toHaveBeenCalled()
		);
		const payload = JSON.parse(
			navigator.clipboard.writeText.mock.calls[ 0 ][ 0 ]
		);
		expect( payload.id ).toBe( 'tr_a' );
		expect( createSuccessNotice ).toHaveBeenCalledWith(
			'Copied 1 trace to clipboard.',
			{ id: NOTICE_ID }
		);
	} );

	it( 'per-row View opens the trace modal', async () => {
		const trace = traceFixture( { id: 'tr_a' } );
		apiFetch.mockResolvedValueOnce( tracesResponse( [ trace ] ) );
		render( <DiagnosticsTraces isRecording={ true } /> );
		const row = await screen.findByTestId( 'trace-row-tr_a' );

		await userEvent.click(
			within( row ).getByLabelText( /View trace tr_a/ )
		);

		expect(
			screen.getByRole( 'dialog', { name: /Trace tr_a/ } )
		).toBeInTheDocument();
	} );

	it( 'Clear opens a confirmation, DELETEs traces, and refetches the list', async () => {
		apiFetch
			.mockResolvedValueOnce(
				tracesResponse( [ traceFixture( { id: 'tr_a' } ) ] )
			)
			.mockResolvedValueOnce( { deleted: 1, total: 0 } )
			.mockResolvedValueOnce( tracesResponse( [] ) );

		render( <DiagnosticsTraces isRecording={ true } /> );
		await screen.findByTestId( 'trace-row-tr_a' );

		await userEvent.click( screen.getByText( 'Clear' ) );
		// Confirmation modal — confirm
		await userEvent.click( screen.getByText( 'Clear traces' ) );

		await waitFor( () =>
			expect(
				apiFetch.mock.calls.some(
					( [ args ] ) => args.method === 'DELETE'
				)
			).toBe( true )
		);
		expect( createSuccessNotice ).toHaveBeenCalledWith(
			'Cleared all stored traces.',
			{ id: NOTICE_ID }
		);
	} );

	it( 'falls back to a file download when the trace bundle exceeds the clipboard threshold', async () => {
		const padding = 'x'.repeat( 600 * 1024 );
		const big = traceFixture( {
			id: 'big',
			status: 'failed',
			events: [
				{ kind: 'pad', data: padding },
				{ kind: 'pad', data: padding },
			],
		} );
		apiFetch.mockResolvedValueOnce( tracesResponse( [ big ] ) );

		const createObjectURL = jest.fn().mockReturnValue( 'blob:fake' );
		Object.defineProperty( global.URL, 'createObjectURL', {
			value: createObjectURL,
			writable: true,
		} );
		Object.defineProperty( global.URL, 'revokeObjectURL', {
			value: jest.fn(),
			writable: true,
		} );

		render( <DiagnosticsTraces isRecording={ true } /> );
		await screen.findByTestId( 'trace-row-big' );
		await userEvent.click( screen.getByText( 'Copy all' ) );

		await waitFor( () =>
			expect( createSuccessNotice ).toHaveBeenCalledWith(
				'Trace bundle was too large to copy — downloaded as a file instead.',
				{ id: NOTICE_ID }
			)
		);
		expect( createObjectURL ).toHaveBeenCalled();
		expect( navigator.clipboard.writeText ).not.toHaveBeenCalled();
	} );

	it( 'shows an error notice when the clear request fails', async () => {
		apiFetch
			.mockResolvedValueOnce(
				tracesResponse( [ traceFixture( { id: 'tr_a' } ) ] )
			)
			.mockRejectedValueOnce( new Error( 'boom' ) );

		render( <DiagnosticsTraces isRecording={ true } /> );
		await screen.findByTestId( 'trace-row-tr_a' );

		await userEvent.click( screen.getByText( 'Clear' ) );
		await userEvent.click( screen.getByText( 'Clear traces' ) );

		await waitFor( () =>
			expect( createErrorNotice ).toHaveBeenCalledWith(
				'Could not clear traces. Try again.',
				{ id: NOTICE_ID }
			)
		);
	} );

	describe( 'auto-off capture limit', () => {
		it( 'renders the "X of N captured" counter against the active limit', async () => {
			apiFetch.mockResolvedValueOnce(
				tracesResponse( [
					traceFixture( { id: 'tr_a', status: 'failed' } ),
					traceFixture( { id: 'tr_b', status: 'completed' } ),
				] )
			);
			render(
				<DiagnosticsTraces
					isRecording={ true }
					captureLimit={ 10 }
					onChangeCaptureLimit={ jest.fn() }
				/>
			);
			expect(
				await screen.findByText( /2 of 10 captured/ )
			).toBeInTheDocument();
		} );

		it( 'shows the inline limit selector while recording and writes preset changes back', async () => {
			const onChangeCaptureLimit = jest.fn();
			apiFetch.mockResolvedValueOnce(
				tracesResponse( [ traceFixture( { id: 'tr_a' } ) ] )
			);
			render(
				<DiagnosticsTraces
					isRecording={ true }
					captureLimit={ 10 }
					captureLimitPresets={ [ 5, 10, 25, 50 ] }
					onChangeCaptureLimit={ onChangeCaptureLimit }
				/>
			);
			const select = await screen.findByLabelText(
				'Auto-off capture limit'
			);
			expect( select ).toHaveValue( '10' );
			// Auto-off is mandatory — no opt-out option.
			expect(
				screen.queryByRole( 'option', { name: /unlimited/i } )
			).not.toBeInTheDocument();
			await userEvent.selectOptions( select, '25' );
			expect( onChangeCaptureLimit ).toHaveBeenCalledWith( 25 );
		} );

		it( 'hides the limit selector and progress bar when recording is off', async () => {
			apiFetch.mockResolvedValueOnce(
				tracesResponse( [ traceFixture( { id: 'tr_a' } ) ] )
			);
			render(
				<DiagnosticsTraces
					isRecording={ false }
					captureLimit={ 10 }
					onChangeCaptureLimit={ jest.fn() }
				/>
			);
			await screen.findByTestId( 'trace-row-tr_a' );
			expect(
				screen.queryByLabelText( 'Auto-off capture limit' )
			).not.toBeInTheDocument();
			expect(
				screen.queryByRole( 'progressbar', {
					name: /Capture progress/i,
				} )
			).not.toBeInTheDocument();
		} );

		it( 'renders a progress bar that reflects captured/limit when recording with a limit set', async () => {
			apiFetch.mockResolvedValueOnce(
				tracesResponse( [
					traceFixture( { id: 'tr_a' } ),
					traceFixture( { id: 'tr_b' } ),
					traceFixture( { id: 'tr_c' } ),
				] )
			);
			render(
				<DiagnosticsTraces
					isRecording={ true }
					captureLimit={ 10 }
					onChangeCaptureLimit={ jest.fn() }
				/>
			);
			const bar = await screen.findByRole( 'progressbar', {
				name: /Capture progress/i,
			} );
			expect( bar ).toHaveAttribute( 'aria-valuemax', '10' );
			expect( bar ).toHaveAttribute( 'aria-valuenow', '3' );
		} );
	} );
} );
