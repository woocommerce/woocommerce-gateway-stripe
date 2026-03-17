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
		$loaded_classes   = get_declared_classes();

		if ( ! class_exists( $suite_class_name, false ) ) {
			FileLoader::checkAndLoad( $suite_class_file );

			$loaded_classes = array_values(
				array_diff( get_declared_classes(), $loaded_classes )
			);

			if ( empty( $loaded_classes ) ) {
				// No new classes loaded — try the WordPress-style fallback below.
				$loaded_classes = get_declared_classes();
			}
		}

		// Standard PHPUnit lookup: exact class name or underscore/namespace suffix.
		if ( ! class_exists( $suite_class_name, false ) ) {
			$offset = 0 - strlen( $suite_class_name );

			foreach ( $loaded_classes as $loaded_class ) {
				if ( stripos( substr( $loaded_class, $offset - 1 ), '\\' . $suite_class_name ) === 0 ||
					stripos( substr( $loaded_class, $offset - 1 ), '_' . $suite_class_name ) === 0 ) {
					$suite_class_name = $loaded_class;
					break;
				}
			}
		}

		// WordPress-style fallback: find the test class by its declared file path.
		if ( ! class_exists( $suite_class_name, false ) ) {
			$real_path = realpath( $suite_class_file );

			if ( false !== $real_path ) {
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
			}

			throw new Exception(
				sprintf(
					'Class %s could not be found in %s',
					$suite_class_name,
					$suite_class_file
				)
			);
		}

		try {
			$class = new ReflectionClass( $suite_class_name );
		} catch ( ReflectionException $e ) {
			throw new Exception( $e->getMessage(), (int) $e->getCode(), $e );
		}

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

			return $class;
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

		return $class;
	}

	/**
	 * @param ReflectionClass $a_class
	 * @return ReflectionClass
	 */
	public function reload( ReflectionClass $a_class ): ReflectionClass {
		return $a_class;
	}
}
