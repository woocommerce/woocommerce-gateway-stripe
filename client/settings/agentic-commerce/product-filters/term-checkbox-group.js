import React, { useCallback, useEffect, useState } from 'react';
import styled from '@emotion/styled';
import { CheckboxControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Terms requested per page when fetching a taxonomy list.
 *
 * @type {number}
 */
const TERMS_PER_PAGE = 100;

const GroupHeading = styled.h4`
	margin: 16px 0 8px;
`;

const TermList = styled.div`
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	gap: 4px;
	padding: 3px;
	max-height: 220px;
	overflow-y: auto;
`;

/**
 * Fetch a single page of terms for a taxonomy.
 *
 * Uses `parse: false` so the `X-WP-TotalPages` header is readable.
 *
 * @param {string} restBase The products sub-resource (e.g. `categories`, `tags`, `brands`).
 * @param {number} page     1-based page number.
 * @return {Promise<{terms: Array, totalPages: number}>} Page terms and the total page count.
 */
const fetchTermsPage = async ( restBase, page ) => {
	const query = new URLSearchParams( {
		per_page: String( TERMS_PER_PAGE ),
		page: String( page ),
		_fields: 'id,name',
		orderby: 'name',
		order: 'asc',
	} );

	const response = await apiFetch( {
		path: `/wc/v3/products/${ restBase }?${ query.toString() }`,
		parse: false,
	} );

	const terms = await response.json();
	const totalPages = parseInt(
		response.headers.get( 'X-WP-TotalPages' ) || '1',
		10
	);

	return { terms, totalPages };
};

/**
 * Fetch every term for a taxonomy.
 *
 * Page 1 is fetched first to learn the total page count, then any remaining
 * pages are fetched concurrently to keep the load time close to a single
 * round-trip for large taxonomies.
 *
 * @param {string} restBase The products sub-resource (e.g. `categories`, `tags`, `brands`).
 * @return {Promise<Array>} Resolved `{ id, name }` term list, page order preserved.
 */
const fetchAllTerms = async ( restBase ) => {
	const { terms: firstPageTerms, totalPages } = await fetchTermsPage(
		restBase,
		1
	);

	if ( totalPages <= 1 ) {
		return firstPageTerms;
	}

	const remainingPages = [];
	for ( let page = 2; page <= totalPages; page++ ) {
		remainingPages.push( fetchTermsPage( restBase, page ) );
	}

	const rest = await Promise.all( remainingPages );

	return rest.reduce(
		( all, { terms } ) => all.concat( terms ),
		firstPageTerms
	);
};

/**
 * Checkbox list for a single product taxonomy, rendered in its own section.
 *
 * @param {Object}   props
 * @param {string}   props.title    Section heading.
 * @param {string}   props.restBase `wc/v3/products` sub-resource to query.
 * @param {number[]} props.value    Selected term IDs.
 * @param {Function} props.onChange Called with the updated array of term IDs.
 * @return {JSX.Element} The taxonomy section.
 */
const TermCheckboxGroup = ( { title, restBase, value, onChange } ) => {
	const [ terms, setTerms ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );

	useEffect( () => {
		let cancelled = false;
		setIsLoading( true );

		fetchAllTerms( restBase )
			.then( ( fetched ) => {
				if ( ! cancelled ) {
					setTerms( fetched );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setTerms( [] );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setIsLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ restBase ] );

	const toggleTerm = useCallback(
		( termId, isChecked ) => {
			if ( isChecked ) {
				onChange( [ ...new Set( [ ...value, termId ] ) ] );
			} else {
				onChange( value.filter( ( id ) => id !== termId ) );
			}
		},
		[ value, onChange ]
	);

	return (
		<div>
			<GroupHeading>{ title }</GroupHeading>
			{ isLoading && <Spinner /> }
			{ ! isLoading && terms.length === 0 && (
				<p>{ __( 'No items found.', 'woocommerce-gateway-stripe' ) }</p>
			) }
			{ ! isLoading && terms.length > 0 && (
				<TermList>
					{ terms.map( ( term ) => (
						<CheckboxControl
							key={ term.id }
							label={ term.name }
							checked={ value.includes( term.id ) }
							onChange={ ( isChecked ) =>
								toggleTerm( term.id, isChecked )
							}
						/>
					) ) }
				</TermList>
			) }
		</div>
	);
};

export default TermCheckboxGroup;
