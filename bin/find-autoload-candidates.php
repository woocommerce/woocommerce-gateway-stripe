<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

/**
 * Surface autoloader-rollout candidates from the dependency map.
 *
 * Pipeline:
 *   1. Shell out to bin/build-class-dependency-map.php with --exclude-autoloaded
 *      --format=json to get the graph of classes still outside the classmap,
 *      with edges to already-autoloaded classes stripped.
 *   2. Step 1 — list "unblocked" classes: every strict field is empty, meaning
 *      the class has no strict deps on non-autoloaded classes and can be added
 *      to the classmap in isolation.
 *   3. If step 1 is empty (and --deep is not set), prompt the user. If they
 *      decline, exit. If they accept (or --deep is set), run the deep pass.
 *   4. Deep pass — compute each class's transitive strict closure. A closure is
 *      a set closed under strict-deps, i.e. a valid rollout group. Emit unique
 *      closures up to --max-group-size, smallest first.
 *
 * Loose references are intentionally ignored — they don't constrain autoload
 * ordering, only strict declarations (extends/implements/use/param/return/property)
 * do.
 */

declare( strict_types=1 );

// ---------------------------------------------------------------------------
// CLI parsing
// ---------------------------------------------------------------------------

$cli_options = getopt( '', [ 'path::', 'max-group-size::', 'deep', 'types::', 'help' ] );

if ( isset( $cli_options['help'] ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite(
		STDERR,
		"Usage: php bin/find-autoload-candidates.php [--path=<dir>] [--max-group-size=<n>] [--deep] [--types=<csv>]\n"
		. "  --path=<dir>            Directory to scan (default: includes). Passed through to\n"
		. "                          build-class-dependency-map.php.\n"
		. "  --max-group-size=<n>    Cap on closure size for the deep pass (default: 10).\n"
		. "  --deep                  Always run the deep pass without prompting.\n"
		. "  --types=<csv>           Which dependency tiers count as blockers (default: all).\n"
		. "                          Accepts: all, compile-time, runtime, doc. Combine tiers\n"
		. "                          via commas, e.g. --types=compile-time,runtime. 'all' is\n"
		. "                          mutually exclusive with the others.\n"
	);
	exit( 0 );
}

$directory      = $cli_options['path'] ?? 'includes';
$max_group_size = isset( $cli_options['max-group-size'] ) ? (int) $cli_options['max-group-size'] : 10;
$deep           = isset( $cli_options['deep'] );

$types_raw = strtolower( (string) ( $cli_options['types'] ?? 'all' ) );
$tokens    = array_values( array_filter( array_map( 'trim', explode( ',', $types_raw ) ) ) );
$valid     = [ 'all', 'compile-time', 'runtime', 'doc' ];
foreach ( $tokens as $tok ) {
	if ( ! in_array( $tok, $valid, true ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( STDERR, "Unknown --types value: {$tok} (expected one or more of: all, compile-time, runtime, doc)\n" );
		exit( 2 );
	}
}
if ( in_array( 'all', $tokens, true ) && count( $tokens ) > 1 ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "--types=all cannot be combined with other values\n" );
	exit( 2 );
}

$blockers = ( empty( $tokens ) || in_array( 'all', $tokens, true ) )
	? [
		'compile_time' => true,
		'runtime'      => true,
		'doc_only'     => true,
	]
	: [
		'compile_time' => in_array( 'compile-time', $tokens, true ),
		'runtime'      => in_array( 'runtime', $tokens, true ),
		'doc_only'     => in_array( 'doc', $tokens, true ),
	];

$tier_label_map = [
	'compile_time' => 'compile-time',
	'runtime'      => 'runtime',
	'doc_only'     => 'doc-only',
];
$active_labels  = [];
foreach ( $blockers as $key => $on ) {
	if ( $on ) {
		$active_labels[] = $tier_label_map[ $key ];
	}
}
$active_label = implode( ' + ', $active_labels );

if ( $max_group_size < 1 ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "--max-group-size must be >= 1\n" );
	exit( 2 );
}

// ---------------------------------------------------------------------------
// Step 0: run build-class-dependency-map.php and capture JSON.
// ---------------------------------------------------------------------------

$child_script = __DIR__ . '/build-class-dependency-map.php';
if ( ! is_file( $child_script ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "Missing dependency map script: {$child_script}\n" );
	exit( 1 );
}

$cmd = sprintf(
	'%s %s --exclude-autoloaded --format=json --path=%s',
	escapeshellarg( PHP_BINARY ),
	escapeshellarg( $child_script ),
	escapeshellarg( $directory )
);

$descriptors = [
	0 => [ 'pipe', 'r' ],
	1 => [ 'pipe', 'w' ],
	2 => STDERR,
];

// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open
$process = proc_open( $cmd, $descriptors, $pipes );
if ( ! is_resource( $process ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "Failed to spawn dependency map process\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
fclose( $pipes[0] );
$json_raw = stream_get_contents( $pipes[1] );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
fclose( $pipes[1] );
$exit_code = proc_close( $process );

if ( 0 !== $exit_code ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "build-class-dependency-map.php exited with code {$exit_code}\n" );
	exit( 1 );
}

$graph = json_decode( (string) $json_raw, true );
if ( ! is_array( $graph ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "Could not parse JSON output from build-class-dependency-map.php\n" );
	exit( 1 );
}

if ( empty( $graph ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo "All classes in {$directory} are already autoloaded.\n";
	exit( 0 );
}

// ---------------------------------------------------------------------------
// Step 1: collect unblocked classes (no remaining deps in the selected tiers
// after the --exclude-autoloaded filter).
// ---------------------------------------------------------------------------

$is_unblocked = static function ( array $entry ) use ( $blockers ): bool {
	if ( $blockers['compile_time'] && ! empty( $entry['compile_time'] ?? [] ) ) {
		return false;
	}
	if ( $blockers['runtime'] && ! empty( $entry['runtime'] ?? [] ) ) {
		return false;
	}
	if ( $blockers['doc_only'] && ! empty( $entry['doc_only'] ?? [] ) ) {
		return false;
	}
	return true;
};

$unblocked = [];
foreach ( $graph as $owner => $entry ) {
	if ( $is_unblocked( $entry ) ) {
		$unblocked[] = $owner;
	}
}
sort( $unblocked );

if ( ! $deep ) {
	if ( ! empty( $unblocked ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "Unblocked classes (no {$active_label} deps on non-autoloaded classes):\n\n";
		foreach ( $unblocked as $name ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "    {$name}\n";
		}
		exit( 0 );
	}

	// ---------------------------------------------------------------------------
	// Step 2: prompt for deep analysis.
	// ---------------------------------------------------------------------------

	$is_tty = function_exists( 'stream_isatty' ) ? stream_isatty( STDIN ) : false;
	if ( ! $is_tty ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "No unblocked classes found. Re-run with --deep to enumerate small interrelated groups.\n";
		exit( 0 );
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo 'No unblocked classes found. Run deeper analysis to find small interrelated groups? [y/N]: ';
	$answer = fgets( STDIN );
	if ( false === $answer ) {
		exit( 0 );
	}
	$answer = strtolower( trim( $answer ) );
	if ( 'y' !== $answer && 'yes' !== $answer ) {
		exit( 0 );
	}
}

// ---------------------------------------------------------------------------
// Step 3: deep pass — transitive strict closures.
// ---------------------------------------------------------------------------

$adjacency = [];
foreach ( $graph as $owner => $entry ) {
	$deps = [];
	if ( $blockers['compile_time'] ) {
		foreach ( $entry['compile_time'] ?? [] as $dep ) {
			$deps[ $dep ] = true;
		}
	}
	if ( $blockers['runtime'] ) {
		foreach ( $entry['runtime'] ?? [] as $dep ) {
			$deps[ $dep ] = true;
		}
	}
	if ( $blockers['doc_only'] ) {
		foreach ( $entry['doc_only'] ?? [] as $dep ) {
			$deps[ $dep ] = true;
		}
	}
	$adjacency[ $owner ] = array_keys( $deps );
}

$closure_of = static function ( string $start ) use ( $adjacency ): array {
	$seen  = [];
	$stack = [ $start ];
	while ( ! empty( $stack ) ) {
		$node = array_pop( $stack );
		if ( isset( $seen[ $node ] ) ) {
			continue;
		}
		$seen[ $node ] = true;
		foreach ( $adjacency[ $node ] ?? [] as $dep ) {
			if ( ! isset( $seen[ $dep ] ) ) {
				$stack[] = $dep;
			}
		}
	}
	$members = array_keys( $seen );
	sort( $members );
	return $members;
};

$groups = [];
foreach ( array_keys( $graph ) as $owner ) {
	$members = $closure_of( $owner );
	if ( count( $members ) > $max_group_size ) {
		continue;
	}
	$key = implode( "\0", $members );
	if ( ! isset( $groups[ $key ] ) ) {
		$groups[ $key ] = $members;
	}
}

$groups = array_values( $groups );
usort(
	$groups,
	static function ( array $a, array $b ): int {
		$size_diff = count( $a ) - count( $b );
		if ( 0 !== $size_diff ) {
			return $size_diff;
		}
		return strcmp( $a[0], $b[0] );
	}
);

if ( empty( $groups ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo "No candidate groups within size <= {$max_group_size}. Try a larger --max-group-size.\n";
	exit( 0 );
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo "Candidate groups (each is closed under {$active_label} deps; size <= {$max_group_size}):\n\n";
$index = 1;
foreach ( $groups as $members ) {
	$size = count( $members );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo "[{$index}] (size {$size})\n";
	foreach ( $members as $name ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "    {$name}\n";
	}
	echo "\n";
	++$index;
}
