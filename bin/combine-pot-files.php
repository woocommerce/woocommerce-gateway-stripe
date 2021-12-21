<?php
// phpcs:ignoreFile - This is an auxiliary build tool, and not part of the plugin.

/**
 * Command line script for merging two .pot files.
 */

/**
 * Get the two file names from the command line.
 */
if ( $argc < 2 ) {
	echo "Usage: php -f {$argv[0]} source-file.pot destination-file.pot\n";
	exit;
}

for ( $index = 1; $index <= 2; $index++ ) {
	if ( ! is_file( $argv[ $index ] ) ) {
		echo "[ERROR] File not found: {$argv[ $index ]}\n";
		exit;
	}
}

// Merge all translation messages into the second file.
$target_file = $argv[2];

/**
 * Parses a .pot file into an array.
 *
 * @param string $file_name Pot file name.
 * @return array Translation messages
 */
function read_pot_translations( string $file_name ): array {
	$fh         = fopen( $file_name, 'r' );
	$originals  = [];
	$references = [];
	$messages   = [];
	$have_msgid = false;

	while ( ! feof( $fh ) ) {
		$line = trim( fgets( $fh ) );
		if ( ! $line ) {
			$message               = implode( "\n", $messages );
			$originals[ $message ] = $references;
			$references            = [];
			$messages              = [];
			$have_msgid            = false;
			continue;
		}

		if ( 'msgid' == substr( $line, 0, 5 ) ) {
			$have_msgid = true;
		}

		if ( $have_msgid ) {
			$messages[] = $line;
		} else {
			$references[] = $line;
		}
	}

	fclose( $fh );

	$message               = implode( "\n", $messages );
	$originals[ $message ] = $references;
	return $originals;
}

/**
 * Generates a map with the mapping for 'original source file' -> final transpiled/minified file in the 'dist' folder.
 * Format example:
 * [
 *   'client/card-readers/settings/file-upload.js' => [
 *     'build/index.js',
 *     'build/tos.js',
 *   ]
 * ]
 *
 * @return array Mapping of source js files and the generated files that use them.
 */
function load_js_transpiling_source_maps(): array {
	$mappings = [];
	foreach ( glob( "build/*.js.map", GLOB_NOSORT ) as $filename ) {
		$file_content = file_get_contents( $filename );
		if ( $file_content === false ) {
			echo "[WARN] Unable to read file '". $filename . "'. Some translation strings might not have the correct references as a result.\n";
			continue;
		}
		$file_json = json_decode( $file_content, true );
		if ( $file_json === null ) {
			echo "[WARN] Unable to parse JSON file: '". $filename . "'. Some translation strings might not have the correct references as a result.\n";
			continue;
		}

		foreach ( $file_json[ 'sources' ] as $source ) {
			$source = preg_replace( '%^webpack:///\./(client/.*)$%', '${1}', $source );
			if ( 'webpack' !== substr( $source, 0, 7 ) ) {
				$mappings[ $source ][] = $file_json[ 'file' ];
			}
		}
	}

	if ( empty( $mappings ) ) {
		echo "[ERROR] Unable to load JS transpiling mappings from 'build/*.js.map' files. Make sure the JS assets compilation was successful.\n";
		die();
	}

	return $mappings;
}

/**
 * For each file reference to a javascript/typescript file (from the client folder) in the comments, it adds file
 * references to the generated files (from the build folder) that use that particular javascript/typescript as source.
 *
 * @param array $js_mappings Mapping of source js files and the generated files that use them.
 * @param array $translations POT translations (including references/comments).
 * @return array Translation messages
 */
function add_transpiled_filepath_reference_to_comments( array $js_mappings, array $translations ): array {
	foreach ( $translations as $message => $references ) {
		// Check references for js/jsx/ts/tsx files
		$dist_js_to_add = [];
		foreach ( $references as $ref ) {
			if ( preg_match( '%^#: (.+\.(js|jsx|ts|tsx)):\d+$%', $ref, $m ) ) {
				if ( ! array_key_exists( $m[1], $js_mappings ) ) {
					// The file $m[1] is not used in any of the generated client JS files. Skip it.
					continue;
				}

				foreach ( $js_mappings[ $m[1] ] as $mapping ) {
					$dist_js_to_add[] = '#: build/' . $mapping . ':1';
				}
			}
		}

		// Add the new file references to the top of the list.
		if ( ! empty( $dist_js_to_add ) ) {
			array_splice( $translations[ $message], 0, 0, array_unique( $dist_js_to_add ) );
		}
	}

	return $translations;
}

// Read the translation .pot files.
$originals_1 = read_pot_translations( $argv[1] );
$originals_2 = read_pot_translations( $argv[2] );

// For transpiled JS client files, we need to add a reference to the generated build file.
$js_source_maps = load_js_transpiling_source_maps();
$originals_1 = add_transpiled_filepath_reference_to_comments( $js_source_maps, $originals_1 );
$originals_2 = add_transpiled_filepath_reference_to_comments( $js_source_maps, $originals_2 );

// Delete the original sources.
unlink( $argv[1] );
unlink( $argv[2] );

// We don't want two .pot headers in the output.
array_shift( $originals_1 );

$fh = fopen( $target_file, 'w' );
foreach ( $originals_2 as $message => $original ) {
	// Use the complete message section to match strings to be translated.
	if ( isset( $originals_1[ $message ] ) ) {
		$original = array_merge( $original, $originals_1[ $message ] );
		unset( $originals_1[ $message ] );
	}

	fwrite( $fh, implode( "\n", $original ) );
	fwrite( $fh, "\n" . $message . "\n\n" );
}

foreach ( $originals_1 as $message => $original ) {
	fwrite( $fh, implode( "\n", $original ) );
	fwrite( $fh, "\n" . $message . "\n\n" );
}

fclose( $fh );

echo "Created {$target_file}\n";
