import { addFilter } from '@wordpress/hooks';

addFilter(
	'woocommerce_admin_pages_list',
	'woocommerce-gateway-stripe',
	( pages ) => pages
);
