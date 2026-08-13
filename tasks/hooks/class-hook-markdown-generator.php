#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Builds a public hook reference from wp-hooks/generator JSON output.
 *
 * Usage:
 *   php generate-markdown.php
 *   php generate-markdown.php --output=../../docs/hooks.md
 */

final class Hook_Markdown_Generator {
	private string $actions_file;
	private string $filters_file;
	private string $schema_file;
	private string $output_file;
	private string $github_base;
	private string $git_ref;
	private string $git_commit;

	/**
	 * Creates a new hook markdown generator from the CLI options.
	 *
	 * @param array<string,string|false> $options CLI options returned by getopt()
	 * @return self The hook markdown generator.
	 * @throws RuntimeException If the actions, filters, or schema file is missing.
	 */
	public static function from_options( array $options ): self {
		$base_dir        = __DIR__;
		$repository_root = dirname( $base_dir, 2 );

		$actions_file = realpath( self::string_option( $options, 'actions', $base_dir . '/tmp/actions.json' ) );
		$filters_file = realpath( self::string_option( $options, 'filters', $base_dir . '/tmp/filters.json' ) );
		$schema_file  = realpath( self::string_option( $options, 'schema', $base_dir . '/vendor/wp-hooks/generator/schema.json' ) );

		if ( false === $actions_file || false === $filters_file || false === $schema_file ) {
			throw new RuntimeException( 'Missing actions, filters, or schema file.' );
		}

		return new self(
			$actions_file,
			$filters_file,
			$schema_file,
			self::string_option( $options, 'output', $repository_root . '/docs/hooks.md' ),
			rtrim(
				self::string_option(
					$options,
					'github-base',
					'https://github.com/woocommerce/woocommerce-gateway-stripe/blob'
				),
				'/'
			),
			self::string_option( $options, 'ref', 'trunk' ),
			self::string_option( $options, 'commit', '' )
		);
	}

	private function __construct(
		string $actions_file,
		string $filters_file,
		string $schema_file,
		string $output_file,
		string $github_base,
		string $git_ref,
		string $git_commit
	) {
		$this->actions_file = $actions_file;
		$this->filters_file = $filters_file;
		$this->schema_file  = $schema_file;
		$this->output_file  = $output_file;
		$this->github_base  = $github_base;
		$this->git_ref      = $git_ref;
		$this->git_commit   = $git_commit;
	}

	public function run(): void {
		$schema = $this->read_json_file( $this->schema_file );
		if ( 'HooksContainer' !== ( $schema['title'] ?? null ) ) {
			throw new RuntimeException( "Unexpected hook schema in {$this->schema_file}." );
		}

		$markdown = $this->render_document(
			$this->read_hooks_file( $this->actions_file ),
			$this->read_hooks_file( $this->filters_file )
		);

		$output_dir = dirname( $this->output_file );
		if ( ! is_dir( $output_dir ) && ! mkdir( $output_dir, 0777, true ) && ! is_dir( $output_dir ) ) {
			throw new RuntimeException( "Unable to create output directory: {$output_dir}" );
		}

		if ( false === file_put_contents( $this->output_file, $markdown ) ) {
			throw new RuntimeException( "Unable to write hook documentation to {$this->output_file}." );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		printf( "Wrote %s\n", $this->output_file );
	}

	/**
	 * Returns a string option from the CLI options.
	 *
	 * @param array<string,string|false> $options The CLI options.
	 * @param string $name The name of the option.
	 * @param string $default The default value of the option.
	 * @return string The value of the option.
	 */
	private static function string_option( array $options, string $name, string $default ): string {
		$value = $options[ $name ] ?? $default;

		return false === $value ? $default : (string) $value;
	}

	/**
	 * Reads a JSON file and returns the decoded data.
	 *
	 * @param string $path The path to the JSON file.
	 * @return array<string,mixed> The decoded data.
	 */
	private function read_json_file( string $path ): array {
		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			throw new RuntimeException( "Unable to read {$path}" );
		}

		$data = json_decode( $contents, true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( "Unable to decode JSON from {$path}" );
		}

		return $data;
	}

