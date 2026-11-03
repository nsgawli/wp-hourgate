<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Frontend;

use HourGate\Core\Behaviour;
use HourGate\Installation\Settings\Behaviour as BehaviourSettings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The storefront notice bar (OW-038): an empty container that the status
 * script fills while ordering is closed or paused.
 *
 * PHP prints only the empty, hidden container (and asks for the script), on
 * every page in scope whatever the store's state now, so a copy of the page
 * cached while open still shows the bar once the store closes. The message is
 * never in the HTML: the script puts the closed message from `GET /status` in
 * with `textContent`, and hides the bar again when the store opens, when the
 * setting is turned off, or when the shopper dismissed this state.
 *
 * Placement: at `wp_body_open`, in the normal page flow above the theme's
 * header. A theme that never fires `wp_body_open` gets it at `wp_footer`, as a
 * bar fixed to the bottom of the window. Order-pay and order-received pages
 * never get it (paying an existing order is allowed while closed, D1).
 */
class NoticeBar {

	/**
	 * The bar's CSS, printed inline with the status script: in the page flow
	 * (or fixed to the bottom), logical properties so right-to-left works,
	 * and `[hidden]` winning over `display`.
	 *
	 * @var string
	 */
	const CSS = '.hourgate-notice-bar{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:8px 16px;box-sizing:border-box;width:100%;padding:10px 16px;background:#fff4d6;color:#1e1e1e;border-block-end:1px solid #e0c36a;font-size:15px;line-height:1.4;text-align:center}.hourgate-notice-bar[hidden]{display:none}.hourgate-notice-bar.is-fixed{position:fixed;inset-inline:0;bottom:0;z-index:99999;border-block-end:0;border-block-start:1px solid #e0c36a}.hourgate-notice-bar button{padding:2px 12px;border:1px solid currentcolor;border-radius:3px;background:transparent;color:inherit;font:inherit;cursor:pointer}';

	/**
	 * Whether the container was printed on this page.
	 *
	 * @var bool
	 */
	private static $printed = false;

	/**
	 * Register the hooks. Called from `Frontend\Autoloader`; no I/O.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		add_action( 'wp_body_open', array( __CLASS__, 'render_in_flow' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_fixed' ), 5 );
	}

	/**
	 * Forget that the container was printed (tests, long-running processes).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$printed = false;
	}

	/**
	 * Print the bar at the top of the page.
	 *
	 * @return void
	 */
	public static function render_in_flow() {
		self::render( false );
	}

	/**
	 * Print the bar at the bottom for a theme that never fired `wp_body_open`.
	 *
	 * @return void
	 */
	public static function render_fixed() {

		if ( ! did_action( 'wp_body_open' ) ) {
			self::render( true );
		}
	}

	/**
	 * Whether the bar belongs on the page being rendered: the setting is on,
	 * and the page is in scope (`woocommerce`: shop, product archives,
	 * product, cart, checkout and My Account; `all`: every front-end page),
	 * but never a feed, an embed, a preview, or the order-pay and
	 * order-received pages.
	 *
	 * @return bool
	 */
	public static function in_scope() {

		if ( ! Behaviour::notice_bar() || is_admin() || is_feed() || is_embed() || is_customize_preview() ) {
			return false;
		}

		if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) {
			return false;
		}

		if ( BehaviourSettings::BAR_SCOPE_ALL === Behaviour::notice_bar_scope() ) {
			return true;
		}

		return function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() );
	}

	/**
	 * Print the empty container, once per page, and ask for the script.
	 *
	 * @param bool $fixed Whether it is the fixed bottom bar.
	 * @return void
	 */
	private static function render( $fixed ) {

		if ( self::$printed || ! self::in_scope() ) {
			return;
		}

		self::$printed = true;

		Assets::need();
		Assets::add_style( self::CSS );

		printf(
			'<div class="hourgate-notice-bar%1$s" role="status" data-dismiss="%2$s" hidden></div>',
			$fixed ? ' is-fixed' : '',
			esc_attr_x( 'Dismiss', 'button that hides the closed-store notice bar', 'hourgate' )
		);
	}
}
