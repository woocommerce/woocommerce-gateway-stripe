<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

/**
 * AST-based class dependency map for includes/.
 *
 * Walks every PHP file under --path (default: includes/) and emits a map of
 * each internal class/interface/trait's dependencies on other internal
 * classes. Internal-only by design — external symbols (WP/WC/SPL/
 * Automattic\WooCommerce\*) are filtered out.
 *
 * Two edge buckets:
 *   - strict: explicit declared relationships (extends, implements, use trait,
 *     and native param/return/property type declarations).
 *   - loose: named runtime references (new X, X::y, instanceof X, catch X,
 *     and docblock types in @var/@param/@return/@throws/@property).
 */

declare( strict_types=1 );

require __DIR__ . '/../vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

// ---------------------------------------------------------------------------
// CLI parsing
// ---------------------------------------------------------------------------

$cli_options = getopt( '', [ 'path::', 'format::', 'output::', 'include-external', 'exclude-autoloaded', 'help' ] );

if ( isset( $cli_options['help'] ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite(
		STDERR,
		"Usage: php bin/build-class-dependency-map.php [--path=<dir>] [--format=json|mermaid] [--output=<file>] [--exclude-autoloaded]\n"
		. "  --path=<dir>           Directory to scan (default: includes)\n"
		. "  --format=json|mermaid  Output format (default: json)\n"
		. "  --output=<file>        Write to file instead of stdout\n"
		. "  --exclude-autoloaded   Drop classes covered by composer.json's autoload.classmap,\n"
		. "                         and strip references to them from remaining classes' edges.\n"
	);
	exit( 0 );
}

$directory          = $cli_options['path'] ?? 'includes';
$format             = $cli_options['format'] ?? 'json';
$output             = $cli_options['output'] ?? null;
$exclude_autoloaded = isset( $cli_options['exclude-autoloaded'] );

if ( ! in_array( $format, [ 'json', 'mermaid' ], true ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "Unknown --format: {$format} (expected json or mermaid)\n" );
	exit( 2 );
}

$root = realpath( $directory );
if ( false === $root || ! is_dir( $root ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "Not a directory: {$directory}\n" );
	exit( 2 );
}

// ---------------------------------------------------------------------------
// File discovery
// ---------------------------------------------------------------------------

$files    = [];
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( $file->isFile() && $file->getExtension() === 'php' ) {
		$files[] = $file->getPathname();
	}
}
sort( $files );

// ---------------------------------------------------------------------------
// Composer autoload membership.
//
// We resolve classes against composer.json's `autoload.classmap` directly
// rather than vendor/composer/autoload_classmap.php so the result is
// deterministic (production-only, no autoload-dev) and works without a
// `composer install`.
// ---------------------------------------------------------------------------

$project_root   = realpath( __DIR__ . '/..' );
$composer_path  = $project_root . '/composer.json';
$autoload_dirs  = [];
$autoload_files = [];

if ( is_file( $composer_path ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$composer_json = json_decode( file_get_contents( $composer_path ), true );
	$entries       = $composer_json['autoload']['classmap'] ?? [];
	foreach ( $entries as $entry ) {
		$abs = realpath( $project_root . '/' . $entry );
		if ( false === $abs ) {
			continue;
		}
		if ( is_dir( $abs ) ) {
			$autoload_dirs[] = $abs;
		} else {
			$autoload_files[ $abs ] = true;
		}
	}
} else {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite( STDERR, "Warning: composer.json not found at {$composer_path}; autoload status will be false for all classes.\n" );
}

$is_autoloaded_file = static function ( string $file ) use ( $autoload_dirs, $autoload_files ): bool {
	if ( isset( $autoload_files[ $file ] ) ) {
		return true;
	}
	foreach ( $autoload_dirs as $dir ) {
		if ( strpos( $file, $dir . DIRECTORY_SEPARATOR ) === 0 ) {
			return true;
		}
	}
	return false;
};

// ---------------------------------------------------------------------------
// Parser + helpers
// ---------------------------------------------------------------------------

$parser = ( new ParserFactory() )->createForNewestSupportedVersion();

$builtin_types = array_flip(
	[
		'int',
		'integer',
		'string',
		'bool',
		'boolean',
		'float',
		'double',
		'array',
		'iterable',
		'callable',
		'void',
		'mixed',
		'never',
		'object',
		'resource',
		'self',
		'static',
		'parent',
		'null',
		'false',
		'true',
		'numeric',
		'scalar',
	]
);

$is_builtin = static function ( string $name ) use ( $builtin_types ): bool {
	return isset( $builtin_types[ strtolower( ltrim( $name, '\\' ) ) ] );
};

// ---------------------------------------------------------------------------
// Pass 1: index every class/interface/trait declared under $root.
// ---------------------------------------------------------------------------

class ClassIndexVisitor extends NodeVisitorAbstract {
	public array $names = [];

	public function enterNode( Node $node ) {
		if ( $node instanceof Node\Stmt\Class_
			|| $node instanceof Node\Stmt\Interface_
			|| $node instanceof Node\Stmt\Trait_
		) {
			$name = $this->resolve( $node );
			if ( null !== $name ) {
				$this->names[ $name ] = true;
			}
		}
	}

	private function resolve( Node $node ): ?string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( isset( $node->namespacedName ) && null !== $node->namespacedName ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return $node->namespacedName->toString();
		}
		if ( null !== $node->name ) {
			return $node->name->toString();
		}
		return null;
	}
}

