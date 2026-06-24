import React, {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from 'react';
import { debounce } from 'lodash';
import { FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Number of product suggestions to request per search.
 *
 * @type {number}
 */
const SUGGESTIONS_PER_PAGE = 20;

/**
 * Debounce window (ms) before a search request is issued.
 *
 * @type {number}
 */
const SEARCH_DEBOUNCE_MS = 300;

/**
 * Lazy-loading multi-select for products, backed by the core
 * `wc/v3/products` endpoint.
 *
 * The control persists product IDs while displaying product names: a label
 * map (id ↔ name) is seeded from `initialLabels` and extended with every
 * search response, so selected tokens always render a human-readable name and
 * the `onChange` consumer always receives integer IDs.
 *
 * @param {Object}   props
 * @param {number[]} props.value         Selected product IDs.
 * @param {Function} props.onChange      Called with the updated array of IDs.
 * @param {string}   props.productType   `wc/v3/products` `type` filter (e.g. `simple`, `variable`).
 * @param {Array}    props.initialLabels `{ id, name }` pairs for already-selected products.
 * @param {string}   props.label         Field label.
 * @param {string}   [props.help]        Help text.
 * @param {string}   [props.placeholder] Input placeholder.
 * @return {JSX.Element} The token field.
 */
const AsyncProductSelect = ( {
	value,
	onChange,
	productType,
	initialLabels = [],
	label,
	help,
	placeholder,
} ) => {
	const [ suggestions, setSuggestions ] = useState( [] );

	// Persistent id ↔ name lookups. Kept in a ref so debounced async updates
	// don't depend on a render cycle and don't trigger re-renders themselves.
	const idToName = useRef( {} );
	const nameToId = useRef( {} );

	// Seed the maps from the labels resolved server-side for existing selections.
	useMemo( () => {
		initialLabels.forEach( ( { id, name } ) => {
			idToName.current[ id ] = name;
			nameToId.current[ name ] = id;
		} );
	}, [ initialLabels ] );

	const fetchSuggestions = useCallback(
		async ( search ) => {
			const query = new URLSearchParams( {
				per_page: String( SUGGESTIONS_PER_PAGE ),
				_fields: 'id,name',
				type: productType,
			} );
			if ( search ) {
				query.set( 'search', search );
			}

			try {
				const results = await apiFetch( {
					path: `/wc/v3/products?${ query.toString() }`,
				} );

				const names = ( results || [] ).map( ( product ) => {
					idToName.current[ product.id ] = product.name;
					nameToId.current[ product.name ] = product.id;
					return product.name;
				} );
				setSuggestions( names );
			} catch {
				// A failed lookup just yields no suggestions; the field stays usable.
				setSuggestions( [] );
			}
		},
		[ productType ]
	);

	const debouncedFetch = useMemo(
		() => debounce( fetchSuggestions, SEARCH_DEBOUNCE_MS ),
		[ fetchSuggestions ]
	);

	// Cancel any pending search when the debounced function is replaced
	// (e.g. productType changed) or the component unmounts, so a stale request
	// can't resolve into an unmounted/irrelevant field.
	useEffect( () => () => debouncedFetch.cancel(), [ debouncedFetch ] );

	const handleInputChange = useCallback(
		( input ) => {
			debouncedFetch( input );
		},
		[ debouncedFetch ]
	);

	// FormTokenField works in token strings; map IDs to their resolved names
	// and drop any ID we have no name for (e.g. a since-deleted product).
	const tokens = useMemo(
		() =>
			value
				.map( ( id ) => idToName.current[ id ] )
				.filter( ( name ) => Boolean( name ) ),
		[ value ]
	);

	const handleChange = useCallback(
		( nextTokens ) => {
			const ids = nextTokens
				.map( ( token ) => nameToId.current[ token ] )
				.filter( ( id ) => Boolean( id ) );
			// De-duplicate while preserving order.
			onChange( [ ...new Set( ids ) ] );
		},
		[ onChange ]
	);

	return (
		<FormTokenField
			label={ label }
			value={ tokens }
			suggestions={ suggestions }
			onInputChange={ handleInputChange }
			onChange={ handleChange }
			placeholder={
				placeholder ??
				__( 'Search for products…', 'woocommerce-gateway-stripe' )
			}
			help={ help }
			__experimentalExpandOnFocus
			__experimentalShowHowTo={ false }
		/>
	);
};

export default AsyncProductSelect;
