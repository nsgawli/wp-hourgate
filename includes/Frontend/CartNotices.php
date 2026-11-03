<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Frontend;

use HourGate\Core\Enforcement\AddToCart;
use HourGate\Core\Enforcement\Message;
use HourGate\Core\Ordering\Situation;
use HourGate\Core\OrderingPolicy;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the shopper about the closed store on the cart and checkout before
 * Place order (OW-042).
 *
 * - Checkout blocked (modes 1 and 3, and mode 2 with nothing to schedule
 *   for): the closed message, in a neutral notice (OW-048). It isn't an
 *   error: the shopper did nothing wrong. Place order is still refused
 *   with an error, by `Core\Enforcement\Checkout`.
 * - Mode 2 (orders scheduled): an info notice, "Orders placed now are
 *   scheduled for {time}."
 * - Nothing while open, with an empty cart, or on order-pay and
 *   order-received (paying is allowed while closed, D1). On the blocks the
 *   closed banner is still printed, hidden and empty, while the cart has
 *   items (below).
 *
 * Classic pages print it at `woocommerce_before_cart` and
 * `woocommerce_before_checkout_form`. The Cart and Checkout blocks have no
 * PHP notice hook, so the block's rendered HTML gets the same banner
 * WooCommerce's own block notices use (`render_block_{name}`). On the
 * blocks the closed message is a warning banner, and the status script
 * keeps the cart error out of the blocks' own notices (OW-048).
 *
 * It also prints notices that WooCommerce queued for a rejected
 * `?add-to-cart=` on block-theme pages, which have no notices area.
 *
 * Cart and checkout pages aren't page-cached, so the text is printed by PHP.
 * The decision is memoized, so this adds no query.
 */
class CartNotices {

	/**
	 * Register the hooks. No I/O.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		add_action( 'woocommerce_before_cart', array( __CLASS__, 'print_classic' ), 5 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'print_classic' ), 5 );
		add_filter( 'render_block_woocommerce/cart', array( __CLASS__, 'prepend_to_block' ), 10, 1 );
		add_filter( 'render_block_woocommerce/checkout', array( __CLASS__, 'prepend_to_block' ), 10, 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'print_rejected_add' ), 5 );
	}

	/**
	 * The notice to show now: `{ type: 'closed'|'info', text }` (plain text), or null.
	 *
	 * @return array|null
	 */
	public static function notice() {

		if ( ! self::has_order_to_place() ) {
			return null;
		}

		$decision = OrderingPolicy::check( Situation::CONTEXT_CHECKOUT );
		$message  = Message::for_blocked( $decision );

		if ( null !== $message ) {
			return array(
				'type' => 'closed',
				'text' => $message,
			);
		}

		if ( $decision->is_scheduled() && null !== $decision->get_scheduled_for() ) {
			return array(
				'type' => 'info',
				'text' => sprintf(
					/* translators: %s: when the store can fulfil the order, e.g. "tomorrow 9:00 am". */
					__( 'Orders placed now are scheduled for %s.', 'hourgate' ),
					OrderingPolicy::format_when( (int) $decision->get_scheduled_for() )
				),
			);
		}

		return null;
	}

	/**
	 * Whether this page could place an order: a cart with items, and not
	 * order-pay or order-received (paying is never told "we're closed", D1).
	 *
	 * @return bool
	 */
	private static function has_order_to_place() {

		if ( ! function_exists( 'WC' ) || ! function_exists( 'is_wc_endpoint_url' ) ) {
			return false;
		}

		if ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
			return false;
		}

		$cart = WC()->cart;

		return $cart instanceof \WC_Cart && ! $cart->is_empty();
	}

	/**
	 * Classic cart and checkout: print the notice above the page.
	 *
	 * @return void
	 */
	public static function print_classic() {

		$notice = self::notice();

		if ( null === $notice || ! function_exists( 'wc_print_notice' ) ) {
			return;
		}

		// WooCommerce's "notice" type is the info notice; the closed message is one too (OW-048).
		wc_print_notice( Message::html( $notice['text'] ), 'notice' );
	}

	/**
	 * Cart and Checkout blocks: put the banner above the block. The closed
	 * message is a warning, not an error: the blocks would put WooCommerce's
	 * "Please fix the following error" heading above an error (OW-048). The
	 * cart preloaded into these pages leaves the error out
	 * (`Checkout::hide_from_preloaded_cart()`), and the status script removes
	 * it from the blocks' notices when a later cart response brings it back.
	 * The script also relabels Place order with `data-place-order-label`.
	 *
	 * The closed banner is printed whenever the cart has an order to place,
	 * hidden and empty while checkout is allowed, so a page loaded while open
	 * still has it and the script: it is shown when the status says checkout
	 * is refused, or when the server refuses Place order first (OW-048).
	 *
	 * @param mixed $content Rendered block HTML.
	 * @return mixed
	 */
	public static function prepend_to_block( $content ) {

		if ( ! is_string( $content ) || ! self::has_order_to_place() ) {
			return $content;
		}

		$notice = self::notice();
		$closed = null !== $notice && 'closed' === $notice['type'];
		$html   = '';

		if ( null !== $notice && 'info' === $notice['type'] ) {
			$html .= sprintf(
				'<div class="wc-block-components-notice-banner is-info hourgate-notice hourgate-notice--scheduled" role="status"><div class="wc-block-components-notice-banner__content">%s</div></div>',
				esc_html( $notice['text'] )
			);
		}

		// The status script shows and hides the closed notice, and holds Place order while it is shown.
		Assets::need();

		$html .= sprintf(
			'<div class="wc-block-components-notice-banner is-warning hourgate-notice hourgate-notice--closed" role="status" data-blocked="%1$s" data-place-order-label="%2$s"%3$s><div class="wc-block-components-notice-banner__content">%4$s</div></div>',
			$closed ? '1' : '0',
			/* translators: Label of the Checkout block's Place order button while the store is closed. */
			esc_attr__( 'Ordering is closed', 'hourgate' ),
			// Not `hidden`: WooCommerce's banner CSS sets `display` and would win.
			$closed ? '' : ' style="display:none"',
			$closed ? esc_html( $notice['text'] ) : ''
		);

		return $html . $content;
	}

	/**
	 * Block themes: a rejected `?add-to-cart=` queues its notice, but pages
	 * without a notices area never print it. Print what is queued at the top
	 * of the body, once (printing clears it, so pages that do have a notices
	 * area don't repeat it).
	 *
	 * @return void
	 */
	public static function print_rejected_add() {

		if ( ! AddToCart::was_rejected() || ! function_exists( 'wc_print_notices' ) || ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return;
		}

		echo '<div class="woocommerce woocommerce-notices-wrapper hourgate-notices">';
		wc_print_notices();
		echo '</div>';
	}
}
