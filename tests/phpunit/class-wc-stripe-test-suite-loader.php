<?php
/**
 * Custom PHPUnit TestSuiteLoader for WordPress-style file naming.
 *
 * WordPress convention names files as `class-my-class-name.php` while the
 * PHP class inside is `My_Class_Name`. PHPUnit's StandardTestSuiteLoader
 * derives the class name from the filename and fails for these files when
 * passed a file path directly (as paratest does for worker subprocesses).
 *
 * This loader falls back to finding the test class by its declared file path
 * when the standard name-based matching fails.
 *
 * @package WooCommerce_Stripe/Tests
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\StandardTestSuiteLoader;

/**
 * Custom test suite loader that handles WordPress-style file naming.
 */
class WC_Stripe_Test_Suite_Loader extends StandardTestSuiteLoader {

	/**
	 * Load a test suite class from a file, falling back to file-path-based
	 * class discovery when the filename does not match the class name.
	 *
	 * @param string $suite_class_file Path to the test file.
	 * @return ReflectionClass
	 * @throws \PHPUnit\Runner\Exception When no test class can be found.
	 */
	public function load( string $suite_class_file ): ReflectionClass {
		try {
			return parent::load( $suite_class_file );
		} catch ( \PHPUnit\Runner\Exception $original_exception ) {
			// parent::load() already called FileLoader::checkAndLoad(), so the
			// class is declared (or was declared earlier). Find it by file path.
			$real_path = realpath( $suite_class_file );

			if ( false === $real_path ) {
				throw $original_exception;
			}

			foreach ( get_declared_classes() as $class ) {
				try {
					$ref_class = new ReflectionClass( $class );

					if ( realpath( (string) $ref_class->getFileName() ) === $real_path
						&& $ref_class->isSubclassOf( TestCase::class )
						&& ! $ref_class->isAbstract() ) {
						return $ref_class;
					}
				} catch ( ReflectionException $reflection_exception ) {
					continue;
				}
			}

			throw $original_exception;
		}
	}
}
