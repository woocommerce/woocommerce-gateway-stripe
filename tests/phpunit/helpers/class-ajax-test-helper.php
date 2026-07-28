<?php

/**
 * Provides useful methods to test logic related to AJAX requests.
 */
class Ajax_Test_Helper {
	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init_hooks(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', [ self::class, 'wp_ajax_halt_handler_filter' ] );
	}

	public static function remove_hooks(): void {
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', [ self::class, 'wp_ajax_halt_handler_filter' ] );
	}

	/**
	 * Filter to return a custom handler for AJAX requests.
	 *
	 * @return callable The custom handler function.
	 */
	public static function wp_ajax_halt_handler_filter(): callable {
		return [ self::class, 'wp_ajax_print_handler' ];
	}

	/**
	 * Custom handler function to output the message.
	 *
	 * @param string $message The message to print.
	 */
	public static function wp_ajax_print_handler( string $message ): void {
		echo wp_kses_post( $message );
	}
}
