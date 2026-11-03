<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Admin;

use HourGate\Core\Capabilities;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level HourGate admin menu and its submenus.
 */
class AdminMenu {

	/**
	 * Top-level menu slug. Also the slug of the Settings submenu, so clicking
	 * the top-level item opens Settings.
	 *
	 * @var string
	 */
	const PARENT_SLUG = 'hourgate-settings';

	/**
	 * Initialize the class.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function init() {

		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register the top-level menu and every submenu.
	 *
	 * Each admin screen adds itself through the `hourgate_admin_submenus`
	 * filter (see `SettingsPage::add_submenu()`), sorted by `position`.
	 *
	 * @return void
	 */
	public static function register_menu() {

		add_menu_page(
			esc_attr__( 'HourGate', 'hourgate' ),
			esc_attr__( 'HourGate', 'hourgate' ),
			Capabilities::get_manage_capability(),
			self::PARENT_SLUG,
			array( SettingsPage::class, 'render_settings_page' ),
			self::get_menu_icon(),
			56
		);

		$submenus = apply_filters( 'hourgate_admin_submenus', array() );

		usort(
			$submenus,
			function ( $a, $b ) {
				return $a['position'] <=> $b['position'];
			}
		);

		// add submenus.
		foreach ( $submenus as $submenu ) {
			add_submenu_page(
				self::PARENT_SLUG,
				$submenu['page_title'],
				$submenu['menu_title'],
				$submenu['capability'],
				$submenu['menu_slug'],
				$submenu['callback']
			);
		}
	}

	/**
	 * Build the admin menu icon.
	 *
	 * A cart with a clock in it (OW-063; the header's ProductIcon is the
	 * same drawing) as a base64 SVG data URI, per the WP convention for
	 * custom menu icons. Filled shapes only, no strokes or masks: WP's
	 * svg-painter.js rewrites every `fill="…"` to the admin colour
	 * scheme's idle, hover and active colours. `#a7aaad` (WP's default
	 * idle menu grey) is what shows before that script runs.
	 *
	 * @return string
	 */
	private static function get_menu_icon(): string {

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
			. '<path fill="#a7aaad" fill-rule="evenodd" d="M12.5.4a5.6 5.6 0 1 1-.01 0zM12.5 1.9a4.1 4.1 0 1 0 .01 0zM11.8 3.2h1.4v2.9h2.1v1.4h-3.5z"/>'
			. '<path fill="#a7aaad" d="M.5 3.2h3.3l2.6 10.3h10.4l.7-2.6h1.5l-1.1 4.1H5.3L2.7 4.7H.5z"/>'
			. '<circle fill="#a7aaad" cx="7.4" cy="17.3" r="1.7"/>'
			. '<circle fill="#a7aaad" cx="15.6" cy="17.3" r="1.7"/>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes a fixed SVG for the menu icon data URI, as WordPress expects; no input involved.
	}
}
