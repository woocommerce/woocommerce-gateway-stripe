import React from 'react'
import { screen, render } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

describe('Dummy React base test', () => {
	it('should render a react component', () => {
		render(
			<div>Dummy element</div>
		)

		expect(screen.queryByText('Dummy element')).toBeInTheDocument()
	})

	it('should test user events', () => {
		const handleClick = jest.fn()
		render(<button onClick={handleClick}>Click me</button>)

		expect(handleClick).not.toHaveBeenCalled()

		userEvent.click(screen.getByText('Click me'))

		expect(handleClick).toHaveBeenCalled()
	})
})
