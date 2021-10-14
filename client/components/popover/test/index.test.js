import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import Popover from '..';

jest.useFakeTimers();

describe( 'Popover', () => {
	it( 'does not render its content when hidden', () => {
		const handleHideMock = jest.fn();
		render(
			<Popover
				isVisible={ false }
				content="Popover content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</Popover>
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
			<Popover
				isVisible
				content="Popover content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</Popover>
		);

		jest.runAllTimers();

		expect( screen.queryByText( 'Popover content' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'renders its content when clicked', () => {
		const handleHideMock = jest.fn();
		render(
			<Popover content="Popover content" onHide={ handleHideMock }>
				<span>Trigger element</span>
			</Popover>
		);

		jest.runAllTimers();

		expect(
			screen.queryByText( 'Popover content' )
		).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Trigger element' ) );

		jest.runAllTimers();

		expect( screen.queryByText( 'Popover content' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();

		userEvent.click( screen.getByText( 'Trigger element' ) );
		jest.runAllTimers();

		expect( handleHideMock ).toHaveBeenCalled();
	} );

	it( 'asks other Popovers to hide, when multiple are opened', () => {
		const handleHide1Mock = jest.fn();
		const handleHide2Mock = jest.fn();
		render(
			<>
				<Popover content="Popover 1 content" onHide={ handleHide1Mock }>
					<span>Open popover 1</span>
				</Popover>
				<Popover content="Popover 2 content" onHide={ handleHide2Mock }>
					<span>Open popover 2</span>
				</Popover>
			</>
		);

		jest.runAllTimers();

		expect(
			screen.queryByText( 'Popover 1 content' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Popover 2 content' )
		).not.toBeInTheDocument();
		expect( handleHide1Mock ).not.toHaveBeenCalled();
		expect( handleHide2Mock ).not.toHaveBeenCalled();

		// opening the first popover, no need to call any hide handlers
		act( () => userEvent.click( screen.getByText( 'Open popover 1' ) ) );

		expect( screen.queryByText( 'Popover 1 content' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'Popover 2 content' )
		).not.toBeInTheDocument();
		expect( handleHide1Mock ).not.toHaveBeenCalled();
		expect( handleHide2Mock ).not.toHaveBeenCalled();

		jest.runAllTimers();

		// opening the second popover, only the first popover should not be visible anymore
		act( () => {
			userEvent.click( screen.getByText( 'Open popover 2' ) );
			jest.runAllTimers();
		} );

		expect(
			screen.queryByText( 'Popover 1 content' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Popover 2 content' ) ).toBeInTheDocument();
		expect( handleHide1Mock ).toHaveBeenCalled();
		expect( handleHide2Mock ).not.toHaveBeenCalled();
	} );
} );
