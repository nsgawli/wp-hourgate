<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HourGate's order meta: read and write only, through the order object
 * (`get_meta()` / `update_meta_data()`), so it works with HPOS on and off.
 * Setters change the order in memory; the caller saves it.
 *
 * Keys (OW-002 naming; the contract for sprint 5's display and Free 1.1's picker):
 * - `_hourgate_scheduled_for`: when the order can be fulfilled, ISO 8601
 *   with the store's offset at that instant (`2026-10-19T10:00:00+05:30`);
 * - `_hourgate_placed_while_closed`: `'1'` when the order came in
 *   while the store was closed or paused.
 */
class OrderMeta {

	/**
	 * When the order can be fulfilled.
	 *
	 * @var string
	 */
	const SCHEDULED_FOR = '_hourgate_scheduled_for';

	/**
	 * Placed while closed.
	 *
	 * @var string
	 */
	const PLACED_WHILE_CLOSED = '_hourgate_placed_while_closed';

	/**
	 * When the order can be fulfilled (UTC), or null when it has no valid value.
	 *
	 * Missing, empty, non-string or malformed values all give null; this never throws.
	 *
	 * @param mixed $order Order.
	 * @return int|null
	 */
	public static function get_scheduled_for( $order ) {

		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		return self::parse( $order->get_meta( self::SCHEDULED_FOR, true ) );
	}

	/**
	 * Set when the order can be fulfilled. Refuses anything but a positive integer timestamp.
	 *
	 * @param mixed $order Order.
	 * @param mixed $ts    UTC timestamp.
	 * @return bool Whether it was set.
	 */
	public static function set_scheduled_for( $order, $ts ) {

		if ( ! $order instanceof \WC_Order || ! is_int( $ts ) || $ts <= 0 ) {
			return false;
		}

		$order->update_meta_data( self::SCHEDULED_FOR, ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( wp_timezone() )->format( DATE_ATOM ) );

		return true;
	}

	/**
	 * Whether the order came in while the store was closed or paused.
	 *
	 * @param mixed $order Order.
	 * @return bool
	 */
	public static function is_placed_while_closed( $order ) {
		return $order instanceof \WC_Order && '1' === $order->get_meta( self::PLACED_WHILE_CLOSED, true );
	}

	/**
	 * Mark the order as placed while closed.
	 *
	 * @param mixed $order Order.
	 * @return bool Whether it was set.
	 */
	public static function set_placed_while_closed( $order ) {

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		$order->update_meta_data( self::PLACED_WHILE_CLOSED, '1' );

		return true;
	}

	/**
	 * Remove both keys.
	 *
	 * @param mixed $order Order.
	 * @return void
	 */
	public static function clear( $order ) {

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$order->delete_meta_data( self::SCHEDULED_FOR );
		$order->delete_meta_data( self::PLACED_WHILE_CLOSED );
	}

	/**
	 * A stored ISO 8601 value (`Y-m-d\TH:i:sP`, or `Z`) to a timestamp, or null.
	 *
	 * @param mixed $value Stored value.
	 * @return int|null
	 */
	private static function parse( $value ) {

		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)$/D', $value ) ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:sP', $value );

		// Reject values PHP would roll over (2026-02-30, 25:00) rather than read.
		if ( false === $date || $date->format( 'Y-m-d\TH:i:s' ) !== substr( $value, 0, 19 ) ) {
			return null;
		}

		return $date->getTimestamp();
	}
}
