import React, { useCallback, useEffect, useRef, useState } from 'react';
import { isEqual } from 'lodash';
import InlineNotice from '../components/inline-notice';
import paymentMethodsMap from '../payment-methods-map';
import { useSettings } from '../data';
import { useAccount } from '../data/account';
import { NAMESPACE, STORE_NAME } from '../data/constants';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const endpoint = `${ NAMESPACE }/account/pmc-review`;
const selections = ( methods = [] ) => [ ...methods ].sort();
const describe = ( selection ) =>
	[
		...selection.upe_checkout_experience_accepted_payments.map(
			( id ) => paymentMethodsMap[ id ]?.label || id
		),
		...( selection.express_checkout === 'yes'
			? [ 'Apple Pay / Google Pay' ]
			: [] ),
	].join( ', ' ) || __( 'None', 'woocommerce-gateway-stripe' );

const PMCSyncNotice = ( { onResolved, hasUnsavedChanges = false } ) => {
	const { settings, isLoading, isSaving } = useSettings();
	const { data, isRefreshing } = useAccount();
	const { updateSettings } = useDispatch( STORE_NAME );
	const [ review, setReview ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ retry, setRetry ] = useState( 0 );

	const currentSettings = useRef( settings );
	currentSettings.current = settings;
	const unsavedChanges = useRef( hasUnsavedChanges );
	unsavedChanges.current = hasUnsavedChanges;

	const resolve = useCallback(
		async ( source, revision ) => {
			const submitted = currentSettings.current;
			setBusy( true );
			try {
				const saved = await apiFetch( {
					path: endpoint,
					method: 'POST',
					data: { source, revision },
				} );
				const edits = Object.fromEntries(
					Object.entries( currentSettings.current ).filter(
						( [ key, value ] ) =>
							! isEqual( value, submitted[ key ] )
					)
				);
				updateSettings( { ...saved, ...edits } );
				onResolved( saved );
				setReview( null );
				setError( '' );
			} catch ( e ) {
				setError( e.message );
				setReview( null );
			} finally {
				setBusy( false );
			}
		},
		[ updateSettings, onResolved ]
	);

	useEffect( () => {
		if (
			isLoading ||
			isSaving ||
			isRefreshing ||
			settings.is_pmc_enabled
		) {
			setReview( null );
			return;
		}
		let cancelled = false;
		setReview( null );
		apiFetch( { path: endpoint } )
			.then( ( result ) => {
				if ( ! cancelled ) {
					setReview( result );
					if (
						result.revision &&
						! result.differs &&
						! unsavedChanges.current
					) {
						resolve( 'stripe', result.revision );
					}
					setError( '' );
				}
			} )
			.catch( ( e ) => {
				if ( ! cancelled ) {
					setError( e.message );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [
		isLoading,
		isSaving,
		isRefreshing,
		settings.is_pmc_enabled,
		settings.is_test_mode_enabled,
		retry,
		resolve,
	] );

	if ( settings.is_pmc_enabled || isLoading ) {
		return null;
	}

	if ( error ) {
		return (
			<InlineNotice
				status="warning"
				isDismissible={ false }
				spokenMessage={ error }
			>
				<p>{ error }</p>
				<Button
					variant="secondary"
					onClick={ () => setRetry( retry + 1 ) }
				>
					{ __( 'Refresh comparison', 'woocommerce-gateway-stripe' ) }
				</Button>
			</InlineNotice>
		);
	}
	if ( ! review?.differs ) {
		return null;
	}

	const dirty =
		hasUnsavedChanges ||
		! isEqual(
			selections( settings.enabled_payment_method_ids ),
			selections(
				review.local.upe_checkout_experience_accepted_payments.filter(
					( id ) =>
						settings.available_payment_method_ids?.includes( id )
				)
			)
		) ||
		settings.is_express_checkout_enabled !==
			( review.local.express_checkout === 'yes' );

	return (
		<InlineNotice
			status="warning"
			isDismissible={ false }
			spokenMessage={ __(
				'WooCommerce and Stripe have different payment methods enabled.',
				'woocommerce-gateway-stripe'
			) }
		>
			<p>
				<strong>
					{ __(
						'WooCommerce and Stripe have different payment methods enabled.',
						'woocommerce-gateway-stripe'
					) }
				</strong>
			</p>
			<p>
				{ sprintf(
					/* translators: 1: account ID, 2: Stripe configuration ID, 3: test/live mode. */
					__(
						'Account: %1$s. Configuration: %2$s (%3$s). Checkout uses your saved WooCommerce selections until you choose.',
						'woocommerce-gateway-stripe'
					),
					data?.account?.id || '',
					review.configuration_id,
					review.testmode
						? __( 'test mode', 'woocommerce-gateway-stripe' )
						: __( 'live mode', 'woocommerce-gateway-stripe' )
				) }
			</p>
			<p>
				{ sprintf(
					/* translators: %s: enabled payment methods. */
					__( 'WooCommerce: %s', 'woocommerce-gateway-stripe' ),
					describe( review.local )
				) }
			</p>
			<p>
				{ sprintf(
					/* translators: %s: enabled payment methods. */
					__( 'Stripe: %s', 'woocommerce-gateway-stripe' ),
					describe( { ...review.stripe, express_checkout: 'no' } ) +
						( review.wallets.apple_pay ? ', Apple Pay' : '' ) +
						( review.wallets.google_pay ? ', Google Pay' : '' )
				) }
			</p>
			<p>
				{ __(
					'Use WooCommerce updates this Stripe configuration, including for other stores sharing it. Use Stripe updates this store. Synchronization starts after the selected settings are verified.',
					'woocommerce-gateway-stripe'
				) }
			</p>
			<p>
				{ __(
					'WooCommerce groups Apple Pay and Google Pay. Use WooCommerce turns both wallets on or off together. Use Stripe turns the store’s wallet toggle on if either Stripe wallet is on.',
					'woocommerce-gateway-stripe'
				) }
			</p>
			{ dirty && (
				<p>
					{ __(
						'Save your changes before choosing.',
						'woocommerce-gateway-stripe'
					) }
				</p>
			) }
			<Button
				variant="primary"
				disabled={ busy || dirty || isSaving }
				onClick={ () => resolve( 'woocommerce', review.revision ) }
			>
				{ __( 'Use WooCommerce', 'woocommerce-gateway-stripe' ) }
			</Button>{ ' ' }
			<Button
				variant="secondary"
				disabled={ busy || dirty || isSaving }
				onClick={ () => resolve( 'stripe', review.revision ) }
			>
				{ __( 'Use Stripe', 'woocommerce-gateway-stripe' ) }
			</Button>
		</InlineNotice>
	);
};

export default PMCSyncNotice;
