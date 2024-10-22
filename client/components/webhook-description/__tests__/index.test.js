import { render, screen } from '@testing-library/react';
import { WebhookDescription } from '..';
import { useAccount } from 'wcstripe/data/account';
import useWebhookStateMessage from 'wcstripe/settings/account-details/use-webhook-state-message';

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

jest.mock( 'wcstripe/settings/account-details/use-webhook-state-message' );

beforeEach( () => {
	useAccount.mockReturnValue( {
		data: { webhook_url: 'example.com' },
	} );
} );

describe( 'WebhookDescription', () => {
	it( 'regular message (not a warning), no information component', () => {
		useWebhookStateMessage.mockImplementation( () => {
			return {
				message: 'Some message',
				requestStatus: 'success',
				refreshMessage: jest.fn(),
			};
		} );

		render( <WebhookDescription isWebhookEnabled={ true } /> );

		expect(
			screen.queryByTestId( 'webhook-information' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByTestId( 'warning-icon' )
		).not.toBeInTheDocument();
	} );

	it( 'regular message (not a warning), with information component', () => {
		useWebhookStateMessage.mockImplementation( () => {
			return {
				message: 'Some message',
				requestStatus: 'success',
				refreshMessage: jest.fn(),
			};
		} );

		render( <WebhookDescription isWebhookEnabled={ false } /> );

		expect(
			screen.queryByTestId( 'webhook-information' )
		).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'warning-icon' )
		).not.toBeInTheDocument();
	} );

	it( 'warning message, with information component', () => {
		useWebhookStateMessage.mockImplementation( () => {
			return {
				code: 4,
				message: 'Warning: Some message',
				requestStatus: 'success',
				refreshMessage: jest.fn(),
			};
		} );

		render( <WebhookDescription isWebhookEnabled={ false } /> );

		expect(
			screen.queryByTestId( 'webhook-information' )
		).toBeInTheDocument();
		expect( screen.queryByTestId( 'warning-icon' ) ).toBeInTheDocument();
	} );
} );
