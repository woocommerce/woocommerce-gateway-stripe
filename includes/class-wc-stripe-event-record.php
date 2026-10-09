<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The stored processing state of a Stripe event.
 *
 * Holds only what the plugin tracks about an event, never the event payload: payloads carry
 * customer data, and the full event can be retrieved from Stripe by ID when it is needed.
 */
final class WC_Stripe_Event_Record {
	/**
	 * Stripe event ID.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * One of the WC_Stripe_Event_Store_Interface::STATUS_* constants.
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Stripe event type.
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * Stripe event creation timestamp.
	 *
	 * @var int
	 */
	public int $created;

	/**
	 * Whether the event belongs to live mode, rather than test mode.
	 *
	 * @var bool
	 */
	public bool $livemode;

	/**
	 * ID of the order the event was applied to, if any.
	 *
	 * @var int|null
	 */
	public ?int $order_id;

	/**
	 * Number of times fetching the event from Stripe failed.
	 *
	 * @var int
	 */
	public int $attempts;

	/**
	 * Constructor.
	 *
	 * @param string   $id       Stripe event ID.
	 * @param string   $status   One of the WC_Stripe_Event_Store_Interface::STATUS_* constants.
	 * @param string   $type     Stripe event type.
	 * @param int      $created  Stripe event creation timestamp.
	 * @param bool     $livemode Whether the event belongs to live mode, rather than test mode.
	 * @param int|null $order_id ID of the order the event was applied to, if any.
	 * @param int      $attempts Number of times fetching the event from Stripe failed.
	 */
	public function __construct( string $id, string $status, string $type, int $created, bool $livemode, ?int $order_id = null, int $attempts = 0 ) {
		$this->id       = $id;
		$this->status   = $status;
		$this->type     = $type;
		$this->created  = $created;
		$this->livemode = $livemode;
		$this->order_id = $order_id;
		$this->attempts = $attempts;
	}

	/**
	 * Creates a record from a Stripe event object.
	 *
	 * @param object $event  Stripe event, as decoded from a webhook or the Events API.
	 * @param string $status One of the WC_Stripe_Event_Store_Interface::STATUS_* constants.
	 * @return self
	 */
	public static function from_stripe_event( object $event, string $status ): self {
		return new self(
			(string) ( $event->id ?? '' ),
			$status,
			(string) ( $event->type ?? '' ),
			(int) ( $event->created ?? time() ),
			! empty( $event->livemode )
		);
	}
}