$index      = [];
$autoloaded = [];
foreach ( $files as $file ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$code = file_get_contents( $file );
	if ( false === $code ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( STDERR, "Could not read {$file}\n" );
		continue;
	}
	try {
		$stmts = $parser->parse( $code );
	} catch ( \Throwable $e ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( STDERR, "Parse error in {$file}: " . $e->getMessage() . "\n" );
		continue;
	}
	if ( null === $stmts ) {
		continue;
	}
	$traverser = new NodeTraverser();
	$traverser->addVisitor( new NameResolver() );
	$visitor = new ClassIndexVisitor();
	$traverser->addVisitor( $visitor );
	$traverser->traverse( $stmts );
	$file_is_autoloaded = $is_autoloaded_file( $file );
	foreach ( $visitor->names as $name => $_ ) {
		$index[ $name ]      = true;
		$autoloaded[ $name ] = $file_is_autoloaded;
	}
}

// ---------------------------------------------------------------------------
// Pass 2: collect edges per class.
// ---------------------------------------------------------------------------

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class EdgeVisitor extends NodeVisitorAbstract {
	/**
	 * Edges between classes.
	 *
	 * @var array<string, array>
	 */
	public array $edges = [];

	/**
	 * Stack of class names.
	 *
	 * @var list<string>
	 */
	private array $stack = [];

	/**
	 * Callback to determine if a type is a built-in type.
	 *
	 * @var callable
	 */
	private $is_builtin;

	/**
	 * EdgeVisitor constructor.
	 *
	 * @param callable $is_builtin Callback to determine if a type is a built-in type.
	 * @return void
	 */
	public function __construct( callable $is_builtin ) {
		$this->is_builtin = $is_builtin;
	}

	public function enterNode( Node $node ) {
		if ( $this->is_class_like( $node ) ) {
			$owner = $this->resolve_name( $node );
			if ( null === $owner ) {
				return;
			}
			$this->stack[] = $owner;
			$this->ensure( $owner );

			if ( $node instanceof Node\Stmt\Class_ ) {
				if ( null !== $node->extends ) {
					$this->add_strict( 'extends', $this->fq( $node->extends ), true );
				}
				foreach ( $node->implements as $impl ) {
					$this->add_strict( 'implements', $this->fq( $impl ) );
				}
			} elseif ( $node instanceof Node\Stmt\Interface_ ) {
				foreach ( $node->extends as $impl ) {
					$this->add_strict( 'implements', $this->fq( $impl ) );
				}
			}

			$this->collect_docblock( $node->getDocComment() );
			return;
		}

		if ( empty( $this->stack ) ) {
			return;
		}

		if ( $node instanceof Node\Stmt\TraitUse ) {
			foreach ( $node->traits as $t ) {
				$this->add_strict( 'uses_traits', $this->fq( $t ) );
			}
			return;
		}

		if ( $node instanceof Node\Param ) {
			foreach ( $this->unwrap_type( $node->type ) as $name ) {
				$this->add_strict( 'param_types', $name );
			}
			return;
		}

		if ( $node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_ ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			foreach ( $this->unwrap_type( $node->returnType ) as $name ) {
				$this->add_strict( 'return_types', $name );
			}
			$this->collect_docblock( $node->getDocComment() );
			return;
		}

		if ( $node instanceof Node\Stmt\Property ) {
			foreach ( $this->unwrap_type( $node->type ) as $name ) {
				$this->add_strict( 'property_types', $name );
			}
			$this->collect_docblock( $node->getDocComment() );
			return;
		}

		if ( $node instanceof Node\Expr\New_
			|| $node instanceof Node\Expr\StaticCall
			|| $node instanceof Node\Expr\StaticPropertyFetch
			|| $node instanceof Node\Expr\ClassConstFetch
			|| $node instanceof Node\Expr\Instanceof_
		) {
			if ( $node->class instanceof Node\Name ) {
				$this->add_loose( $this->fq( $node->class ) );
			}
			return;
		}

		if ( $node instanceof Node\Stmt\Catch_ ) {
			foreach ( $node->types as $t ) {
				$this->add_loose( $this->fq( $t ) );
			}
		}
	}

	public function leaveNode( Node $node ) {
		if ( $this->is_class_like( $node ) && ! empty( $this->stack ) ) {
			array_pop( $this->stack );
		}
	}

	private function is_class_like( Node $node ): bool {
		return ( $node instanceof Node\Stmt\Class_
				|| $node instanceof Node\Stmt\Interface_
				|| $node instanceof Node\Stmt\Trait_ )
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			&& ( ( isset( $node->namespacedName ) && null !== $node->namespacedName )
				|| null !== $node->name );
	}

	private function resolve_name( Node $node ): ?string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( isset( $node->namespacedName ) && null !== $node->namespacedName ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return $node->namespacedName->toString();
		}
		if ( null !== $node->name ) {
			return $node->name->toString();
		}
		return null;
	}

	private function ensure( string $owner ): void {
		if ( ! isset( $this->edges[ $owner ] ) ) {
			$this->edges[ $owner ] = [
				'strict' => [
					'extends'        => null,
					'implements'     => [],
					'uses_traits'    => [],
					'param_types'    => [],
					'return_types'   => [],
					'property_types' => [],
				],
				'loose'  => [],
			];
		}
	}

	private function add_strict( string $field, ?string $target, bool $single = false ): void {
		$owner = $this->guarded_target( $target );
		if ( null === $owner ) {
			return;
		}
		if ( $single ) {
			$this->edges[ $owner ]['strict'][ $field ] = $target;
		} else {
			$this->edges[ $owner ]['strict'][ $field ][] = $target;
		}
	}

	private function add_loose( ?string $target ): void {
		$owner = $this->guarded_target( $target );
		if ( null === $owner ) {
			return;
		}
		$this->edges[ $owner ]['loose'][] = $target;
	}

	/**
	 * Returns the current owner if the target is acceptable (non-empty,
	 * non-builtin, non-self), else null. Used to short-circuit both add_*
	 * paths with one check.
	 */
	private function guarded_target( ?string $target ): ?string {
		if ( null === $target || '' === $target ) {
			return null;
		}
		if ( ( $this->is_builtin )( $target ) ) {
			return null;
		}
		$owner = end( $this->stack );
		if ( false === $owner || $owner === $target ) {
			return null;
		}
		return $owner;
	}

	private function fq( Node\Name $name ): string {
		$resolved = $name->getAttribute( 'resolvedName' );
		if ( $resolved instanceof Node\Name ) {
			return $resolved->toString();
		}
		return $name->toString();
	}

	/**
	 * Unwrap a type annotation (param/return/property) into the underlying
	 * class-name strings. Strips NullableType / UnionType / IntersectionType.
	 *
	 * @return string[]
	 */
	private function unwrap_type( $type ): array {
		if ( null === $type ) {
			return [];
		}
		if ( $type instanceof Node\NullableType ) {
			return $this->unwrap_type( $type->type );
		}
		if ( $type instanceof Node\UnionType || $type instanceof Node\IntersectionType ) {
			$out = [];
			foreach ( $type->types as $sub ) {
				foreach ( $this->unwrap_type( $sub ) as $name ) {
					$out[] = $name;
				}
			}
			return $out;
		}
		if ( $type instanceof Node\Identifier ) {
			return [ $type->name ];
		}
		if ( $type instanceof Node\Name ) {
			return [ $this->fq( $type ) ];
		}
		return [];
	}

	private function collect_docblock( $doc ): void {
		if ( null === $doc ) {
			return;
		}
		if ( ! preg_match_all(
			'/@(?:var|param|return|throws|property|property-read|property-write)\s+([^\s\*\/][^\*\/\r\n]*)/',
			$doc->getText(),
			$matches
		) ) {
			return;
		}
		foreach ( $matches[1] as $type_expr ) {
			// Stop at the first variable name or description boundary.
			$type_expr = preg_split( '/\s+\$|\s+--?\s/', $type_expr, 2 )[0] ?? '';
			$tokens    = preg_split( '/[\s\|\&<>,\(\)\?\[\]]+/', $type_expr );
			if ( ! is_array( $tokens ) ) {
				continue;
			}
			foreach ( $tokens as $tok ) {
				$tok = ltrim( $tok, '\\' );
				if ( '' === $tok ) {
					continue;
				}
				if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $tok ) ) {
					continue;
				}
				$this->add_loose( $tok );
			}
		}
	}
}

