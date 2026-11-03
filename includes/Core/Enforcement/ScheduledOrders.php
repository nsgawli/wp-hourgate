<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Enforcement;

use HourGate\Core\OrderingPolicy;
use HourGate\Core\OrderMeta;
use HourGate\Core\Ordering\Situation;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closed mode 2 (`schedule`): a checkout while closed or paused is accepted,
 * and the order records when it can be fulfilled (OW-031). The decision's
 * scheduled time is the next opening, or for a pause its end (moved to the
 * next opening when hours are enforced). With no next opening, `Checkout`
 * rejects the order instead (decision 4).
 *
 * - Classic checkout: the meta is set on `woocommerce_checkout_create_order`
 *   (before WooCommerce saves the order) and the note added on
 *   `woocommerce_checkout_order_created` (once it has an ID).
 * - Block checkout, direct Store API, express: the meta on
 *   `woocommerce_store_api_checkout_update_order_meta`, the note on
 *   `woocommerce_store_api_checkout_order_processed` (at Place order), only
 *   when the meta hook ran for that order in the same request: the processed
 *   hook also fires for order-pay, the meta hook doesn't. Before WooCommerce 11 the meta hook also
 *   fires on the checkout page's GET and PUT requests for the draft order,
 *   which aren't always saved, so it can't add the note (OW-050: WooCommerce
 *   10.0 got the note twice, or a stale one when the store opened in between).
 *
 * Order-pay, staff, REST `wc/v3` and programmatic orders never reach these
 * hooks (D1, D3). A checkout retried after a failed payment reuses its order:
 * the private note is added only when the scheduled time changes (classic:
 * this request's write; Store API: no note with that text yet). If the store
 * has opened since, the meta is removed and a second note says the order is
 * no longer scheduled, so the earlier note doesn't mislead (OW-052).
 *
 * It fails open: an error here is logged and never blocks the order.
 */
class ScheduledOrders {

	/** The scheduled time was set to a new value. */
	const SET = 'set';

	/** A scheduled time from an earlier attempt was removed. */
	const CLEARED = 'cleared';

	/**
	 * Orders whose meta changed in this request (`spl_object_id` => SET or CLEARED), for the note.
	 *
	 * @var array<int, string>
	 */
	private static $changed = array();

	/**
	 * Orders the Store API checkout's meta hook saw in this request (order ID => what `apply()` did, or ''): new checkouts, never order-pay.
	 *
	 * @var array<int, string>
	 */
	private static $checkout = array();

	/**
	 * The scheduled time a retry removed (`spl_object_id` => UTC timestamp), for the "no longer scheduled" note.
	 *
	 * @var array<int, int>
	 */
	private static $removed = array();

	/**
	 * Register the hooks. No queries.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'classic_create_order' ), 10, 1 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'classic_order_created' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'store_api_order' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'store_api_order_processed' ), 10, 1 );
	}

	/**
	 * Classic checkout, before the order is saved: write or clear the meta.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public static function classic_create_order( $order ) {

		try {
			$change = self::apply( $order );

			if ( $change ) {
				self::$changed[ spl_object_id( $order ) ] = $change;
			}
		} catch ( \Throwable $e ) {
			OrderingPolicy::report( $e );
		}
	}

	/**
	 * Classic checkout, after the order is saved: the note, when the time changed or was removed.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public static function classic_order_created( $order ) {

		if ( ! $order instanceof \WC_Order || empty( self::$changed[ spl_object_id( $order ) ] ) ) {
			return;
		}

		$change = self::$changed[ spl_object_id( $order ) ];
		unset( self::$changed[ spl_object_id( $order ) ] );

		try {
			self::CLEARED === $change ? self::add_cleared_note( $order ) : self::add_note( $order );
		} catch ( \Throwable $e ) {
			OrderingPolicy::report( $e );
		}
	}

	/**
	 * Block checkout and the Store API: the meta. At Place order WooCommerce
	 * saves the order next; on an earlier draft request it may not.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public static function store_api_order( $order ) {

		try {
			$change = self::apply( $order );

			if ( $order instanceof \WC_Order && $order->get_id() ) {
				self::$checkout[ $order->get_id() ] = (string) $change;
			}
		} catch ( \Throwable $e ) {
			OrderingPolicy::report( $e );
		}
	}

	/**
	 * Block checkout and the Store API, at Place order: the note, unless the
	 * order already has it for this time (a retried checkout), or the "no longer
	 * scheduled" note when a retry removed the time. One read of the order's
	 * notes, once per scheduled order; never on a page load or order-pay.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public static function store_api_order_processed( $order ) {

		if ( ! $order instanceof \WC_Order || ! isset( self::$checkout[ $order->get_id() ] ) ) {
			return;
		}

		$change = self::$checkout[ $order->get_id() ];
		unset( self::$checkout[ $order->get_id() ] );

		try {
			if ( self::CLEARED === $change ) {
				self::add_cleared_note( $order );
				return;
			}

			$scheduled = OrderMeta::get_scheduled_for( $order );

			if ( null === $scheduled ) {
				return;
			}

			$text = self::note_text( $scheduled );

			foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) as $note ) {
				if ( isset( $note->content ) && $text === $note->content ) {
					return;
				}
			}

			self::add_note( $order );
		} catch ( \Throwable $e ) {
			OrderingPolicy::report( $e );
		}
	}

	/**
	 * Write the meta when the checkout decision is "scheduled", or remove a
	 * stale one when it isn't (a retried checkout after the store opened).
	 *
	 * @param mixed $order Order.
	 * @return string|false SET when the scheduled time was set to a new value, CLEARED when an earlier one was removed, else false.
	 */
	private static function apply( $order ) {

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		$decision = OrderingPolicy::check( Situation::CONTEXT_CHECKOUT );
		$previous = OrderMeta::get_scheduled_for( $order );

		if ( ! $decision->is_scheduled() ) {
			if ( null !== $previous || OrderMeta::is_placed_while_closed( $order ) ) {
				OrderMeta::clear( $order );

				if ( null === $previous ) {
					return false;
				}

				self::$removed[ spl_object_id( $order ) ] = $previous;
				return self::CLEARED;
			}
			return false;
		}

		$scheduled = (int) $decision->get_scheduled_for();

		OrderMeta::set_scheduled_for( $order, $scheduled );
		OrderMeta::set_placed_while_closed( $order );

		return $previous !== $scheduled ? self::SET : false;
	}

	/**
	 * The private order note, plain text, built only from our own strings and the time.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	private static function add_note( $order ) {

		$scheduled = OrderMeta::get_scheduled_for( $order );

		if ( null === $scheduled ) {
			return;
		}

		$order->add_order_note( self::note_text( $scheduled ), 0, false );
	}

	/**
	 * The private note when a retried checkout removed the scheduled time (the
	 * store had opened, so the order is an ordinary one), only when the order
	 * has the note for that time: before WooCommerce 11 the checkout page's
	 * draft requests can save the meta without a note. One read of the order's
	 * notes, only on this path.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	private static function add_cleared_note( $order ) {

		$key = spl_object_id( $order );

		if ( ! isset( self::$removed[ $key ] ) ) {
			return;
		}

		$text = self::note_text( self::$removed[ $key ] );
		unset( self::$removed[ $key ] );

		foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) as $note ) {
			if ( isset( $note->content ) && $text === $note->content ) {
				$order->add_order_note( __( 'Placed again while the store was open. No longer scheduled.', 'hourgate' ), 0, false );
				return;
			}
		}
	}

	/**
	 * The note's text for a scheduled time.
	 *
	 * @param int $scheduled UTC timestamp.
	 * @return string
	 */
	private static function note_text( $scheduled ) {

		return sprintf(
			/* translators: %s: date and time the order can be fulfilled, in the site's format. */
			__( 'Placed while the store was closed. Scheduled for %s.', 'hourgate' ),
			OrderingPolicy::format_time( $scheduled )
		);
	}
}