	/**
	 * Reads a hooks file and validates the data against the expected schema.
	 *
	 * @param string $path The path to the hooks file.
	 * @return array<int,array<string,mixed>> The hooks data.
	 */
	private function read_hooks_file( string $path ): array {
		$data = $this->read_json_file( $path );

		if ( ! isset( $data['$schema'], $data['hooks'] ) || ! is_array( $data['hooks'] ) ) {
			throw new RuntimeException( "{$path} does not match the expected hooks container schema from {$this->schema_file}" );
		}

		foreach ( $data['hooks'] as $index => $hook ) {
			$this->validate_hook( $hook, "{$path} hooks[{$index}]" );
		}

		return $data['hooks'];
	}

	/**
	 * Validates a hook against the expected schema.
	 *
	 * @param array $hook The hook data.
	 * @param string $label The label of the hook.
	 */
	private function validate_hook( $hook, string $label ): void {
		$required = [ 'name', 'file', 'type', 'doc', 'args' ];
		foreach ( $required as $property ) {
			if ( ! is_array( $hook ) || ! array_key_exists( $property, $hook ) ) {
				throw new RuntimeException( "{$label} is missing {$property}; expected schema {$this->schema_file}" );
			}
		}

		if ( ! is_array( $hook['doc'] ) || ! isset( $hook['doc']['description'], $hook['doc']['long_description'], $hook['doc']['tags'] ) || ! is_array( $hook['doc']['tags'] ) ) {
			throw new RuntimeException( "{$label} has an invalid doc object; expected schema {$this->schema_file}" );
		}
	}

