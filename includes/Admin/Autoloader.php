<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the admin interface.
 */
class Autoloader {

	/**
	 * Initialize the autoloader.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function run() {

		// load admin style and scripts.
		Admin::init();

		// top-level admin menu.
		AdminMenu::init();

		// load setting admin page.
		SettingsPage::init();

		// blocks, for the block editor (OW-040).
		\HourGate\Frontend\Blocks::register_hooks();

		// first-run activation redirect.
		Onboarding::init();
	}
}
