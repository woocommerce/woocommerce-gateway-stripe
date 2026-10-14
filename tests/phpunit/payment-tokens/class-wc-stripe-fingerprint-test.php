<?php

/**
 * Trait WC_Stripe_Fingerprint_Trait tests.
 */
class WC_Stripe_Fingerprint_Test extends WP_UnitTestCase {

	/**
	 * An instance of a class using WC_Stripe_Fingerprint_Trait.
	 *
	 * @var WC_Stripe_Payment_Token_CC
	 */
	protected $token;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->token = new WC_Stripe_Payment_Token_CC();
	}

	/**
	 * Test default fingerprint value is empty upon instantiation.
	 */
	public function test_default_fingerprint(): void {
		$this->assertSame( '', $this->token->get_fingerprint() );
	}

	/**
	 * Test setting and getting fingerprint with various contexts.
	 *
	 * @dataProvider provide_fingerprint_contexts
	 *
	 * @param string $fingerprint Fingerprint to test.
	 * @param string $context     Context passed to get_fingerprint ('view' or 'edit').
	 * @return void
	 */
	public function test_get_and_set_fingerprint( string $fingerprint, string $context ): void {
		$this->token->set_fingerprint( $fingerprint );
		$this->assertSame( $fingerprint, $this->token->get_fingerprint( $context ) );
	}

	/**
	 * Data provider for fingerprint contexts.
	 *
	 * @return array
	 */
	public function provide_fingerprint_contexts(): array {
		return [
			'view context with alphanumeric fingerprint' => [ '123abc456def', 'view' ],
			'edit context with alphanumeric fingerprint' => [ '123abc456def', 'edit' ],
			'view context with empty fingerprint'        => [ '', 'view' ],
			'edit context with empty fingerprint'        => [ '', 'edit' ],
			'hashed fingerprint string'                  => [ 'Wb3N1vB6mJ4g', 'view' ],
		];
	}

	/**
	 * Test overwriting an existing fingerprint.
	 */
	public function test_overwrite_fingerprint(): void {
		$this->token->set_fingerprint( 'initial_fingerprint' );
		$this->assertSame( 'initial_fingerprint', $this->token->get_fingerprint() );

		$this->token->set_fingerprint( 'updated_fingerprint' );
		$this->assertSame( 'updated_fingerprint', $this->token->get_fingerprint() );
	}
}
