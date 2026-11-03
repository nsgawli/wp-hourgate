<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Admin;

use HourGate\Core\Capabilities;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings admin screen.
 */
class SettingsPage {

	/**
	 * Initialize the class.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function init() {

		add_filter( 'hourgate_admin_submenus', array( __CLASS__, 'add_submenu' ) );

		// Enqueue the scripts and styles for the admin page.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Add submenu for settings.
	 *
	 * @param array $submenus - submenu array.
	 * @return array
	 */
	public static function add_submenu( $submenus ) {

		$submenus[] = array(
			'page_title' => esc_attr__( 'Settings', 'hourgate' ),
			'menu_title' => esc_attr__( 'Settings', 'hourgate' ),
			'capability' => Capabilities::get_manage_capability(),
			'menu_slug'  => 'hourgate-settings',
			'callback'   => array( __CLASS__, 'render_settings_page' ),
			'position'   => 99,
		);

		return $submenus;
	}

	/**
	 * Render the main admin page.
	 *
	 * This method is called when the admin page is accessed and is responsible for
	 * rendering the main content of the admin page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {

		// WordPress already checks the menu capability; check again so the
		// screen never relies on the menu alone.
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'hourgate' ), 403 );
		}

		echo '<div id="hourgate-admin-settings"></div>';
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

		if ( ! preg_match( '/_page_hourgate-settings$/', $hook ) ) {
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
		$assets = require HOURGATE_ABSPATH . 'build/admin-settings/index.asset.php';

		// Enqueue page styles.
		wp_enqueue_style(
			'hourgate-admin-settings',
			HOURGATE_PLUGIN_URL . 'build/admin-settings/index' . ( is_rtl() ? '-rtl.css' : '.css' ),
			array(),
			$assets['version']
		);

		// Enqueue page script.
		wp_enqueue_script(
			'hourgate-admin-settings',
			HOURGATE_PLUGIN_URL . 'build/admin-settings/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		// What the current user may do, so the UI can hide what the server would refuse.
		// The server still enforces every check; this only shapes the UI.
		wp_add_inline_script(
			'hourgate-admin-settings',
			'window.hourGateSettings = ' . wp_json_encode(
				array(
					'user' => array(
						'canManageSite' => Capabilities::current_user_can_manage_site(),
					),
				)
			) . ';',
			'before'
		);

		// script translations.
		wp_set_script_translations( 'hourgate-admin-settings', 'hourgate' );
	}
}
