import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TooltipBase from '../tooltip-base';

jest.useFakeTimers();

describe( 'TooltipBase', () => {
	it( 'does not render its content when hidden', () => {
		const handleHideMock = jest.fn();
		render(
			<TooltipBase
				isVisible={ false }
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</TooltipBase>
		);

		jest.runAllTimers();

		expect(
			screen.queryByText( 'Tooltip content' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'renders its content when opened', () => {
		const handleHideMock = jest.fn();
		render(
			<TooltipBase
				isVisible
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</TooltipBase>
		);

		jest.runAllTimers();

		expect( screen.queryByText( 'Tooltip content' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'does not call onHide when an internal element is clicked', () => {
		const handleHideMock = jest.fn();
		render(
			<TooltipBase
				isVisible
				content="Tooltip content"
				onHide={ handleHideMock }
			>
				<span>Trigger element</span>
			</TooltipBase>
		);

		userEvent.click( screen.getByText( 'Tooltip content' ) );
		jest.runAllTimers();

		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).not.toHaveBeenCalled();
	} );

	it( 'calls onHide when an external element is clicked', () => {
		const handleHideMock = jest.fn();
		render(
			<>
				<TooltipBase
					isVisible
					content="Tooltip content"
					onHide={ handleHideMock }
				>
					<span>Trigger element</span>
				</TooltipBase>
				<span>External element</span>
			</>
		);

		userEvent.click( screen.getByText( 'External element' ) );
		jest.runAllTimers();

		expect( screen.queryByText( 'Trigger element' ) ).toBeInTheDocument();
		expect( handleHideMock ).toHaveBeenCalled();
	} );
} );