$edge_visitor = new EdgeVisitor( $is_builtin );

foreach ( $files as $file ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$code = file_get_contents( $file );
	if ( false === $code ) {
		continue;
	}
	try {
		$stmts = $parser->parse( $code );
	} catch ( \Throwable $e ) {
		continue;
	}
	if ( null === $stmts ) {
		continue;
	}
	$traverser = new NodeTraverser();
	$traverser->addVisitor( new NameResolver() );
	$traverser->addVisitor( $edge_visitor );
	$traverser->traverse( $stmts );
}

// ---------------------------------------------------------------------------
// Filter (internal-only) + dedupe + sort.
// ---------------------------------------------------------------------------

$keep = static function ( string $target ) use ( $index, $autoloaded, $exclude_autoloaded ): bool {
	if ( ! isset( $index[ $target ] ) ) {
		return false;
	}
	if ( $exclude_autoloaded && ! empty( $autoloaded[ $target ] ) ) {
		return false;
	}
	return true;
};

$result = [];
foreach ( $edge_visitor->edges as $owner => $data ) {
	if ( ! isset( $index[ $owner ] ) ) {
		continue;
	}
	if ( $exclude_autoloaded && ! empty( $autoloaded[ $owner ] ) ) {
		continue;
	}
	$strict = $data['strict'];

	if ( null !== $strict['extends'] && ! $keep( $strict['extends'] ) ) {
		$strict['extends'] = null;
	}
	foreach ( [ 'implements', 'uses_traits', 'param_types', 'return_types', 'property_types' ] as $field ) {
		$strict[ $field ] = array_values( array_unique( array_filter( $strict[ $field ], $keep ) ) );
		sort( $strict[ $field ] );
	}
	$loose = array_values( array_unique( array_filter( $data['loose'], $keep ) ) );
	sort( $loose );

	$result[ $owner ] = [
		'autoloaded' => ! empty( $autoloaded[ $owner ] ),
		'strict'     => $strict,
		'loose'      => $loose,
	];
}

