<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the storefront interface.
 */
class Autoloader {

	/**
	 * Initialize the autoloader.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function run() {

		// The storefront status script, printed only on pages that ask for it.
		Assets::register_hooks();

		// Catalogue mode: pages with an add-to-cart control ask for that script (OW-039).
		CatalogueButtons::register_hooks();

		// The notice bar's empty container (OW-038).
		NoticeBar::register_hooks();

		// The closed or scheduled notice on the cart and checkout (OW-042).
		CartNotices::register_hooks();

		// The "Store status" block and shortcode (OW-040).
		Blocks::register_hooks();
		Shortcodes::register_hooks();
	}
}
