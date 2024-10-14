import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
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

	it( 'toggle the visibility on click', () => {
		render(
			<Popover
				BaseComponent={ DummyBaseComponent }
				content="Popover Content"
			/>
		);

		expect(
			screen.queryByText( 'Popover Content' )
		).not.toBeInTheDocument();

		act( () => {
			userEvent.click( screen.getByTestId( 'base-component' ) );
		} );

		expect( screen.queryByText( 'Popover Content' ) ).toBeInTheDocument();

		act( () => {
			userEvent.click( screen.getByTestId( 'base-component' ) );
		} );

		expect(
			screen.queryByText( 'Popover Content' )
		).not.toBeInTheDocument();
	} );
} );
