<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Enforcement;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use HourGate\Core\OrderingPolicy;
use HourGate\Core\Ordering\Situation;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rejects checkouts while the store is closed or paused, in closed modes 1
 * (`block`) and 3 (`catalogue`), on every shopper checkout path (OW-029):
 *
 * - block checkout, direct Store API `POST /checkout` and express payment
 *   buttons (they post to the Store API): `woocommerce_store_api_cart_errors`,
 *   which rejects before any draft order exists (HTTP 409, `hourgate_closed`);
 * - classic checkout (`wc-ajax=checkout`): `woocommerce_after_checkout_validation`.
 *
 * Paying an existing order is allowed while closed (D1). The filter
 * `hourgate_allow_order_pay_while_closed` (default true) can refuse
 * it, so the order-pay hooks are registered too but return at once unless it
 * is false. Orders made by staff and integrations (admin, REST `wc/v3`,
 * `wc_create_order()`, renewals) never pass through these hooks (D3).
 * Mode 2 (`schedule`) accepts the order instead; that is `ScheduledOrders`.
 *
 * Only hook registration happens at boot. Each callback asks
 * `OrderingPolicy`, which is memoized for the request.
 */
class Checkout {

	/**
	 * Error code shoppers' requests are rejected with.
	 *
	 * @var string
	 */
	const ERROR_CODE = 'hourgate_closed';

	/**
	 * Register the hooks. No queries.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'store_api_cart_errors' ), 10, 2 );
		add_filter( 'woocommerce_hydration_request_after_callbacks', array( __CLASS__, 'hide_from_preloaded_cart' ), 10, 3 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'classic_checkout' ), 10, 2 );
		add_action( 'woocommerce_before_pay_action', array( __CLASS__, 'classic_order_pay' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'store_api_order_pay' ), 10, 2 );
	}

	/**
	 * Block checkout, the Store API and express payments.
	 *
	 * This also runs on cart reads (the block theme's mini-cart preloads
	 * `GET /wc/store/v1/cart` on most pages), so the error shows at the top of
	 * the mini-cart too. An empty cart is skipped before anything is decided:
	 * nothing can be checked out, and most visitors pay nothing.
	 *
	 * @param \WP_Error     $errors Errors to add to.
	 * @param \WC_Cart|null $cart   Cart.
	 * @return void
	 */
	public static function store_api_cart_errors( $errors, $cart = null ) {

		if ( ! $errors instanceof \WP_Error || $errors->get_error_message( self::ERROR_CODE ) ) {
			return;
		}

		if ( $cart instanceof \WC_Cart && $cart->is_empty() ) {
			return;
		}

		$message = self::blocked_message();

		if ( null !== $message ) {
			$errors->add( self::ERROR_CODE, Message::html( $message ) );
		}
	}

	/**
	 * Keep the closed error out of the cart preloaded into the Cart and
	 * Checkout pages (OW-048).
	 *
	 * The Cart and Checkout blocks show cart errors under WooCommerce's fixed
	 * "Please fix the following error before continuing" heading, which makes
	 * "we're closed" read like a fault. There the shopper gets a neutral
	 * notice from `Frontend\CartNotices` instead, and the status script
	 * removes the error notice when a later cart response brings it back.
	 * Everywhere else the preloaded cart keeps it, so the mini-cart still
	 * shows the message (without that heading). Store API responses are
	 * never changed: `POST /checkout` still calls `validate_cart()`, which
	 * throws on the error, so the order is still refused.
	 *
	 * @param mixed $response Response.
	 * @param mixed $handler  Route handler (unused).
	 * @param mixed $request  Request.
	 * @return mixed
	 */
	public static function hide_from_preloaded_cart( $response, $handler, $request ) {

		if ( ! $response instanceof \WP_REST_Response || ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		if ( ! preg_match( '#^/wc/store(?:/v\d+)?/cart/?$#', (string) $request->get_route() ) ) {
			return $response;
		}

		if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) || ! ( is_cart() || is_checkout() ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! is_array( $data ) || empty( $data['errors'] ) || ! is_array( $data['errors'] ) ) {
			return $response;
		}

		$kept = array();

		foreach ( $data['errors'] as $error ) {
			if ( ! is_array( $error ) || ! isset( $error['code'] ) || self::ERROR_CODE !== $error['code'] ) {
				$kept[] = $error;
			}
		}

		if ( count( $kept ) !== count( $data['errors'] ) ) {
			$data['errors'] = $kept;
			$response->set_data( $data );
		}

		return $response;
	}

	/**
	 * Classic checkout: the error shows inline and no order is created.
	 *
	 * @param array     $data   Posted checkout data (unused).
	 * @param \WP_Error $errors Errors to add to.
	 * @return void
	 */
	public static function classic_checkout( $data, $errors ) {

		if ( ! $errors instanceof \WP_Error || $errors->get_error_message( self::ERROR_CODE ) ) {
			return;
		}

		$message = self::blocked_message();

		if ( null !== $message ) {
			$errors->add( self::ERROR_CODE, Message::html( $message ) );
		}
	}

	/**
	 * Classic order-pay form: refused only when the filter says so.
	 *
	 * @param \WC_Order $order Order being paid.
	 * @return void
	 */
	public static function classic_order_pay( $order ) {

		$message = self::order_pay_blocked_message( $order );

		if ( null !== $message ) {
			wc_add_notice( Message::html( $message ), 'error' );
		}
	}

	/**
	 * Store API order-pay (`POST /checkout/{id}`): refused only when the
	 * filter says so. The hook also fires on a new checkout (`POST /checkout`),
	 * which `store_api_cart_errors` has already decided, so only the
	 * order-pay route is handled here.
	 *
	 * @param \WC_Order        $order   Order.
	 * @param \WP_REST_Request $request Request.
	 * @return void
	 * @throws RouteException When order-pay is refused; the order stays as it was.
	 */
	public static function store_api_order_pay( $order, $request ) {

		if ( ! $request instanceof \WP_REST_Request || ! preg_match( '#/checkout/\d+$#', (string) $request->get_route() ) ) {
			return;
		}

		$message = self::order_pay_blocked_message( $order );

		if ( null !== $message ) {
			throw new RouteException( self::ERROR_CODE, Message::html( $message ), 409 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The code is a fixed constant and the message is escaped by Message::html().
		}
	}

	/**
	 * The closed message when checkout must be rejected now, or null.
	 *
	 * @return string|null Plain text.
	 */
	private static function blocked_message() {
		return Message::for_blocked( OrderingPolicy::check( Situation::CONTEXT_CHECKOUT ) );
	}

	/**
	 * The closed message when paying this order must be refused, or null.
	 * Only when the filter returns false and checkout would be blocked now.
	 *
	 * @param mixed $order Order.
	 * @return string|null Plain text.
	 */
	private static function order_pay_blocked_message( $order ) {

		/**
		 * Filters whether shoppers may pay an existing order while the store is closed or paused.
		 *
		 * Default true (D1): a failed card retried after closing still goes
		 * through. Return false to refuse it with the closed message. Mode 2
		 * (accept for the next opening) never refuses it.
		 *
		 * @param bool           $allow Whether to allow it.
		 * @param \WC_Order|null $order The order being paid.
		 */
		$allow = apply_filters( 'hourgate_allow_order_pay_while_closed', true, $order instanceof \WC_Order ? $order : null );

		if ( false !== $allow ) {
			return null;
		}

		return self::blocked_message();
	}
}
