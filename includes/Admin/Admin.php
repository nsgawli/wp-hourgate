<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared admin assets for every HourGate screen.
 */
class Admin {

	/**
	 * Initialize the class.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function init() {

		// Enqueue the scripts and styles for the admin page.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Whether the given admin page hook belongs to an HourGate screen.
	 *
	 * Matches on the menu slug rather than the full hook name, because
	 * WordPress builds submenu hooks from the *translated* parent menu
	 * title (e.g. `hourgate_page_hourgate-settings` in English). The
	 * top-level page's own hook, `toplevel_page_hourgate-settings`,
	 * matches the same pattern.
	 *
	 * @param string $hook The current admin page hook.
	 * @return bool
	 */
	public static function is_hourgate_screen( $hook ) {
		return (bool) preg_match( '/_page_hourgate-[a-z-]+$/', $hook );
	}

	/**
	 * Enqueues the scripts and styles for the admin page.
	 *
	 * This method is called during the admin_enqueue_scripts action and is responsible
	 * for enqueuing the necessary scripts and styles for the admin page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public static function enqueue_scripts( $hook ) {

		// HourGate's own screens only - the common bundle defines the
		// `--hourgate-*` design tokens, which no other admin screen needs.
		if ( ! self::is_hourgate_screen( $hook ) ) {
			return;
		}

		// add body class for the admin page.
		add_filter(
			'admin_body_class',
			function ( $classes ) {
				$classes .= ' toplevel-hourgate-page';
				return $classes;
			}
		);

		// Load asset file.
		$assets = require HOURGATE_ABSPATH . 'build/common/index.asset.php';

		// Enqueue page styles.
		wp_enqueue_style(
			'hourgate-common-scripts',
			HOURGATE_PLUGIN_URL . 'build/common/index' . ( is_rtl() ? '-rtl.css' : '.css' ),
			array(),
			$assets['version']
		);

		// Enqueue page script.
		wp_enqueue_script(
			'hourgate-common-scripts',
			HOURGATE_PLUGIN_URL . 'build/common/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		// script translations.
		wp_set_script_translations( 'hourgate-common-scripts', 'hourgate' );
	}
}
