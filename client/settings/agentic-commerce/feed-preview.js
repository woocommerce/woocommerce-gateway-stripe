import React, { useState, useCallback } from 'react';
import styled from '@emotion/styled';
import { chevronDown, chevronUp } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { createInterpolateElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardHeader,
	ExternalLink,
	Flex,
	Notice,
} from '@wordpress/components';
import CardBody from 'wcstripe/settings/card-body';

const SectionCard = styled( Card )`
	margin-bottom: 20px;

	&:last-child {
		margin-bottom: 0;
	}
`;

const SectionHeading = styled.h2`
	margin: 0;
	font-size: 14px;
`;

const SummaryRow = styled( Flex )`
	margin: 16px 0;
	flex-wrap: wrap;
	gap: 24px;
`;

const SummaryStat = styled.div`
	flex: 0 0 auto;

	.wc-stripe-agentic-preview__stat-value {
		display: block;
		font-size: 24px;
		font-weight: 600;
		line-height: 1.2;
	}

	.wc-stripe-agentic-preview__stat-label {
		color: #646970;
		font-size: 12px;
	}

	&.is-included .wc-stripe-agentic-preview__stat-value {
		color: #005c12;
	}

	&.is-invalid .wc-stripe-agentic-preview__stat-value {
		color: #8a2424;
	}
`;

const ErrorsTable = styled.table`
	width: 100%;
	border-collapse: collapse;
	margin-top: 8px;

	th,
	td {
		text-align: left;
		padding: 8px;
		border-bottom: 1px solid #f0f0f0;
		vertical-align: top;
	}

	th {
		font-weight: 600;
		background: #f9f9f9;
	}

	tr:last-child td {
		border-bottom: none;
	}

	// Drop the bullet indent so a single issue lines up with the "Issues"
	// column header instead of sitting in from it.
	ul {
		margin: 0;
		padding-left: 0;
		list-style: none;
	}

	li + li {
		margin-top: 4px;
	}

	.wc-stripe-agentic-preview__product-col {
		width: 240px;
	}
`;

const IntroDescription = styled.p`
	margin-top: 16px;
`;

const ExcludedDetails = styled.div`
	margin: 8px 0 16px;
`;

const ExcludedBreakdown = styled.ul`
	margin: 8px 0 4px;
	padding-left: 18px;
	color: #646970;
	font-size: 12px;

	li {
		margin: 2px 0;
	}
`;

const ExcludedNote = styled.p`
	margin: 0;
	color: #646970;
	font-size: 12px;
`;

