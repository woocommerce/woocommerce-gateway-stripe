<?php

defined( 'ABSPATH' ) || exit;

/**
 * Listens for the trace_finalized action and writes an order snapshot to
 * the trace's top-level `order_snapshot` field.
 *
 * Hookup:
 *
 *   Trace_Store::set_status() -> do_action(
 *       'wc_stripe_diagnostics_trace_finalized',
 *       $session_id,
 *       $status
 *   );
 *
 * Reads the order id from `trace.meta.order_id`, which the recorder pins
 * via {@see WC_Stripe_Diagnostics_Trace_Store::set_order_id()} the first
 * time it observes one in a request, response, or webhook. If no order
 * was ever observed during the trace (e.g. an abandoned express checkout
 * where no order got created), the listener no-ops.
 *
 * The actual snapshot is deferred to the WordPress `shutdown` hook so
 * that order-side operations performed later in the same request — order
 * notes added by the gateway after the API response, status transitions
 * driven by the failure handler — settle into the database before we
 * read the order. Capturing synchronously inside `trace_finalized` would
 * miss those follow-on writes because the recorder's response hook fires
 * inside `WC_Stripe_API::request()`, several frames before the caller
 * processes the response.
 */
class WC_Stripe_Diagnostics_Order_Snapshotter {

	protected const ACTION           = 'wc_stripe_diagnostics_trace_finalized';
	protected const MAX_LINE_ITEMS   = 20;
	protected const MAX_NOTES        = 20;
	protected const NOTE_BODY_MAX    = 500;
	protected const PRODUCT_NAME_MAX = 80;
	protected const MAX_COUPONS      = 10;

	/**
	 * Session ids that finalized during this request and are pending
	 * a snapshot at shutdown. Keyed by session id (de-duplicates).
	 *
	 * @var array<string, true>
	 */
	private $pending_session_ids = [];

	/**
	 * Trace store the snapshotter reads existing events from and appends
	 * the order_snapshot event to.
	 *
	 * @var WC_Stripe_Diagnostics_Trace_Store
	 */
	private $store;

	/**
	 * Redaction engine applied to the snapshot before storage. Enforces
	 * the order_snapshot allow-list and runs PII scrubbers over string
	 * values inside line_items, coupons, and order_notes.
	 *
	 * @var WC_Stripe_Diagnostics_Redactor
	 */
	private $redactor;

	public function __construct(
		WC_Stripe_Diagnostics_Trace_Store $store,
		WC_Stripe_Diagnostics_Redactor $redactor
	) {
		$this->store    = $store;
		$this->redactor = $redactor;
	}

	/**
	 * Subscribe to the finalized action. Production callers register from
	 * inside Recorder::init() so this only happens when the diagnostics
	 * toggle is on.
	 */
	public function register(): void {
		add_action( self::ACTION, [ $this, 'on_trace_finalized' ], 10, 2 );
	}

	/**
	 * Tests use this to detach the listener between cases.
	 */
	public function unregister(): void {
		remove_action( self::ACTION, [ $this, 'on_trace_finalized' ], 10 );
		remove_action( 'shutdown', [ $this, 'flush_pending_snapshots' ], 100 );
		$this->pending_session_ids = [];
	}

	/**
	 * Action listener for `wc_stripe_diagnostics_trace_finalized`. Queues
	 * the session for end-of-request snapshotting; the actual order read
	 * happens in {@see self::flush_pending_snapshots()} during shutdown.
	 *
	 * @param string $session_id Trace session identifier.
	 * @param string $status     New terminal status (unused, but part of the action contract).
	 */
	public function on_trace_finalized( $session_id, $status ): void {
		if ( empty( $this->pending_session_ids ) ) {
			add_action( 'shutdown', [ $this, 'flush_pending_snapshots' ], 100 );
		}
		$this->pending_session_ids[ (string) $session_id ] = true;
	}

	/**
	 * Drain the queue and snapshot each pending session. Intended for the
	 * WordPress `shutdown` hook; tests drive it via `do_action( 'shutdown' )`
	 * after they call `set_status()`.
	 */
	public function flush_pending_snapshots(): void {
		// Self-remove so a follow-up `set_status` in tests (or in any
		// long-lived process) doesn't pile up duplicate listeners. The
		// queue add in `on_trace_finalized` re-registers as needed.
		remove_action( 'shutdown', [ $this, 'flush_pending_snapshots' ], 100 );

		$session_ids               = array_keys( $this->pending_session_ids );
		$this->pending_session_ids = [];
		foreach ( $session_ids as $session_id ) {
			$this->take_snapshot( (string) $session_id );
		}
	}

