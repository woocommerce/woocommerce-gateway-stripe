import React, {
	forwardRef,
	useCallback,
	useEffect,
	useImperativeHandle,
	useState,
} from 'react';
import styled from '@emotion/styled';
import AsyncProductSelect from './async-product-select';
import TermCheckboxGroup from './term-checkbox-group';
import { RadioControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const FILTERS_PATH = '/wc/v3/wc_stripe/agentic-commerce/filters';

const FILTER_MODES = {
	PRODUCTS: 'products',
	VARIABLE_PRODUCTS: 'variable_products',
	TAXONOMIES: 'taxonomies',
};

const Wrapper = styled.div`
	margin-top: 8px;
`;

const ModeControls = styled.div`
	margin-top: 16px;
`;

/**
 * Derive the active filter mode from the persisted filters, following the same
 * precedence the backend uses:
 *  - Specific product IDs win over variable products.
 *  - Variable product IDs win over taxonomies.
 * Defaults to the taxonomies mode when nothing is set.
 *
 * @param {Object} filters Filter response from the REST endpoint.
 * @return {string} One of FILTER_MODES.
 */
const deriveMode = ( filters ) => {
	if ( filters.product_ids?.length ) {
		return FILTER_MODES.PRODUCTS;
	}
	if ( filters.variable_product_ids?.length ) {
		return FILTER_MODES.VARIABLE_PRODUCTS;
	}
	return FILTER_MODES.TAXONOMIES;
};

/**
 * Product filter configuration for the Agentic Commerce feed.
 *
 * Only one filter group is ever applied by the backend, so we show
 * a "Filter products by" selector and render only the chosen group's
 * controls. The component exposes an imperative `save()` (consumed by the
 * parent section's ref) that POSTs the active group populated and the others
 * empty, so switching modes clears the previously stored selection.
 */
const ProductFilters = forwardRef( ( props, ref ) => {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ mode, setMode ] = useState( FILTER_MODES.TAXONOMIES );
	const [ productIds, setProductIds ] = useState( [] );
	const [ variableProductIds, setVariableProductIds ] = useState( [] );
	const [ categoryIds, setCategoryIds ] = useState( [] );
	const [ tagIds, setTagIds ] = useState( [] );
	const [ brandIds, setBrandIds ] = useState( [] );
	const [ productLabels, setProductLabels ] = useState( [] );
	const [ variableProductLabels, setVariableProductLabels ] = useState( [] );
	const [ brandTaxonomyAvailable, setBrandTaxonomyAvailable ] =
		useState( false );

	useEffect( () => {
		let cancelled = false;

		apiFetch( { path: FILTERS_PATH } )
			.then( ( filters ) => {
				if ( cancelled ) {
					return;
				}
				setProductIds( filters.product_ids ?? [] );
				setVariableProductIds( filters.variable_product_ids ?? [] );
				setCategoryIds( filters.category_ids ?? [] );
				setTagIds( filters.tag_ids ?? [] );
				setBrandIds( filters.brand_ids ?? [] );
				setProductLabels( filters.products ?? [] );
				setVariableProductLabels( filters.variable_products ?? [] );
				setBrandTaxonomyAvailable(
					Boolean( filters.brand_taxonomy_available )
				);
				setMode( deriveMode( filters ) );
			} )
			.catch( () => {
				// Non-fatal: leave defaults so the rest of the section renders.
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setIsLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	// Persist only the active group; send the rest empty so the backend clears
	// them. Returns the POST promise so the parent can await it.
	const save = useCallback( () => {
		const payload = {
			product_ids: [],
			variable_product_ids: [],
			category_ids: [],
			tag_ids: [],
			brand_ids: [],
		};

		if ( mode === FILTER_MODES.PRODUCTS ) {
			payload.product_ids = productIds;
		} else if ( mode === FILTER_MODES.VARIABLE_PRODUCTS ) {
			payload.variable_product_ids = variableProductIds;
		} else {
			payload.category_ids = categoryIds;
			payload.tag_ids = tagIds;
			payload.brand_ids = brandTaxonomyAvailable ? brandIds : [];
		}

		return apiFetch( {
			path: FILTERS_PATH,
			method: 'POST',
			data: payload,
		} );
	}, [
		mode,
		productIds,
		variableProductIds,
		categoryIds,
		tagIds,
		brandIds,
		brandTaxonomyAvailable,
	] );

	useImperativeHandle( ref, () => ( { save } ), [ save ] );

	if ( isLoading ) {
		return <p>{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }</p>;
	}

	return (
		<Wrapper>
			<RadioControl
				label={ __(
					'Filter products by',
					'woocommerce-gateway-stripe'
				) }
				help={ __(
					'Choose which products are included in the feed synced to Stripe. Only one filter type can be active at a time.',
					'woocommerce-gateway-stripe'
				) }
				selected={ mode }
				options={ [
					{
						label: __(
							'Specific products',
							'woocommerce-gateway-stripe'
						),
						value: FILTER_MODES.PRODUCTS,
					},
					{
						label: __(
							'Variable products',
							'woocommerce-gateway-stripe'
						),
						value: FILTER_MODES.VARIABLE_PRODUCTS,
					},
					{
						label: __(
							'Categories, tags, and brands',
							'woocommerce-gateway-stripe'
						),
						value: FILTER_MODES.TAXONOMIES,
					},
				] }
				onChange={ setMode }
			/>

			<ModeControls>
				{ mode === FILTER_MODES.PRODUCTS && (
					<AsyncProductSelect
						label={ __( 'Products', 'woocommerce-gateway-stripe' ) }
						help={ __(
							'Search for simple products to include in the feed.',
							'woocommerce-gateway-stripe'
						) }
						productType="simple"
						value={ productIds }
						initialLabels={ productLabels }
						onChange={ setProductIds }
					/>
				) }

				{ mode === FILTER_MODES.VARIABLE_PRODUCTS && (
					<AsyncProductSelect
						label={ __(
							'Variable products',
							'woocommerce-gateway-stripe'
						) }
						help={ __(
							'Search for variable products; all their variations are included in the feed.',
							'woocommerce-gateway-stripe'
						) }
						productType="variable"
						value={ variableProductIds }
						initialLabels={ variableProductLabels }
						onChange={ setVariableProductIds }
					/>
				) }

				{ mode === FILTER_MODES.TAXONOMIES && (
					<>
						<TermCheckboxGroup
							title={ __(
								'Categories',
								'woocommerce-gateway-stripe'
							) }
							restBase="categories"
							value={ categoryIds }
							onChange={ setCategoryIds }
						/>
						<TermCheckboxGroup
							title={ __( 'Tags', 'woocommerce-gateway-stripe' ) }
							restBase="tags"
							value={ tagIds }
							onChange={ setTagIds }
						/>
						{ brandTaxonomyAvailable && (
							<TermCheckboxGroup
								title={ __(
									'Brands',
									'woocommerce-gateway-stripe'
								) }
								restBase="brands"
								value={ brandIds }
								onChange={ setBrandIds }
							/>
						) }
					</>
				) }
			</ModeControls>
		</Wrapper>
	);
} );

ProductFilters.displayName = 'ProductFilters';

export default ProductFilters;
