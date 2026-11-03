<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the code that must run on every request type: front end, admin,
 * admin-ajax, REST (including the WooCommerce Store API) and cron.
 *
 * Ordering rules live here so they hold on every checkout path, not only
 * where the storefront UI loads. Keep it free of output.
 */
class Autoloader {

	/**
	 * Initialize the autoloader.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function run() {

		// Keeps the memoized schedule engine fresh when settings change; registers hooks only.
		StoreSchedule::register_hooks();
		Behaviour::register_hooks();
		Pause::register_hooks();
		OrderingPolicy::register_hooks();

		// WooCommerce hooks need WooCommerce loaded.
		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		// Rejects checkouts while closed or paused (modes 1 and 3).
		Enforcement\Checkout::register_hooks();

		// Rejects add-to-cart while closed or paused (mode 3, catalogue).
		Enforcement\AddToCart::register_hooks();

		// Accepts orders while closed in mode 2 and records when they can be fulfilled.
		Enforcement\ScheduledOrders::register_hooks();

		// Shows when an order can be fulfilled: totals rows (emails, thank-you, My Account) and the admin order screen.
		OrderDisplay::register_hooks();

		// Ordering state and one-click pause in the admin bar, for managers.
		AdminBar::register_hooks();
	}

	/**
	 * Whether WooCommerce is loaded.
	 *
	 * `Requires Plugins` normally guarantees it, but WooCommerce can still be
	 * missing (e.g. its folder was deleted), so check before using its API.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}
