import { render, screen, act } from '@testing-library/react';
import CopyButton from '..';

const writeTextMock = jest.fn( () => Promise.resolve() );

beforeEach( () => {
	writeTextMock.mockClear();
	Object.assign( navigator, {
		clipboard: { writeText: writeTextMock },
	} );
} );

describe( 'CopyButton', () => {
	it( 'renders with default aria-label', () => {
		render( <CopyButton text="hello" /> );
		expect(
			screen.getByRole( 'button', { name: 'Copy' } )
		).toBeInTheDocument();
	} );

	it( 'renders with custom aria-label', () => {
		render( <CopyButton text="hello" label="Copy URL" /> );
		expect(
			screen.getByRole( 'button', { name: 'Copy URL' } )
		).toBeInTheDocument();
	} );

	it( 'copies text to clipboard on click', async () => {
		render( <CopyButton text="some-value" /> );

		await act( async () => {
			screen.getByRole( 'button' ).click();
		} );

		expect( writeTextMock ).toHaveBeenCalledWith( 'some-value' );
	} );

	it( 'adds success class after copy', async () => {
		render( <CopyButton text="hello" /> );
		const button = screen.getByRole( 'button' );

		await act( async () => {
			button.click();
		} );

		expect( button ).toHaveClass( 'state--success' );
	} );

	it( 'does not copy when text is empty', async () => {
		render( <CopyButton text="" /> );

		await act( async () => {
			screen.getByRole( 'button' ).click();
		} );

		expect( writeTextMock ).not.toHaveBeenCalled();
	} );

	it( 'applies custom className', () => {
		render( <CopyButton text="hello" className="my-class" /> );
		expect( screen.getByRole( 'button' ) ).toHaveClass( 'my-class' );
	} );
} );
