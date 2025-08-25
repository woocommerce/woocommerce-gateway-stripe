import { render, screen, waitFor } from '@testing-library/react';
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

		userEvent.click( screen.getByTestId( 'base-component' ) );

		await waitFor( () => {
			expect(
				screen.queryByText( 'Popover Content' )
			).toBeInTheDocument();
		} );

		userEvent.click( screen.getByTestId( 'base-component' ) );

		await waitFor( () => {
			expect(
				screen.queryByText( 'Popover Content' )
			).not.toBeInTheDocument();
		} );
	} );
} );