const AgenticCommerceFeedPreview = () => {
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ hasError, setHasError ] = useState( false );
	const [ showExcludedDetails, setShowExcludedDetails ] = useState( false );

	const fetchPreview = useCallback( async () => {
		setIsLoading( true );
		setHasError( false );
		try {
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/preview',
			} );
			setData( result );
		} catch ( err ) {
			setHasError( true );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	const {
		total_count: totalCount,
		included_count: includedCount,
		excluded_count: excludedCount,
		excluded_breakdown: excludedBreakdown,
		invalid_count: invalidCount,
		validation_errors: validationErrors,
		truncated,
		scan_limited: scanLimited,
	} = data ?? {};

	const excludedSubscriptions = excludedBreakdown?.subscriptions ?? 0;
	const excludedFiltered = excludedBreakdown?.filtered ?? 0;

	return (
		<SectionCard>
			<CardHeader>
				<SectionHeading>
					{ __( 'Feed preview', 'woocommerce-gateway-stripe' ) }
				</SectionHeading>
			</CardHeader>
			<CardBody>
				<IntroDescription>
					{ __(
						'Check which products will be made available to AI agents on the next sync, and which would be skipped because of missing or invalid data, without uploading anything.',
						'woocommerce-gateway-stripe'
					) }
				</IntroDescription>

				{ hasError && (
					<Notice status="error" isDismissible={ false }>
						{ createInterpolateElement(
							__(
								'Could not build the feed preview. Check the <logsLink>WooCommerce logs</logsLink> for details.',
								'woocommerce-gateway-stripe'
							),
							{
								logsLink: (
									<ExternalLink href="/wp-admin/admin.php?page=wc-status&tab=logs" />
								),
							}
						) }
					</Notice>
				) }

				{ data && (
					<>
						{ scanLimited && (
							<Notice status="warning" isDismissible={ false }>
								{ sprintf(
									/* translators: %s: number of products checked. */
									__(
										'This preview covers the first %s products. The full sync still processes your entire catalog.',
										'woocommerce-gateway-stripe'
									),
									totalCount.toLocaleString()
								) }
							</Notice>
						) }

						<SummaryRow justify="flex-start">
							<SummaryStat className="is-included">
								<span className="wc-stripe-agentic-preview__stat-value">
									{ includedCount.toLocaleString() }
								</span>
								<span className="wc-stripe-agentic-preview__stat-label">
									{ __(
										'Included',
										'woocommerce-gateway-stripe'
									) }
								</span>
							</SummaryStat>
							<SummaryStat className="is-invalid">
								<span className="wc-stripe-agentic-preview__stat-value">
									{ invalidCount.toLocaleString() }
								</span>
								<span className="wc-stripe-agentic-preview__stat-label">
									{ __(
										'With errors',
										'woocommerce-gateway-stripe'
									) }
								</span>
							</SummaryStat>
							<SummaryStat>
								<span className="wc-stripe-agentic-preview__stat-value">
									{ excludedCount.toLocaleString() }
								</span>
								<span className="wc-stripe-agentic-preview__stat-label">
									{ __(
										'Excluded',
										'woocommerce-gateway-stripe'
									) }
								</span>
							</SummaryStat>
						</SummaryRow>

						{ excludedCount > 0 && (
							<ExcludedDetails>
								<Button
									variant="link"
									icon={
										showExcludedDetails
											? chevronUp
											: chevronDown
									}
									iconPosition="right"
									aria-expanded={ showExcludedDetails }
									onClick={ () =>
										setShowExcludedDetails(
											( shown ) => ! shown
										)
									}
								>
									{ __(
										'Details',
										'woocommerce-gateway-stripe'
									) }
								</Button>
								{ showExcludedDetails && (
									<>
										<ExcludedBreakdown>
											{ excludedSubscriptions > 0 && (
												<li>
													{ sprintf(
														/* translators: %s: number of subscription products. */
														_n(
															'%s subscription product — subscriptions aren’t supported by AI agents.',
															'%s subscription products — subscriptions aren’t supported by AI agents.',
															excludedSubscriptions,
															'woocommerce-gateway-stripe'
														),
														excludedSubscriptions.toLocaleString()
													) }
												</li>
											) }
											{ excludedFiltered > 0 && (
												<li>
													{ sprintf(
														/* translators: %s: number of products hidden by visibility settings. */
														_n(
															'%s product hidden by your product visibility settings.',
															'%s products hidden by your product visibility settings.',
															excludedFiltered,
															'woocommerce-gateway-stripe'
														),
														excludedFiltered.toLocaleString()
													) }
												</li>
											) }
										</ExcludedBreakdown>
										<ExcludedNote>
											{ __(
												'These products aren’t sent to AI agents and aren’t validation errors.',
												'woocommerce-gateway-stripe'
											) }
										</ExcludedNote>
									</>
								) }
							</ExcludedDetails>
						) }

						{ !! validationErrors?.length && (
							<>
								<p>
									<strong>
										{ __(
											'Products that need attention',
											'woocommerce-gateway-stripe'
										) }
									</strong>
								</p>
								<ErrorsTable>
									<thead>
										<tr>
											<th className="wc-stripe-agentic-preview__product-col">
												{ __(
													'Product',
													'woocommerce-gateway-stripe'
												) }
											</th>
											<th>
												{ __(
													'Issues',
													'woocommerce-gateway-stripe'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ validationErrors.map( ( entry ) => (
											<tr key={ entry.product_id }>
												<td className="wc-stripe-agentic-preview__product-col">
													{ entry.edit_link ? (
														<ExternalLink
															href={
																entry.edit_link
															}
														>
															{
																entry.product_name
															}
														</ExternalLink>
													) : (
														entry.product_name
													) }
												</td>
												<td>
													<ul>
														{ entry.errors.map(
															( message, i ) => (
																<li key={ i }>
																	{ message }
																</li>
															)
														) }
													</ul>
												</td>
											</tr>
										) ) }
									</tbody>
								</ErrorsTable>

								{ truncated > 0 && (
									<p>
										{ sprintf(
											/* translators: %d: number of additional products with errors not shown. */
											_n(
												'… and %d more product with errors not shown.',
												'… and %d more products with errors not shown.',
												truncated,
												'woocommerce-gateway-stripe'
											),
											truncated
										) }
									</p>
								) }
							</>
						) }

						{ ! validationErrors?.length && (
							<p>
								{ __(
									'No validation errors found. All selected products are ready for agents.',
									'woocommerce-gateway-stripe'
								) }
							</p>
						) }
					</>
				) }

				<Flex justify="flex-start" gap={ 2 }>
					<Button
						variant="secondary"
						isBusy={ isLoading }
						disabled={ isLoading }
						onClick={ fetchPreview }
					>
						{ isLoading
							? __( 'Checking…', 'woocommerce-gateway-stripe' )
							: __(
									'Preview feed',
									'woocommerce-gateway-stripe'
							  ) }
					</Button>
				</Flex>
			</CardBody>
		</SectionCard>
	);
};

export default AgenticCommerceFeedPreview;
