import { render } from '@testing-library/react';
import Loadable from '..';

describe( 'Loadable', () => {
	const ChildComponent = () => <div>Loaded content</div>;
	let loadableProps;

	describe( 'when active', () => {
		beforeEach( () => {
			loadableProps = {
				isLoading: true,
			};
		} );

		test( 'renders custom placeholder', () => {
			const customPlaceholder = 'Custom text';
			loadableProps.placeholder = customPlaceholder;
			const { queryByText } = renderLoadable( loadableProps );
			expect( queryByText( customPlaceholder ) ).toBeInTheDocument();
		} );

		test( 'uses children as placeholder if not passed', () => {
			const { container } = renderLoadable( loadableProps );
			expect( container ).toMatchSnapshot();
		} );
	} );

	describe( 'when inactive', () => {
		beforeEach( () => {
			loadableProps = {
				isLoading: false,
			};
		} );

		test( 'render children', () => {
			const { container } = renderLoadable( loadableProps );
			expect( container ).toMatchSnapshot();
		} );

		test( 'renders simple value', () => {
			const simpleValue = 'Simple loadable value';
			loadableProps.value = simpleValue;
			const { queryByText } = renderLoadable( loadableProps, null );
			expect( queryByText( simpleValue ) ).toBeInTheDocument();
		} );

		test( 'prioritizes rendering children over simple value', () => {
			const simpleValue = 'Simple loadable value';
			loadableProps.value = simpleValue;
			const { queryByText } = renderLoadable( loadableProps );
			expect( queryByText( /loaded content/i ) ).toBeInTheDocument();
			expect( queryByText( simpleValue ) ).not.toBeInTheDocument();
		} );

		test( 'renders nothing when neither children nor value passed', () => {
			const { container, queryByText } = renderLoadable(
				loadableProps,
				null
			);
			expect( queryByText( /loaded content/i ) ).not.toBeInTheDocument();
			expect( container.innerHTML ).toBe( '' );
		} );
	} );

	function renderLoadable( props = {}, content = <ChildComponent /> ) {
		return render( <Loadable { ...props }>{ content }</Loadable> );
	}
} );
