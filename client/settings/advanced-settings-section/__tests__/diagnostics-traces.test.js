import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DiagnosticsTraces from '../diagnostics-traces';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const NOTICE_ID = 'wc-stripe/diagnostics-copy-traces';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
} ) );

// Stub @wordpress/components: the component only relies on label text, the
// disabled flag, and the click handler. Pulling in the real components
// inflates test boot time without adding coverage.
jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, disabled, onClick } ) => (
		<button type="button" disabled={ disabled } onClick={ onClick }>
			{ children }
		</button>
	),
} ) );

let createSuccessNotice;
let createErrorNotice;

const summaryFixture = ( overrides = {} ) => ( {
	counts: {
		pending: 0,
		failed: 2,
		completed: 1,
		abandoned: 1,
		...( overrides.counts || {} ),
	},
	total: overrides.total ?? 4,
} );

const tracesFixture = ( count = 2 ) => ( {
	traces: Array.from( { length: count }, ( _, i ) => ( {
		id: `t${ i }`,
		status: 'failed',
		events: [],
	} ) ),
	count,
} );

describe( 'DiagnosticsTraces', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		Object.assign( navigator, {
			clipboard: { writeText: jest.fn().mockResolvedValue() },
		} );
		createSuccessNotice = jest.fn();
		createErrorNotice = jest.fn();
		useDispatch.mockReturnValue( {
			createSuccessNotice,
			createErrorNotice,
		} );
	} );

	it( 'renders nothing when there are no captured traces', async () => {
		apiFetch.mockResolvedValueOnce( { counts: {}, total: 0 } );
		const { container } = render( <DiagnosticsTraces /> );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalled() );
		expect( container.firstChild ).toBeNull();
	} );

	// Breakdown row rendering. Encodes the three behaviors the user can
	// see — non-zero bucket suppression, mixed counts, and singular
	// pluralization — in one parameterized test.
	it.each( [
		[
			'omits zero-count buckets',
			summaryFixture(), // pending=0, the rest non-zero
			'4 traces captured (2 failed, 1 abandoned, 1 succeeded)',
		],
		[
			'mixed counts with one zero bucket (abandoned)',
			summaryFixture( {
				counts: {
					pending: 5,
					failed: 1,
					completed: 1,
					abandoned: 0,
				},
				total: 7,
			} ),
			'7 traces captured (1 failed, 1 succeeded, 5 pending)',
		],
		[
			'singularizes when there is exactly one trace',
			summaryFixture( {
				counts: { pending: 0, failed: 1, completed: 0, abandoned: 0 },
				total: 1,
			} ),
			'1 trace captured (1 failed)',
		],
	] )( 'renders breakdown line: %s', async ( _label, summary, expected ) => {
		apiFetch.mockResolvedValueOnce( summary );
		render( <DiagnosticsTraces /> );
		expect( await screen.findByText( expected ) ).toBeInTheDocument();
	} );

	it( 'disables the primary button when no failed or abandoned traces exist', async () => {
		apiFetch.mockResolvedValueOnce(
			summaryFixture( {
				counts: { pending: 0, failed: 0, completed: 3, abandoned: 0 },
				total: 3,
			} )
		);
		render( <DiagnosticsTraces /> );

		const button = await screen.findByText(
			'Copy failed traces for support (0)'
		);
		expect( button ).toBeDisabled();
	} );

	// Click → fetch → clipboard → notice. Both buttons go through the same
	// pipeline; only the path filter and the rendered count differ.
	it.each( [
		[
			'primary button sends status=failed,abandoned',
			'Copy failed traces for support (3)',
			( path ) =>
				path.includes( 'status[]=failed' ) &&
				path.includes( 'status[]=abandoned' ),
			'Copied 3 traces to clipboard.',
		],
		[
			'secondary link sends no status filter',
			'Copy all instead',
			( path ) => ! path.includes( 'status[]' ),
			'Copied 4 traces to clipboard.',
		],
	] )(
		'click → fetch → clipboard → notice: %s',
		async ( _label, buttonText, pathMatches, expectedNotice ) => {
			const tracesCount = expectedNotice.includes( '4' ) ? 4 : 3;
			apiFetch
				.mockResolvedValueOnce( summaryFixture() )
				.mockResolvedValueOnce( tracesFixture( tracesCount ) )
				.mockResolvedValueOnce( summaryFixture() );

			render( <DiagnosticsTraces /> );
			await userEvent.click( await screen.findByText( buttonText ) );

			await waitFor( () =>
				expect( navigator.clipboard.writeText ).toHaveBeenCalled()
			);
			expect( pathMatches( apiFetch.mock.calls[ 1 ][ 0 ].path ) ).toBe(
				true
			);
			await waitFor( () =>
				expect( createSuccessNotice ).toHaveBeenCalledWith(
					expectedNotice,
					{ id: NOTICE_ID }
				)
			);
		}
	);

	it( 'shows an error notice when the traces fetch fails', async () => {
		apiFetch
			.mockResolvedValueOnce( summaryFixture() )
			.mockRejectedValueOnce( new Error( 'boom' ) );

		render( <DiagnosticsTraces /> );
		await userEvent.click(
			await screen.findByText( 'Copy failed traces for support (3)' )
		);

		await waitFor( () =>
			expect( createErrorNotice ).toHaveBeenCalledWith(
				'Could not copy traces. Try again.',
				{ id: NOTICE_ID }
			)
		);
		expect( createSuccessNotice ).not.toHaveBeenCalled();
	} );

	it( 'falls back to a file download when the trace bundle exceeds the clipboard threshold', async () => {
		// Force a > 1MB JSON payload by inflating events.
		const bigEvent = { kind: 'pad', data: 'x'.repeat( 600 * 1024 ) };
		const bigTraces = {
			traces: [
				{ id: 'big1', status: 'failed', events: [ bigEvent ] },
				{ id: 'big2', status: 'failed', events: [ bigEvent ] },
			],
			count: 2,
		};
		apiFetch
			.mockResolvedValueOnce( summaryFixture() )
			.mockResolvedValueOnce( bigTraces )
			.mockResolvedValueOnce( summaryFixture() );

		const createObjectURL = jest.fn().mockReturnValue( 'blob:fake' );
		Object.defineProperty( global.URL, 'createObjectURL', {
			value: createObjectURL,
			writable: true,
		} );
		Object.defineProperty( global.URL, 'revokeObjectURL', {
			value: jest.fn(),
			writable: true,
		} );

		render( <DiagnosticsTraces /> );
		await userEvent.click(
			await screen.findByText( 'Copy failed traces for support (3)' )
		);

		await waitFor( () =>
			expect( createSuccessNotice ).toHaveBeenCalledWith(
				'Trace bundle was too large to copy — downloaded as a file instead.',
				{ id: NOTICE_ID }
			)
		);
		expect( createObjectURL ).toHaveBeenCalled();
		expect( navigator.clipboard.writeText ).not.toHaveBeenCalled();
	} );
} );
