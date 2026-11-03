<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Enforcement;

use HourGate\Core\OrderingPolicy;
use HourGate\Core\Ordering\Situation;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rejects add-to-cart while the store is closed or paused in closed mode 3
 * (`catalogue`), on every shopper path (OW-030). One hook covers them all
 * (OW-001): `woocommerce_add_to_cart_validation`, which runs for classic
 * `?add-to-cart=` (GET and the product-form POST, simple and variable),
 * `wc-ajax=add_to_cart`, Store API `cart/add-item` and `batch`, and order again.
 *
 * Modes 1 and 2 never reject add-to-cart. Staff and REST `wc/v3` orders never
 * call this hook. Product data is never touched: the "is purchasable" filter
 * is not used, so carts, staff orders and agentic orders are unaffected (D3).
 *
 * The hook fires once per add, not per product in a loop, and the decision is
 * memoized for the request, so it costs O(1).
 */
class AddToCart {

	/**
	 * Whether an add was rejected in this request (the storefront prints the
	 * notice on pages that would not, OW-042).
	 *
	 * @var bool
	 */
	private static $rejected = false;

	/**
	 * Whether an add-to-cart was rejected in this request.
	 *
	 * @return bool
	 */
	public static function was_rejected() {
		return self::$rejected;
	}

	/**
	 * Register the hooks. No queries.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		// Only `$passed` is needed; WooCommerce passes 3, 5 or 6 arguments depending on the path.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 1 );
		add_filter( 'woocommerce_empty_cart_when_order_again', array( __CLASS__, 'keep_cart_on_order_again' ), 99, 1 );
	}

	/**
	 * Reject the add with the closed message while add-to-cart is blocked.
	 *
	 * @param mixed $passed Whether earlier validation passed.
	 * @return mixed `$passed` unchanged, or false.
	 */
	public static function validate( $passed ) {

		// Never turn an earlier rejection into a pass, and don't decide when there's nothing to reject.
		if ( ! $passed ) {
			return $passed;
		}

		$message = Message::for_blocked( OrderingPolicy::check( Situation::CONTEXT_ADD_TO_CART ) );

		if ( null === $message ) {
			return $passed;
		}

		self::$rejected = true;

		$html = Message::html( $message );

		// One notice, however many items one request adds (order again, a Store API batch).
		if ( function_exists( 'WC' ) && WC()->session && ! wc_has_notice( $html, 'error' ) ) {
			wc_add_notice( $html, 'error' );
		}

		return false;
	}

	/**
	 * Order again empties the cart before it validates the order's items
	 * (`woocommerce_empty_cart_when_order_again`, default true). While
	 * add-to-cart is blocked nothing will be added, so keep the shopper's
	 * current cart instead of emptying it.
	 *
	 * @param mixed $empty_cart Whether to empty the cart.
	 * @return mixed
	 */
	public static function keep_cart_on_order_again( $empty_cart ) {

		if ( ! $empty_cart ) {
			return $empty_cart;
		}

		return OrderingPolicy::check( Situation::CONTEXT_ADD_TO_CART )->is_blocked() ? false : $empty_cart;
	}
}