	/**
	 * Renders the entire document.
	 *
	 * @param array<int,array<string,mixed>> $actions The actions to render.
	 * @param array<int,array<string,mixed>> $filters The filters to render.
	 * @return string The markdown text of the document.
	 */
	private function render_document( array $actions, array $filters ): string {
		$lines = [
			'# WooCommerce Stripe Gateway Hook Reference',
			'',
		];

		$git_commit_comment = $this->git_commit ? " (Commit: {$this->git_commit})" : '';

		if ( str_starts_with( $this->git_ref, 'release/' ) ) {
			$release_version = str_replace( 'release/', '', $this->git_ref );
			$lines[]         = "_Generated for release {$release_version}{$git_commit_comment}_";
			$lines[]         = '';
		} else {
			$lines[] = "_Generated for {$this->git_ref}{$git_commit_comment}_";
			$lines[] = '';
		}

		$grouped_actions = $this->group_hooks_by_name( $actions );
		$grouped_filters = $this->group_hooks_by_name( $filters );

		$deprecated_actions = $this->extract_deprecated_hooks( 'action', $grouped_actions );
		$deprecated_filters = $this->extract_deprecated_hooks( 'filter', $grouped_filters );

		$lines[] = '> [!NOTE]';
		$lines[] = '> We are unable to provide support for custom code under [our Support Policy](https://woocommerce.com/support-policy/#customization). If you need assistance with custom code, we highly recommend [Codeable](https://www.codeable.io/partners/woocommerce/?ref=OaWImk) or a [Certified WooExpert](https://partners.woocommerce.com/English/marketplace/).';
		$lines[] = '';

		$lines[] = '## Contents';
		$lines[] = '';

		$action_index_lines = [];
		foreach ( array_keys( $grouped_actions ) as $action_name ) {
			$action_index_lines[] = $this->render_index_entry( $action_name );
		}
		$lines = array_merge(
			$lines,
			$this->render_details_index_section( 'Actions', '#actions', $action_index_lines )
		);

		$filter_index_lines = [];
		foreach ( array_keys( $grouped_filters ) as $filter_name ) {
			$filter_index_lines[] = $this->render_index_entry( $filter_name );
		}
		$lines = array_merge(
			$lines,
			$this->render_details_index_section( 'Filters', '#filters', $filter_index_lines )
		);

		if ( [] !== $deprecated_actions ) {
			$deprecated_action_index_lines = [];

			foreach ( array_keys( $deprecated_actions ) as $deprecated_action_name ) {
				$deprecated_action_index_lines[] = $this->render_index_entry( $deprecated_action_name );
			}
			$lines = array_merge(
				$lines,
				$this->render_details_index_section( 'Deprecated Actions', '#deprecated-actions', $deprecated_action_index_lines )
			);
		}

		if ( [] !== $deprecated_filters ) {
			$deprecated_filter_index_lines = [];
			foreach ( array_keys( $deprecated_filters ) as $deprecated_filter_name ) {
				$deprecated_filter_index_lines[] = $this->render_index_entry( $deprecated_filter_name );
			}
			$lines = array_merge(
				$lines,
				$this->render_details_index_section( 'Deprecated Filters', '#deprecated-filters', $deprecated_filter_index_lines )
			);
		}

		$lines = array_merge(
			$lines,
			$this->render_section( 'Actions', $grouped_actions ),
			[ '' ],
			$this->render_section( 'Filters', $grouped_filters )
		);

		if ( [] !== $deprecated_actions ) {
			$lines = array_merge(
				$lines,
				[ '' ],
				$this->render_section( 'Deprecated Actions', $deprecated_actions )
			);
		}
		if ( [] !== $deprecated_filters ) {
			$lines = array_merge(
				$lines,
				[ '' ],
				$this->render_section( 'Deprecated Filters', $deprecated_filters )
			);
		}

		return rtrim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * Renders a details wrapper for a section of the document that contains an index.
	 *
	 * @param string   $title         The title of the section.
	 * @param string   $anchor        The anchor of the section.
	 * @param string[] $content_lines The lines of the section.
	 * @return string[] The markdown lines of the details section.
	 */
	private function render_details_index_section( string $title, string $anchor, array $content_lines ): array {
		return [
			'<details>',
			'<summary><strong>' . $title . ' <a href="' . $anchor . '">#</a></strong></summary>',
			'',
			...$content_lines,
			'',
			'</details>',
			'',
		];
	}

	/**
	 * Renders an index entry for a given name.
	 *
	 * @param string $name The name to render.
	 * @return string The markdown line of the index entry.
	 */
	private function render_index_entry( string $name ): string {
		$markdown_name   = $this->escape_markdown_inline( $name );
		$markdown_anchor = strtr(
			strtolower( $markdown_name ),
			[
				'$' => '',
				'{' => '',
				'}' => '',
			]
		);
		return sprintf( '   - [%s](#%s)', $markdown_name, $markdown_anchor );
	}

	/**
	 * Renders a section of the document for a given title and hooks.
	 *
	 * @param string $title The title of the section.
	 * @param array<string,array<int,array<string,mixed>>> $grouped_hooks The grouped hooks to render.
	 * @return string[] The markdown lines of the section, including the title and empty lines.
	 */
	private function render_section( string $title, array $grouped_hooks ): array {
		$lines = [
			"## {$title}",
			'',
		];

		foreach ( $grouped_hooks as $name => $entries ) {
			$canonical = $this->select_canonical_hook( $entries );
			$doc       = $canonical['doc'];

			$lines[] = '### `' . $this->escape_markdown_inline( (string) $name ) . '`';
			$lines[] = '';

			$deprecated_hook = $this->get_deprecated_hook_from_group( $entries );
			if ( null !== $deprecated_hook ) {
				if ( empty( $deprecated_hook['deprecated_version'] ) ) {
					$lines[] = '**Deprecated**';
				} else {
					$lines[] = '**Deprecated:** Since ' . $this->escape_markdown_inline( (string) $deprecated_hook['deprecated_version'] );
				}
				$lines[] = '';
				if ( isset( $deprecated_hook['deprecated_replacement'] ) ) {
					$replacement = $this->escape_markdown_inline( (string) $deprecated_hook['deprecated_replacement'] );
					if ( 1 === preg_match( '/^[a-z]+(_[a-z]+)+$/', $replacement ) ) {
						$replacement = $replacement . ' - [documentation](#' . $replacement . ')';
					}
					$lines[] = '**Replacement:** ' . $replacement;
					$lines[] = '';
				}
				if ( isset( $deprecated_hook['deprecated_message'] ) ) {
					$lines[] = '_' . $this->escape_markdown_inline( (string) $deprecated_hook['deprecated_message'] ) . '_';
				}
				$lines[] = '';
			}

			$description = $this->normalize_text( (string) $doc['description'] );
			if ( '' !== $description ) {
				$lines[] = $description;
				$lines[] = '';
			}

			$long_description = trim( (string) $doc['long_description'] );
			if ( '' !== $long_description && ! $this->is_documented_reference( $long_description ) ) {
				$lines[] = $long_description;
				$lines[] = '';
			}

			$since_tags = $this->tags_by_name( $doc['tags'], 'since' );
			if ( ! empty( $since_tags ) ) {
				$lines[] = '**Since:** ' . implode( ', ', array_unique( array_map( [ $this, 'tag_content' ], $since_tags ) ) );
				$lines[] = '';
			}

			if ( isset( $canonical['aliases'] ) && is_array( $canonical['aliases'] ) && ! empty( $canonical['aliases'] ) ) {
				$aliases = array_map(
					fn ( $alias ): string => '`' . $this->escape_markdown_inline( (string) $alias ) . '`',
					$canonical['aliases']
				);
				$lines[] = '**Aliases:** ' . implode( ', ', $aliases );
				$lines[] = '';
			}

			$param_tags = $this->tags_by_name( $doc['tags'], 'param' );
			if ( ! empty( $param_tags ) ) {
				$lines[] = '**Parameters**';
				$lines[] = '';
				$lines[] = '| Name | Type | Description |';
				$lines[] = '| --- | --- | --- |';
				foreach ( $param_tags as $tag ) {
					$lines[] = sprintf(
						'| `%s` | `%s` | %s |',
						$this->escape_table_cell(
							$this->escape_markdown_inline( ltrim( (string) ( $tag['variable'] ?? '' ), '$' ) )
						),
						$this->escape_table_cell(
							$this->escape_markdown_inline( implode( '|', array_map( [ $this, 'normalize_type' ], $tag['types'] ?? [] ) ) )
						),
						$this->escape_table_cell(
							$this->normalize_text( (string) ( $tag['content'] ?? '' ) )
						)
					);
				}
				$lines[] = '';
			}

			$lines[] = '**Source locations**';
			$lines[] = '';
			$lines[] = '| File | Arguments |';
			$lines[] = '| --- | --- |';
			foreach ( $entries as $entry ) {
				$file    = (string) $entry['file'];
				$lines[] = sprintf(
					'| [%s](%s) | %d |',
					$this->escape_table_cell( 'includes/' . $file ),
					$this->get_github_url( $file ),
					(int) $entry['args'],
				);
			}
			$lines[] = '';
			$lines[] = '---';
			$lines[] = '';
		}

		return $lines;
	}

	/**
	 * Groups hooks by name, as a hook may be triggered in multiple locations.
	 *
	 * @param array<int,array<string,mixed>> $hooks All hooks.
	 * @return array<string,array<int,array<string,mixed>>> The hooks grouped by name, sorted alphabetically.
	 */
	private function group_hooks_by_name( array $hooks ): array {
		$groups = [];
		foreach ( $hooks as $hook ) {
			$name = (string) $hook['name'];
			if ( ! isset( $groups[ $name ] ) ) {
				$groups[ $name ] = [];
			}
			$groups[ $name ][] = $hook;
		}

		ksort( $groups, SORT_NATURAL | SORT_FLAG_CASE );

		return $groups;
	}

	/**
	 * Extracts deprecated hooks from a set of grouped hooks.
	 *
	 * @param string                                       $hook_type      The type of hook to extract deprecated hooks from. Either 'action' or 'filter'.
	 * @param array<string,array<int,array<string,mixed>>> &$grouped_hooks The grouped hooks to extract deprecated hooks from.
	 * @return array<string,array<int,array<string,mixed>>> The deprecated hook groups.
	 */
	private function extract_deprecated_hooks( string $hook_type, array &$grouped_hooks ): array {
		$deprecated_hooks = [];
		foreach ( $grouped_hooks as $name => $entries ) {
			if ( null !== $this->get_deprecated_hook_from_group( $entries ) ) {
				$deprecated_hooks[ $name ] = $entries;
				unset( $grouped_hooks[ $name ] );
			}
		}
		return $deprecated_hooks;
	}

	/**
	 * Gets the first deprecated hook from a group of hooks. Returns null if no deprecated hook is found.
	 *
	 * @param array<int,array<string,mixed>> $group The group of hooks to get the deprecated hook from.
	 * @return array<string,mixed>|null The deprecated hook, or null if no deprecated hook is found.
	 */
	private function get_deprecated_hook_from_group( array $group ): ?array {
		foreach ( $group as $entry ) {
			if ( isset( $entry['type'] ) && str_ends_with( $entry['type'], '_deprecated' ) ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Selects the canonical hook from a set of entries by skipping entries that
	 * are documentation references, i.e. "This action/filter is documented in...".
	 *
	 * @param array<int,array<string,mixed>> $entries The hook entries for the hook we're operating on.
	 * @return array<string,mixed> The canonical hook.
	 */
	private function select_canonical_hook( array $entries ): array {
		foreach ( $entries as $entry ) {
			$description = (string) ( $entry['doc']['description'] ?? '' );
			if ( ! $this->is_documented_reference( $description ) ) {
				return $entry;
			}
		}

		return reset( $entries );
	}

	/**
	 * Get the set of tags matching a given name.
	 *
	 * @param array<int,array<string,mixed>> $tags The tags to filter.
	 * @param string                         $name The name of the tags to return.
	 * @return array<int,array<string,mixed>> The tags matching the given name.
	 */
	private function tags_by_name( array $tags, string $name ): array {
		return array_values(
			array_filter(
				$tags,
				static fn ( array $tag ): bool => ( $tag['name'] ?? null ) === $name
			)
		);
	}

	/**
	 * Returns the content of a tag.
	 *
	 * @param array<string,mixed> $tag The tag data.
	 * @return string The content of the tag.
	 */
	private function tag_content( array $tag ): string {
		return $this->normalize_text( (string) ( $tag['content'] ?? '' ) );
	}

	/**
	 * Normalizes a text string by removing HTML tags and entities.
	 *
	 * @param string $text The text string to normalize.
	 * @return string The normalized text string.
	 */
	private function normalize_text( string $text ): string {
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text ) ?? $text;

		return trim( $text );
	}

	/**
	 * Normalizes a type string by removing leading slashes for namespaces.
	 *
	 * @param string $type The type string to normalize.
	 * @return string The normalized type string.
	 */
	private function normalize_type( string $type ): string {
		return ltrim( $type, '\\' );
	}

	/**
	 * Checks if a description is simply a reference to an existing/other documentation location.
	 *
	 * @param string $text The text to check.
	 * @return bool True if the text is a docuemntation reference, false otherwise.
	 */
	private function is_documented_reference( string $text ): bool {
		return 1 === preg_match( '/^This (action|filter) is documented in /', trim( $text ) );
	}

	/**
	 * Returns the GitHub URL for a file.
	 *
	 * @param string $file The file path.
	 * @return string The GitHub URL.
	 */
	private function get_github_url( string $file ): string {
		$path = 'includes/' . ltrim( $file, '/' );
		$path = implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );

		return "{$this->github_base}/{$this->git_ref}/{$path}";
	}

	/**
	 * Escapes a string for Markdown output.
	 *
	 * @param string $text The text to escape.
	 * @return string The escaped text.
	 */
	private function escape_markdown_inline( string $text ): string {
		return str_replace( '`', '\\`', $text );
	}

	/**
	 * Escapes a table cell for Markdown.
	 *
	 * @param string $text The text to escape.
	 * @return string The escaped text.
	 */
	private function escape_table_cell( string $text ): string {
		return str_replace( '|', '\\|', $text );
	}
}

$options = getopt(
	'',
	[
		'actions::',
		'filters::',
		'github-base::',
		'output::',
		'schema::',
		'ref::',
		'commit::',
	]
);

try {
	Hook_Markdown_Generator::from_options( false === $options ? [] : $options )->run();
} catch ( Throwable $e ) {
	fwrite( STDERR, $e->getMessage() . "\n" );
	exit( 1 );
}
