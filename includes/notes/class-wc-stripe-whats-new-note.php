<?php
/**
 * "What's new" inbox note shown after a plugin upgrade.
 *
 * Reaches auto-updaters, who never revisit `plugins.php` where the other
 * "what's new" surfaces fire.
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
	 * Version a merchant was last shown the note for; compared against
	 * WC_STRIPE_VERSION so the note fires for upgraders only.
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
				/* translators: %s: plugin version, e.g. "10.8.2". */
				__( 'WooCommerce Stripe %s: see what\'s new', 'woocommerce-gateway-stripe' ),
				WC_STRIPE_VERSION
			)
		);
		$note->set_content( __( 'You\'re now on the latest version of WooCommerce Stripe. Check the release notes to see the new features and improvements in this update.', 'woocommerce-gateway-stripe' ) );
		// The Inbox only renders info/marketing/survey/warning; "update" would
		// save but never display. "info" also dodges the marketing opt-out.
		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
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

		// Drop any earlier-release note first; the fixed note name would
		// otherwise block re-adding it.
		self::possibly_delete_note();
		self::possibly_add_note();

		// Only writer that advances the baseline to the current version, so the
		// note shows once per upgrade; record_install_version() never lowers it.
		update_option( self::LAST_SEEN_VERSION_OPTION, WC_STRIPE_VERSION );
	}

	/**
	 * Whether the merchant has upgraded past the version they last saw the note for.
	 *
	 * @return bool
	 */
	public static function is_upgrade_pending(): bool {
		$last_seen = get_option( self::LAST_SEEN_VERSION_OPTION );

		// No baseline yet: can't tell an upgrade from a fresh install, so stay
		// silent until record_install_version() seeds it. Reject non-strings
		// too — the option is untrusted and version_compare() fatals on arrays.
		if ( ! is_string( $last_seen ) || '' === $last_seen ) {
			return false;
		}

		return version_compare( $last_seen, WC_STRIPE_VERSION, '<' );
	}

	/**
	 * Seed the version baseline from the plugin's upgrade routine.
	 *
	 * Fresh installs bank the current version so the note never fires. Upgraders
	 * bank the version they came from, but only when no baseline exists yet, so
	 * we never lower a value init() already advanced (both run on the same
	 * `admin_init` pass in an undefined order).
	 *
	 * @param string|false $previous_version Version before this upgrade, or false on a fresh install.
	 * @return void
	 */
	public static function record_install_version( $previous_version ): void {
		if ( false === $previous_version ) {
			update_option( self::LAST_SEEN_VERSION_OPTION, WC_STRIPE_VERSION );
			return;
		}

		// `wc_stripe_version` is untrusted; never bank a non-string/empty value
		// as the baseline (is_upgrade_pending() would reject it anyway).
		if ( ! is_string( $previous_version ) || '' === $previous_version ) {
			return;
		}

		if ( false === get_option( self::LAST_SEEN_VERSION_OPTION ) ) {
			update_option( self::LAST_SEEN_VERSION_OPTION, $previous_version );
		}
	}

	/**
	 * Public changelog URL for the note action.
	 *
	 * An external (non-admin) URL is required for the Inbox to open the action
	 * in a new tab: it routes any link under the admin URL to the same tab and
	 * only `window.open()`s URLs outside it.
	 *
	 * @return string
	 */
	private static function get_changelog_url(): string {
		return 'https://wordpress.org/plugins/woocommerce-gateway-stripe/#developers';
	}
}