ksort( $result );

// ---------------------------------------------------------------------------
// Emit
// ---------------------------------------------------------------------------

if ( 'json' === $format ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	$payload = json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
} else {
	$payload = render_mermaid( $result );
}

if ( null !== $output ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$bytes = file_put_contents( $output, $payload );
	if ( false === $bytes ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( STDERR, "Failed to write {$output}\n" );
		exit( 1 );
	}
} else {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $payload;
}

/**
 * Render the dependency map as a Mermaid `classDiagram`.
 *
 * @param array $result
 */
function render_mermaid( array $result ): string { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
	$sanitize = static function ( string $name ): string {
		return str_replace( '\\', '__', $name );
	};

	$lines = [ 'classDiagram' ];
	foreach ( $result as $owner => $data ) {
		$own     = $sanitize( $owner );
		$lines[] = "    class {$own}";
		if ( ! empty( $data['autoloaded'] ) ) {
			$lines[] = "    <<autoloaded>> {$own}";
		}
		$strict = $data['strict'];

		if ( null !== $strict['extends'] ) {
			$lines[] = "    {$sanitize( $strict['extends'] )} <|-- {$own}";
		}
		foreach ( $strict['implements'] as $t ) {
			$lines[] = "    {$sanitize( $t )} <|.. {$own}";
		}
		foreach ( $strict['uses_traits'] as $t ) {
			$lines[] = "    {$own} --* {$sanitize( $t )} : uses";
		}

		$declared = array_values(
			array_unique(
				array_merge(
					$strict['param_types'],
					$strict['return_types'],
					$strict['property_types']
				)
			)
		);
		sort( $declared );
		foreach ( $declared as $t ) {
			$lines[] = "    {$own} --> {$sanitize( $t )}";
		}

		foreach ( $data['loose'] as $t ) {
			$lines[] = "    {$own} ..> {$sanitize( $t )}";
		}
	}
	return implode( "\n", $lines ) . "\n";
}
