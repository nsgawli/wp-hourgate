<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Who may use HourGate.
 *
 * Shop managers run the store, so menus, settings REST routes and order
 * screens need `manage_woocommerce` (filterable). Settings that destroy data or
 * affect the whole site (e.g. `removeDataOnUninstall`) and Pro's license stay on
 * `manage_options`.
 */
class Capabilities {

	/**
	 * Default capability for using HourGate.
	 *
	 * @var string
	 */
	const DEFAULT_MANAGE = 'manage_woocommerce';

	/**
	 * Capability for site-wide or destructive settings. Not filterable.
	 *
	 * @var string
	 */
	const MANAGE_SITE = 'manage_options';

	/**
	 * The capability needed to use HourGate's screens and REST routes.
	 *
	 * @return string
	 */
	public static function get_manage_capability() {

		/**
		 * Filters the capability needed to use HourGate's admin screens and
		 * settings REST routes.
		 *
		 * @since 0.1.0
		 *
		 * @param string $capability Default `manage_woocommerce`.
		 */
		$capability = apply_filters( 'hourgate_manage_capability', self::DEFAULT_MANAGE );

		// A broken filter must not open the screens to everyone or lock them for good.
		if ( ! is_string( $capability ) || '' === trim( $capability ) ) {
			return self::DEFAULT_MANAGE;
		}

		return $capability;
	}

	/**
	 * Whether the current user may use HourGate.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( self::get_manage_capability() );
	}

	/**
	 * Whether the current user may change site-wide or destructive settings.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_site() {
		return current_user_can( self::MANAGE_SITE );
	}
}
