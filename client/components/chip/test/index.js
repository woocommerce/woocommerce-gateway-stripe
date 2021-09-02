/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Chip from '../';

describe( 'Chip', () => {
	test( 'renders an alert chip', () => {
		renderChip( 'alert', 'Alert message' );
		const alertMessage = screen.getByText( /alert message/i );
		expect( alertMessage ).toBeInTheDocument();
	} );

	test( 'renders a primary chip', () => {
		renderChip( 'primary', 'Primary message' );
		const primaryMessage = screen.getByText( /primary message/i );
		expect( primaryMessage ).toBeInTheDocument();
	} );

	test( 'renders a light chip', () => {
		renderChip( 'light', 'Light message' );
		const lightMessage = screen.getByText( /light message/i );
		expect( lightMessage ).toBeInTheDocument();
	} );

	test( 'renders a primary chip by default', () => {
		renderChip( undefined, 'Message' );
		const primaryMessage = screen.getByText( /message/i );
		expect( primaryMessage ).toBeInTheDocument();
	} );

	test( 'renders a warning chip', () => {
		renderChip( 'warning', 'Alert message' );
		const alertMessage = screen.getByText( /alert message/i );
		expect( alertMessage ).toBeInTheDocument();
	} );

	test( 'renders default if type is invalid', () => {
		renderChip( 'invalidtype', 'Message' );
		const primaryMessage = screen.getByText( /primary message/i );
		expect( primaryMessage ).toBeInTheDocument();
	} );

	function renderChip( type, message ) {
		return render( <Chip type={ type } message={ message } /> );
	}
} );
