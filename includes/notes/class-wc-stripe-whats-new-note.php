<?php
/**
 * Surfaces a "What's new" inbox note after a plugin upgrade.
 *
 * The Plugins-screen "what's new" surfaces only fire on `plugins.php`, which
 * stores on auto-update never revisit. This note reaches those merchants on
 * their next wp-admin visit regardless of how the plugin was updated.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Automattic\WooCommerce\Admin\Notes\Note;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Stripe_Whats_New_Note
 */
final class WC_Stripe_Whats_New_Note {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	public const NOTE_NAME = 'wc-stripe-whats-new-note';

	/**
	 * Option recording the plugin version a merchant was last shown the note
	 * for. It is compared against WC_STRIPE_VERSION so the note fires for
	 * upgraders only.
	 */
	public const LAST_SEEN_VERSION_OPTION = 'wc_stripe_whats_new_note_version';

	/**
	 * Get the note.
	 *
	 * @return Note
	 */
	public static function get_note() {
		$note = new Note();

		$note->set_title(
			sprintf(
				/* translators: %s: plugin version, e.g. "10.9". */
				__( 'WooCommerce Stripe %s: see what\'s new', 'woocommerce-gateway-stripe' ),
				self::get_marketing_version()
			)
		);
		$note->set_content( __( 'You\'re now on the latest version of WooCommerce Stripe. Check the release notes to see the new features and improvements in this update.', 'woocommerce-gateway-stripe' ) );
		$note->set_type( Note::E_WC_ADMIN_NOTE_UPDATE );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-gateway-stripe' );
		$note->add_action(
			'view-whats-new',
			__( 'See what\'s new', 'woocommerce-gateway-stripe' ),
			self::get_changelog_url(),
			Note::E_WC_ADMIN_NOTE_ACTIONED,
			true
		);

		return $note;
	}

	/**
	 * Show the note to merchants who just upgraded.
	 *
	 * @return void
	 * @throws \Automattic\WooCommerce\Admin\Notes\NotesUnavailableException
	 */
	public static function init() {
		if ( ! defined( 'WC_STRIPE_VERSION' ) || ! self::is_upgrade_pending() ) {
			return;
		}

		// Drop any note left over from an earlier release so a single, current
		// note surfaces per upgrade; the fixed note name would otherwise block
		// re-adding it.
		self::possibly_delete_note();
		self::possibly_add_note();

		// Bank the running version so the note shows once per upgrade. This is
		// the only writer that advances the baseline to the current version;
		// record_install_version() never rolls it back below this value.
		update_option( self::LAST_SEEN_VERSION_OPTION, WC_STRIPE_VERSION );
	}

	/**
	 * Whether the merchant has upgraded past the version they last saw the note for.
	 *
	 * @return bool
	 */
	public static function is_upgrade_pending(): bool {
		$last_seen = get_option( self::LAST_SEEN_VERSION_OPTION );

		// No baseline yet: we cannot tell an upgrade from a fresh install, so
		// stay silent. record_install_version() seeds the baseline on the
		// upgrade/activation pass and a later visit will surface the note.
		if ( false === $last_seen || '' === $last_seen ) {
			return false;
		}

		return version_compare( $last_seen, WC_STRIPE_VERSION, '<' );
	}

	/**
	 * Seed the version baseline from the plugin's upgrade routine.
	 *
	 * Fresh installs (no previous version) bank the current version so the note
	 * never fires for them. Upgraders bank the version they came from, but only
	 * when no baseline exists yet, so we never roll back a value init() has
	 * already advanced to the current version (the two run on the same
	 * `admin_init` pass in an undefined order).
	 *
	 * @param string|false $previous_version Version stored before this upgrade, or false on a fresh install.
	 * @return void
	 */
	public static function record_install_version( $previous_version ): void {
		if ( false === $previous_version ) {
			update_option( self::LAST_SEEN_VERSION_OPTION, WC_STRIPE_VERSION );
			return;
		}

		if ( false === get_option( self::LAST_SEEN_VERSION_OPTION ) ) {
			update_option( self::LAST_SEEN_VERSION_OPTION, $previous_version );
		}
	}

	/**
	 * In-admin changelog deep link for the note action.
	 *
	 * Unlike the Plugins-screen "Release notes" link, this omits the thickbox
	 * iframe params: an inbox-note action is a plain navigation, so it must
	 * resolve to a full admin page rather than a modal that needs thickbox JS.
	 *
	 * @return string
	 */
	private static function get_changelog_url(): string {
		return self_admin_url(
			'plugin-install.php?tab=plugin-information&plugin=woocommerce-gateway-stripe&section=changelog'
		);
	}

	/**
	 * Major.minor of the running version for the headline (e.g. "10.9.2" -> "10.9").
	 *
	 * @return string
	 */
	private static function get_marketing_version(): string {
		$parts = explode( '.', WC_STRIPE_VERSION );

		return isset( $parts[1] ) ? $parts[0] . '.' . $parts[1] : WC_STRIPE_VERSION;
	}
}
