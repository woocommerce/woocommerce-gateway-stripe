<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Note;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Notes;

/**
 * Util class for handling compatibilities with different versions of WooCommerce core.
 */
class WC_Stripe_Woo_Compat_Utils {
	/**
	 * Return non-deprecated class for instantiating WC-Admin notes.
	 *
	 * @return string
	 */
	public static function get_note_class() {
		if ( class_exists( 'Automattic\WooCommerce\Admin\Notes\Note' ) ) {
			return Note::class;
		}

		return WC_Admin_Note::class;
	}

	/**
	 * Return non-deprecated class for instantiating WC-Admin notes.
	 *
	 * @return string
	 */
	public static function get_notes_class() {
		if ( class_exists( 'Automattic\WooCommerce\Admin\Notes\Notes' ) ) {
			return Notes::class;
		}

		return WC_Admin_Notes::class;
	}
}
