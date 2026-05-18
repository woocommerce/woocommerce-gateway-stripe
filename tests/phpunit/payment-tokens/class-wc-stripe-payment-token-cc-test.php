<?php

/**
 * Class WC_Stripe_Payment_Token_CC tests.
 */
class WC_Stripe_Payment_Token_CC_Test extends WP_UnitTestCase {

	/**
	 * Builds a fully populated CC token for display-name assertions.
	 *
	 * @param string $wallet_type Wallet slug to seed (empty for manual entry).
	 * @return WC_Stripe_Payment_Token_CC
	 */
	private function build_token( string $wallet_type = '' ): WC_Stripe_Payment_Token_CC {
		$token = new WC_Stripe_Payment_Token_CC();
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( '03' );
		$token->set_expiry_year( '2027' );
		if ( '' !== $wallet_type ) {
			$token->set_wallet_type( $wallet_type );
		}

		return $token;
	}

	/**
	 * Guards the wallet slug → label map. `link` and unknown wallets must return
	 * an empty string so the saved-methods list (and `get_display_name`) fall
	 * back to bare-card rendering for them — `link` is covered by a separate
	 * dedicated token class.
	 *
	 * @return void
	 */
	public function test_get_wallet_brand_label_maps_only_apple_and_google_pay(): void {
		$this->assertSame( 'Apple Pay', $this->build_token( 'apple_pay' )->get_wallet_brand_label() );
		$this->assertSame( 'Google Pay', $this->build_token( 'google_pay' )->get_wallet_brand_label() );
		$this->assertSame( '', $this->build_token( 'link' )->get_wallet_brand_label() );
		$this->assertSame( '', $this->build_token( '' )->get_wallet_brand_label() );
		$this->assertSame( '', $this->build_token( 'unrecognized' )->get_wallet_brand_label() );
	}

	/**
	 * Manual-entry cards must keep WooCommerce's stock display string so we
	 * never regress non-wallet rendering on classic checkout, blocks, or the
	 * order-pay screen.
	 *
	 * @return void
	 */
	public function test_get_display_name_falls_back_to_parent_for_manual_card(): void {
		$this->assertSame(
			'Visa ending in 4242 (expires 03/27)',
			$this->build_token()->get_display_name()
		);
	}

	/**
	 * Wallet-tokenized cards must wrap the card brand with the wallet label so
	 * the classic checkout radio matches the My Account label exactly, e.g.
	 * "Apple Pay (Visa) ending in 4242 (expires 03/27)".
	 *
	 * @dataProvider provide_wallet_display_name_scenarios
	 *
	 * @param string $wallet_type Stored `card.wallet.type` slug.
	 * @param string $expected    Expected display string.
	 * @return void
	 */
	public function test_get_display_name_wraps_wallet_brand( string $wallet_type, string $expected ): void {
		$this->assertSame( $expected, $this->build_token( $wallet_type )->get_display_name() );
	}

	public function provide_wallet_display_name_scenarios(): array {
		return [
			'apple_pay wraps card brand'  => [ 'apple_pay', 'Apple Pay (Visa) ending in 4242 (expires 03/27)' ],
			'google_pay wraps card brand' => [ 'google_pay', 'Google Pay (Visa) ending in 4242 (expires 03/27)' ],
			'link falls back to parent'   => [ 'link', 'Visa ending in 4242 (expires 03/27)' ],
		];
	}
}