	/**
	 * Build and persist the snapshot for a single session. Idempotent:
	 * if the trace already has an `order_snapshot`, skip — support reads
	 * the order's state at the moment the trace first ended, not at every
	 * later mutation.
	 *
	 * @param string $session_id Trace session identifier.
	 */
	private function take_snapshot( string $session_id ): void {
		$trace = $this->store->get( $session_id );
		if ( null === $trace ) {
			return;
		}

		if ( ! empty( $trace['order_snapshot'] ) ) {
			return;
		}

		$order_id = isset( $trace['meta']['order_id'] ) ? (int) $trace['meta']['order_id'] : 0;
		if ( $order_id <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$snapshot = $this->build_snapshot( $order );
		$this->store->set_order_snapshot(
			$session_id,
			$this->redactor->redact( $snapshot )
		);
	}

	/**
	 * Build the snapshot event payload from a WooCommerce order. The
	 * snapshotter is responsible for shaping nested arrays — the redactor
	 * allow-list only operates on top-level paths.
	 *
	 * @param WC_Order $order The order to snapshot.
	 * @return array
	 */
	private function build_snapshot( WC_Order $order ): array {
		$all_items  = $order->get_items();
		$line_items = [];
		foreach ( $all_items as $item ) {
			if ( count( $line_items ) >= self::MAX_LINE_ITEMS ) {
				break;
			}
			$product      = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;
			$total        = $item instanceof WC_Order_Item_Product ? (string) $item->get_total() : '0';
			$line_items[] = [
				'name'         => substr( (string) $item->get_name(), 0, self::PRODUCT_NAME_MAX ),
				'qty'          => (int) $item->get_quantity(),
				'total'        => $total,
				'product_type' => $product instanceof WC_Product ? (string) $product->get_type() : '',
			];
		}

		$coupons = array_slice( $order->get_coupon_codes(), 0, self::MAX_COUPONS );

		$has_subscriptions = function_exists( 'wcs_order_contains_subscription' )
			? (bool) wcs_order_contains_subscription( $order )
			: false;

		$has_pre_orders = class_exists( 'WC_Pre_Orders_Order' )
			? (bool) WC_Pre_Orders_Order::order_contains_pre_order( $order )
			: false;

		$created_at     = $order->get_date_created();
		$date_paid      = $order->get_date_paid();
		$date_completed = $order->get_date_completed();

		return [
			'kind'                 => 'order_snapshot',
			'order_id'             => (int) $order->get_id(),
			'status'               => (string) $order->get_status(),
			'currency'             => (string) $order->get_currency(),
			'total'                => (string) $order->get_total(),
			'subtotal'             => (string) $order->get_subtotal(),
			'tax_total'            => (string) $order->get_total_tax(),
			'shipping_total'       => (string) $order->get_shipping_total(),
			'discount_total'       => (string) $order->get_total_discount(),
			'payment_method'       => (string) $order->get_payment_method(),
			'payment_method_title' => (string) $order->get_payment_method_title(),
			'transaction_id'       => (string) $order->get_transaction_id(),
			'customer_id'          => (int) $order->get_customer_id(),
			'created_at'           => $created_at ? (int) $created_at->getTimestamp() : null,
			'date_paid'            => $date_paid ? (int) $date_paid->getTimestamp() : null,
			'date_completed'       => $date_completed ? (int) $date_completed->getTimestamp() : null,
			'order_key'            => (string) $order->get_order_key(),
			'billing_country'      => (string) $order->get_billing_country(),
			'shipping_country'     => (string) $order->get_shipping_country(),
			'site_locale'          => (string) get_locale(),
			'item_count'           => count( $all_items ),
			'line_items'           => $line_items,
			'coupons'              => array_values( $coupons ),
			'has_subscriptions'    => $has_subscriptions,
			'has_pre_orders'       => $has_pre_orders,
			'order_notes'          => $this->build_notes( $order ),
		];
	}

	/**
	 * Pull the most recent admin (non-customer) notes for the order.
	 *
	 * @param WC_Order $order The order whose notes to retrieve.
	 * @return array
	 */
	private function build_notes( WC_Order $order ): array {
		try {
			$raw_notes = wc_get_order_notes(
				[
					'order_id' => $order->get_id(),
					'order_by' => 'date_created',
					'order'    => 'DESC',
				]
			);
		} catch ( \Throwable $e ) {
			return [];
		}

		$notes = [];
		foreach ( $raw_notes as $note ) {
			if ( ! empty( $note->customer_note ) ) {
				continue;
			}
			if ( count( $notes ) >= self::MAX_NOTES ) {
				break;
			}
			$content = (string) ( $note->content ?? '' );
			$notes[] = [
				'date'     => $note->date_created instanceof WC_DateTime ? (int) $note->date_created->getTimestamp() : null,
				'content'  => substr( $content, 0, self::NOTE_BODY_MAX ),
				'added_by' => (string) ( $note->added_by ?? '' ),
			];
		}
		return $notes;
	}
}
