<?php
/**
 * Custom PHPUnit TestSuiteLoader for WordPress-style file naming.
 *
 * WordPress convention names files as `class-my-class-name.php` while the
 * PHP class inside is `My_Class_Name`. PHPUnit's StandardTestSuiteLoader
 * derives the class name from the filename and fails for these files when
 * passed a file path directly (as paratest does for worker subprocesses).
 *
 * This loader implements TestSuiteLoader directly (StandardTestSuiteLoader is
 * final and cannot be extended) and adds a fallback that finds the test class
 * by scanning declared classes against the file path when the standard
 * name-based matching fails.
 *
 * @package WooCommerce_Stripe/Tests
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Exception;
use PHPUnit\Runner\TestSuiteLoader;
use PHPUnit\Util\FileLoader;

/**
 * Custom test suite loader that handles WordPress-style file naming.
 *
 * Note: TestSuiteLoader is deprecated in PHPUnit 9 and removed in PHPUnit 10.
 * When upgrading to PHPUnit 10+, this loader will need to be replaced with a
 * different approach (e.g. a custom TestRunner extension or filename resolver).
 */
class WC_Stripe_Test_Suite_Loader implements TestSuiteLoader {

	/**
	 * Load a test suite class from a file, falling back to file-path-based
	 * class discovery when the filename does not match the class name.
	 *
	 * @param string $suite_class_file Path to the test file.
	 * @return ReflectionClass
	 * @throws Exception When no test class can be found.
	 */
	public function load( string $suite_class_file ): ReflectionClass {
		$suite_class_name = basename( $suite_class_file, '.php' );
		$loaded_classes   = $this->load_file_if_needed( $suite_class_file, get_declared_classes() );

		$resolved = $this->resolve_by_name( $suite_class_name, $loaded_classes );
		if ( null !== $resolved ) {
			$suite_class_name = $resolved;
		}

		if ( ! class_exists( $suite_class_name, false ) ) {
			$ref_class = $this->resolve_by_file_path( $suite_class_file );

			if ( null === $ref_class ) {
				throw new Exception(
					sprintf(
						'Class %s could not be found in %s',
						$suite_class_name,
						$suite_class_file
					)
				);
			}

			return $ref_class;
		}

		try {
			$class = new ReflectionClass( $suite_class_name );
		} catch ( ReflectionException $e ) {
			throw new Exception( $e->getMessage(), (int) $e->getCode(), $e );
		}

		$this->validate_resolved_class( $class, $suite_class_name, $suite_class_file );

		return $class;
	}

	/**
	 * Load the file if the class it is expected to define is not yet declared,
	 * and return the list of classes that were newly introduced by the load.
	 *
	 * @param string   $suite_class_file          Path to the test file.
	 * @param string[] $previous_declared_classes Classes declared before the load.
	 * @return string[] Newly declared classes, or all declared classes when none are new.
	 */
	private function load_file_if_needed( string $suite_class_file, array $previous_declared_classes ): array {
		$suite_class_name = basename( $suite_class_file, '.php' );

		if ( class_exists( $suite_class_name, false ) ) {
			return $previous_declared_classes;
		}

		FileLoader::checkAndLoad( $suite_class_file );

		$new_classes = array_values(
			array_diff( get_declared_classes(), $previous_declared_classes )
		);

		return empty( $new_classes ) ? get_declared_classes() : $new_classes;
	}

	/**
	 * Resolve the test class name via offset/underscore/namespace suffix matching.
	 *
	 * @param string   $suite_class_name Candidate class name (typically the basename).
	 * @param string[] $loaded_classes   Classes to search against.
	 * @return string|null Resolved fully-qualified class name, or null when not found.
	 */
	private function resolve_by_name( string $suite_class_name, array $loaded_classes ): ?string {
		if ( class_exists( $suite_class_name, false ) ) {
			return null;
		}

		$offset = 0 - strlen( $suite_class_name );

		foreach ( $loaded_classes as $loaded_class ) {
			if ( stripos( substr( $loaded_class, $offset - 1 ), '\\' . $suite_class_name ) === 0 ||
				stripos( substr( $loaded_class, $offset - 1 ), '_' . $suite_class_name ) === 0 ) {
				return $loaded_class;
			}
		}

		return null;
	}

	/**
	 * Find the test class by matching declared-class file paths against the
	 * given file (WordPress-style fallback).
	 *
	 * @param string $suite_class_file Path to the test file.
	 * @return ReflectionClass|null The matching class, or null when not found.
	 */
	private function resolve_by_file_path( string $suite_class_file ): ?ReflectionClass {
		$real_path = realpath( $suite_class_file );

		if ( false === $real_path ) {
			return null;
		}

		foreach ( get_declared_classes() as $class ) {
			try {
				$ref_class = new ReflectionClass( $class );

				if ( realpath( (string) $ref_class->getFileName() ) === $real_path
					&& $ref_class->isSubclassOf( TestCase::class )
					&& ! $ref_class->isAbstract() ) {
					return $ref_class;
				}
			} catch ( ReflectionException $e ) {
				continue;
			}
		}

		return null;
	}

	/**
	 * Validate that the resolved class is a usable test suite, throwing when it
	 * is abstract or when its suite() method is not public and static.
	 *
	 * @param ReflectionClass $class            Resolved class to validate.
	 * @param string          $suite_class_name Class name (for error messages).
	 * @param string          $suite_class_file File path (for error messages).
	 * @return void
	 * @throws Exception When the class or its suite() method fails validation.
	 */
	private function validate_resolved_class( ReflectionClass $class, string $suite_class_name, string $suite_class_file ): void {
		if ( $class->isSubclassOf( TestCase::class ) ) {
			if ( $class->isAbstract() ) {
				throw new Exception(
					sprintf(
						'Class %s declared in %s is abstract',
						$suite_class_name,
						$suite_class_file
					)
				);
			}

			return;
		}

		if ( $class->hasMethod( 'suite' ) ) {
			try {
				$method = $class->getMethod( 'suite' );
			} catch ( ReflectionException $e ) {
				throw new Exception(
					sprintf(
						'Method %s::suite() declared in %s is abstract',
						$suite_class_name,
						$suite_class_file
					)
				);
			}

			if ( ! $method->isPublic() ) {
				throw new Exception(
					sprintf(
						'Method %s::suite() declared in %s is not public',
						$suite_class_name,
						$suite_class_file
					)
				);
			}

			if ( ! $method->isStatic() ) {
				throw new Exception(
					sprintf(
						'Method %s::suite() declared in %s is not static',
						$suite_class_name,
						$suite_class_file
					)
				);
			}
		}
	}

	/**
	 * @param ReflectionClass $a_class
	 * @return ReflectionClass
	 */
	public function reload( ReflectionClass $a_class ): ReflectionClass {
		return $a_class;
	}
}
