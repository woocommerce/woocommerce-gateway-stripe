<?php
/**
 * Admin template: Agentic Commerce Dashboard
 *
 * Renders the product feed sync monitoring dashboard.
 * This template is loaded by WC_Stripe_Agentic_Commerce_Admin_Dashboard::render_page().
 *
 * @package WooCommerce_Stripe
 * @since 10.5.0
 *
 * @var WC_Stripe_Agentic_Commerce_Admin_Dashboard $this Not in scope; methods called via $dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dashboard = new WC_Stripe_Agentic_Commerce_Admin_Dashboard();
?>
<div class="wrap wc-stripe-agentic-dashboard">
	<h1><?php esc_html_e( 'Agentic Commerce — Product Feed', 'woocommerce-gateway-stripe' ); ?></h1>
	<p class="description">
		<?php
		printf(
			/* translators: %s: Stripe dashboard link */
			esc_html__( 'Monitors the product feed sync status for the Agentic Commerce integration. View import results on the %s.', 'woocommerce-gateway-stripe' ),
			'<a href="https://dashboard.stripe.com/data-management/import-sets" target="_blank" rel="noopener noreferrer">'
				. esc_html__( 'Stripe Dashboard', 'woocommerce-gateway-stripe' )
			. '</a>'
		);
		?>
	</p>

	<div class="wc-stripe-agentic-card">
		<h2><?php esc_html_e( 'Product Feed Status', 'woocommerce-gateway-stripe' ); ?></h2>
		<?php $dashboard->render_sync_status(); ?>
	</div>

	<div class="wc-stripe-agentic-card">
		<h2><?php esc_html_e( 'Recent Syncs', 'woocommerce-gateway-stripe' ); ?></h2>
		<?php $dashboard->render_sync_history(); ?>
	</div>
</div>

<style>
.wc-stripe-agentic-dashboard h1 {
	font-size: 23px;
	font-weight: 400;
	margin: 0 0 10px;
	padding: 9px 0 4px;
}
.wc-stripe-agentic-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px 24px;
	margin: 16px 0;
	max-width: 900px;
}
.wc-stripe-agentic-card h2 {
	font-size: 14px;
	font-weight: 600;
	margin: 0 0 16px;
	padding: 0;
	border-bottom: 1px solid #eee;
	padding-bottom: 8px;
}
.wc-stripe-agentic-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
	margin-bottom: 12px;
}
.wc-stripe-agentic-badge.success { background: #d4edda; color: #155724; }
.wc-stripe-agentic-badge.error   { background: #f8d7da; color: #721c24; }
.wc-stripe-agentic-badge.warning { background: #fff3cd; color: #856404; }
.wc-stripe-agentic-badge.info    { background: #d1ecf1; color: #0c5460; }
.wc-stripe-agentic-details {
	border-collapse: collapse;
	margin-bottom: 12px;
	width: 100%;
}
.wc-stripe-agentic-details th {
	width: 160px;
	text-align: left;
	padding: 4px 8px 4px 0;
	font-weight: 600;
	vertical-align: top;
}
.wc-stripe-agentic-details td {
	padding: 4px 0;
}
.wc-stripe-agentic-actions {
	display: flex;
	gap: 8px;
	margin-top: 16px;
}
.wc-stripe-agentic-history-table {
	margin-top: 0;
}
.wc-stripe-agentic-history-table code {
	font-size: 11px;
}
</style>
