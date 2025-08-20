import { fireEvent, render, screen } from '@testing-library/react';
import Popover from '..';

const DummyBaseComponent = ( { children, ...props } ) => (
	<div data-testid="base-component" { ...props }>
		{ children }
	</div>
);

describe( 'Popover', () => {
	it( 'does not render its content initially', () => {
		render(
			<Popover
				BaseComponent={ DummyBaseComponent }
				content="Popover Content"
			/>
		);

		expect(
			screen.queryByText( 'Popover Content' )
		).not.toBeInTheDocument();
	} );

	it( 'toggle the visibility on click', async () => {
		render(
			<Popover
				BaseComponent={ DummyBaseComponent }
				content="Popover Content"
			/>
		);

		expect(
			screen.queryByText( 'Popover Content' )
		).not.toBeInTheDocument();

		await fireEvent.click( screen.getByTestId( 'base-component' ) );

		expect( screen.queryByText( 'Popover Content' ) ).toBeInTheDocument();

		await fireEvent.click( screen.getByTestId( 'base-component' ) );

		expect(
			screen.queryByText( 'Popover Content' )
		).not.toBeInTheDocument();
	} );
} );
