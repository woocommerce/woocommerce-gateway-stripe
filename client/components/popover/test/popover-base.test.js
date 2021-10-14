import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PopoverBase from '../popover-base';

jest.useFakeTimers();

describe( 'PopoverBase', () => {
	it( 'does not render its content when hidden', () => {
		const handleHideMock = jest.fn();
		render(
			<PopoverBase
				isVisible={ false }
				content="Popover content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</PopoverBase>
		);

		jest.runAllTimers();

		expect(
			screen.queryByText( 'Popover content' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'renders its content when opened', () => {
		const handleHideMock = jest.fn();
		render(
			<PopoverBase
				isVisible
				content="Popover content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</PopoverBase>
		);

		jest.runAllTimers();

		expect( screen.queryByText( 'Popover content' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'does not call onHide when an internal element is clicked', () => {
		const handleHideMock = jest.fn();
		render(
			<PopoverBase
				isVisible
				content="Popover content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</PopoverBase>
		);

		userEvent.click( screen.getByText( 'Popover content' ) );
		jest.runAllTimers();

		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'calls onHide when an external element is clicked', () => {
		const handleHideMock = jest.fn();
		render(
			<>
				<PopoverBase
					isVisible
					content="Popover content"
					onHide={ handleHideMock }
				>
					<span>Trigger element</span>
				</PopoverBase>
				<span>External element</span>
			</>
		);

		userEvent.click( screen.getByText( 'External element' ) );
		jest.runAllTimers();

		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).toHaveBeenCalled();
	} );
} );
