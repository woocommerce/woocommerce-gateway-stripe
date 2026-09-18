import React from 'react';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaymentIntentsTable from '../payment-intents-table';
import usePaymentIntents from '../use-payment-intents';
import { DataViews } from '@wordpress/dataviews/wp';

// DataViews bundles its own @wordpress/components, @wordpress/ui and Ariakit
// copies, none of which jest transforms. Mocking it keeps these assertions on
// our own configuration rather than on its DOM.
jest.mock( '@wordpress/dataviews/wp', () => {
	const MockDataViews = jest.fn( () => null );
	MockDataViews.Layout = () => null;

	return { DataViews: MockDataViews };
} );

jest.mock( '../use-payment-intents', () => jest.fn() );

const intents = ( count, prefix = 'pi' ) =>
	Array.from( { length: count }, ( _, index ) => ( {
		id: `${ prefix }_${ index + 1 }`,
		created: 1757406094,
		amount: 1000,
		currency: 'usd',
		status: 'succeeded',
		description: `Order ${ index + 1 }`,
		latest_charge: null,
	} ) );

const mockFetch = ( overrides = {} ) =>
	usePaymentIntents.mockReturnValue( {
		data: [],
		hasMore: false,
		isLoading: false,
		error: null,
		...overrides,
	} );

const lastDataViewsProps = () =>
	DataViews.mock.calls[ DataViews.mock.calls.length - 1 ][ 0 ];

// Notice mirrors its message into wp.a11y's live region, so a plain text query
// matches twice. Read the visible notice instead.
const noticeText = ( container ) =>
	container
		.querySelector( '.wcstripe-inline-notice .components-notice__content' )
		?.textContent.trim();

describe( 'PaymentIntentsTable', () => {
	beforeEach( () => {
		DataViews.mockClear();
		usePaymentIntents.mockReset();
	} );

	it( 'starts on page one with the default page size', () => {
		mockFetch( { data: intents( 25 ) } );

		render( <PaymentIntentsTable /> );

		const { view, getItemId, defaultLayouts } = lastDataViewsProps();

		expect( view.type ).toBe( 'table' );
		expect( view.page ).toBe( 1 );
		expect( view.perPage ).toBe( 25 );
		expect( defaultLayouts ).toEqual( { table: {} } );
		expect( getItemId( { id: 'pi_9' } ) ).toBe( 'pi_9' );
	} );

	it( 'requests the first page without a cursor', () => {
		mockFetch( { data: intents( 25 ) } );

		render( <PaymentIntentsTable /> );

		expect( usePaymentIntents ).toHaveBeenCalledWith( {
			perPage: 25,
			cursor: null,
		} );
	} );

	// paginationInfo is required by DataViews but the endpoint reports no
	// totals, so it must never imply a page count we cannot honour.
	it( 'reports a single page of pagination info regardless of has_more', () => {
		mockFetch( { data: intents( 25 ), hasMore: true } );

		render( <PaymentIntentsTable /> );

		expect( lastDataViewsProps().paginationInfo ).toEqual( {
			totalItems: 25,
			totalPages: 1,
		} );
	} );

	it( 'pages forward using the last row id as the cursor', async () => {
		mockFetch( { data: intents( 25 ), hasMore: true } );

		render( <PaymentIntentsTable /> );

		await userEvent.click( screen.getByRole( 'button', { name: 'Next' } ) );

		expect( usePaymentIntents ).toHaveBeenLastCalledWith( {
			perPage: 25,
			cursor: 'pi_25',
		} );
	} );

	it( 'returns to the first page without a cursor', async () => {
		mockFetch( { data: intents( 25 ), hasMore: true } );

		render( <PaymentIntentsTable /> );

		await userEvent.click( screen.getByRole( 'button', { name: 'Next' } ) );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Previous' } )
		);

		expect( usePaymentIntents ).toHaveBeenLastCalledWith( {
			perPage: 25,
			cursor: null,
		} );
	} );

	it.each( [
		[ 'Previous', true ],
		[ 'Next', false ],
	] )(
		'disables %s on the first page when has_more is %s',
		( label, hasMore ) => {
			mockFetch( { data: intents( 25 ), hasMore } );

			render( <PaymentIntentsTable /> );

			expect(
				screen.getByRole( 'button', { name: label } )
			).toBeDisabled();
		}
	);

	it( 'disables both controls while loading', () => {
		mockFetch( { data: intents( 25 ), hasMore: true, isLoading: true } );

		render( <PaymentIntentsTable /> );

		expect( screen.getByRole( 'button', { name: 'Next' } ) ).toBeDisabled();
		expect(
			screen.getByRole( 'button', { name: 'Previous' } )
		).toBeDisabled();
	} );

	it( 'resets to the first page when the page size changes', () => {
		mockFetch( { data: intents( 25 ), hasMore: true } );

		render( <PaymentIntentsTable /> );

		act( () =>
			lastDataViewsProps().onChangeView( {
				...lastDataViewsProps().view,
				page: 3,
				perPage: 50,
			} )
		);

		expect( usePaymentIntents ).toHaveBeenLastCalledWith( {
			perPage: 50,
			cursor: null,
		} );
	} );

	it( 'forwards the loading state and an empty state to DataViews', () => {
		mockFetch( { data: [], isLoading: true } );

		render( <PaymentIntentsTable /> );

		const { isLoading, empty } = lastDataViewsProps();

		expect( isLoading ).toBe( true );
		expect( empty ).toBeTruthy();
	} );

	it( 'shows the error message while keeping the last good page', () => {
		mockFetch( {
			data: intents( 2 ),
			error: 'Unable to fetch data from Stripe.',
		} );

		const { container } = render( <PaymentIntentsTable /> );

		expect( noticeText( container ) ).toBe(
			'Unable to fetch data from Stripe.'
		);
		expect( lastDataViewsProps().data ).toHaveLength( 2 );
	} );

	it( 'hides the table when the very first load fails', () => {
		mockFetch( { data: [], error: 'Unable to fetch data from Stripe.' } );

		const { container } = render( <PaymentIntentsTable /> );

		expect( noticeText( container ) ).toBe(
			'Unable to fetch data from Stripe.'
		);
		expect( DataViews ).not.toHaveBeenCalled();
	} );
} );
