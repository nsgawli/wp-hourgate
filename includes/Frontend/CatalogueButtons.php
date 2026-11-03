<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Frontend;

use HourGate\Core\Behaviour;
use HourGate\Installation\Settings\Behaviour as BehaviourSettings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalogue mode (OW-039): pages that render an add-to-cart control load the
 * status script, which disables the controls while add-to-cart is blocked and
 * restores them when the store reopens.
 *
 * Nothing is decided or changed here: the hooks below only notice that a
 * control was rendered and, in closed mode `catalogue`, ask for the script.
 * The script asks for it whatever the store's state now, so a page cached
 * while open still disables its buttons once the store closes. The buttons
 * stay in the HTML; the product's "is purchasable" check is never filtered (D3), and
 * the server-side rejection (`Core\Enforcement\AddToCart`) stays the real
 * guard.
 */
class CatalogueButtons {

	/**
	 * Where WooCommerce renders an add-to-cart control: hook => accepted args.
	 * Filters get their first argument back unchanged.
	 *
	 * @var array<string, int>
	 */
	const HOOKS = array(
		// Classic loops: shop and archives, shortcodes, related products, cross-sells.
		'woocommerce_loop_add_to_cart_link'         => 1,
		// The single product form (simple, variable, grouped), classic or the Add to Cart form block.
		'woocommerce_before_add_to_cart_button'     => 0,
		// Product Collection and other block loops.
		'render_block_woocommerce/product-button'   => 1,
		// The All Products block (its buttons are rendered in the browser).
		'render_block_woocommerce/all-products'     => 1,
		// The block single product form with options.
		'render_block_woocommerce/add-to-cart-with-options' => 1,
		// Older product grid blocks (Products by category, Hand-picked products, …).
		'woocommerce_blocks_product_grid_item_html' => 1,
	);

	/**
	 * Register the hooks. Called from `Frontend\Autoloader`; no I/O.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		foreach ( self::HOOKS as $hook => $args ) {
			add_filter( $hook, array( __CLASS__, 'rendered' ), 10, $args );
		}
	}

	/**
	 * A control was rendered: in catalogue mode, ask for the status script.
	 * O(1) per call (the settings are memoized, the option autoloaded).
	 *
	 * @param mixed $value The filtered value (nothing for actions).
	 * @return mixed `$value` unchanged.
	 */
	public static function rendered( $value = null ) {

		if ( ! Assets::is_needed() && BehaviourSettings::MODE_CATALOGUE === Behaviour::closed_mode() ) {
			Assets::need();
		}

		return $value;
	}
}
