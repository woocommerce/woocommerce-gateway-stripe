<?php
/**
 * Plugin Name: WC Stripe Worktree Indicator
 * Description: Shows the active git worktree id in the admin bar so multiple parallel WordPress instances are easy to tell apart.
 * Version: 1.0.0
 * Author: WooCommerce
 *
 * This mu-plugin is seeded into the shared wcstripe-mu-plugins Docker volume
 * by bin/docker-infra-up.sh. The worktree id is read from the WORKTREE_ID
 * environment variable that docker-compose.yml passes through.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wcstripe_worktree_id' ) ) {
	function wcstripe_worktree_id() {
		$id = getenv( 'WORKTREE_ID' );
		if ( false === $id || '' === $id ) {
			$id = $_SERVER['WORKTREE_ID'] ?? 'default';
		}
		return $id;
	}
}

add_action( 'admin_bar_menu', function ( $wp_admin_bar ) {
	$id = wcstripe_worktree_id();
	$wp_admin_bar->add_node( [
		'id'    => 'wcstripe-worktree',
		'title' => '[' . esc_html( $id ) . ']',
		'href'  => false,
		'meta'  => [
			'title' => 'WooCommerce Stripe worktree: ' . esc_attr( $id ),
		],
	] );
}, 100 );

add_action( 'admin_head', function () {
	echo '<style>#wp-admin-bar-wcstripe-worktree > .ab-item { background:#7f54b3; color:#fff !important; font-weight:600; }</style>';
} );
