<?php
/**
 * Jetpack Changelogger formatter for changelog.txt.
 *
 * @package WooCommerce_Stripe
 */

use Automattic\Jetpack\Changelog\Changelog;
use Automattic\Jetpack\Changelog\Parser;
use Automattic\Jetpack\Changelogger\FormatterPlugin;
use Automattic\Jetpack\Changelogger\PluginTrait;

/**
 * Reads and writes changelog.txt in the WooCommerce.com format:
 *
 *     *** Changelog ***
 *
 *     YYYY-MM-DD - version X.Y.Z
 *     * Type - Message
 *
 * The changelogger re-renders the whole file on every write, and the history contains
 * lines that do not follow the `* Type - Message` shape (padded types, en dashes, free
 * text). To guarantee a write never alters released sections, each existing section's
 * body is carried verbatim as the entry prologue rather than being parsed into changes.
 * Only changes added by the current write are rendered from change entries, and they are
 * appended after that verbatim body, which is also what makes `--amend` append to an
 * existing section.
 */
class WC_Stripe_Changelog_Formatter extends Parser implements FormatterPlugin {
	use PluginTrait;

	private const TITLE = '*** Changelog ***';

	/**
	 * Date written for a version that has not been released. The release tooling replaces
	 * the first header carrying this placeholder with the real date, and fails if none exists.
	 */
	private const UNRELEASED_DATE = 'xxxx-xx-xx';

	private const HEADING_PATTERN = '/^(\S+) - version (\S+)[ \t]*$/m';

	/**
	 * Parse changelog data into a Changelog object.
	 *
	 * @param string $changelog Changelog contents.
	 * @return Changelog
	 * @throws InvalidArgumentException If a version heading carries an unparseable date.
	 */
	public function parse( $changelog ) {
		$ret       = new Changelog();
		$changelog = strtr( $changelog, [ "\r\n" => "\n" ] );

		// PREG_SPLIT_DELIM_CAPTURE yields [ preamble, date, version, body, date, version, body, ... ].
		$parts = preg_split( self::HEADING_PATTERN, $changelog, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command-line tool; the message is printed to a terminal.
			throw new InvalidArgumentException( 'Failed to parse changelog: PCRE error ' . preg_last_error() );
		}

		$entries     = [];
		$parts_count = count( $parts );
		for ( $i = 1; $i + 1 < $parts_count; $i += 3 ) {
			$date    = $parts[ $i ];
			$version = $parts[ $i + 1 ];
			$body    = $parts[ $i + 2 ] ?? '';

			if ( self::UNRELEASED_DATE === $date ) {
				$timestamp = null;
			} else {
				$timestamp = DateTime::createFromFormat( '!Y-m-d', $date, new DateTimeZone( 'UTC' ) );
				if ( false === $timestamp || $timestamp->format( 'Y-m-d' ) !== $date ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command-line tool; the message is printed to a terminal.
					throw new InvalidArgumentException( "Heading for version $version has an invalid date: $date" );
				}
			}

			$entries[] = $this->newChangelogEntry(
				$version,
				[
					'timestamp' => $timestamp,
					'prologue'  => trim( $body, "\n" ),
				]
			);
		}

		$ret->setEntries( $entries );

		return $ret;
	}

	/**
	 * Write a Changelog object to a string.
	 *
	 * @param Changelog $changelog Changelog object.
	 * @return string
	 */
	public function format( Changelog $changelog ) {
		$sections = [];

		foreach ( $changelog->getEntries() as $entry ) {
			$timestamp = $entry->getTimestamp();
			$date      = null === $timestamp ? self::UNRELEASED_DATE : $timestamp->format( 'Y-m-d' );
			$lines     = [ $date . ' - version ' . $entry->getVersion() ];

			$prologue = trim( $entry->getPrologue(), "\n" );
			if ( '' !== $prologue ) {
				$lines[] = $prologue;
			}

			foreach ( $entry->getChanges() as $change ) {
				// Entries are written without a trailing period, per the changelog style guide.
				$text = preg_replace( '/\.$/', '', trim( $change->getContent() ) );
				if ( '' === $text ) {
					continue;
				}
				$lines[] = '* ' . $change->getSubheading() . ' - ' . $text;
			}

			$sections[] = implode( "\n", $lines );
		}

		return self::TITLE . "\n\n" . implode( "\n\n", $sections ) . "\n";
	}
}
