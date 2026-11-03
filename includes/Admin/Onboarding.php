<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First-run experience after activation.
 */
class Onboarding {

	/**
	 * Initialize the class.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function init() {

		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_after_activation' ) );
	}

	/**
	 * Redirect to the Settings screen once, right after activation.
	 *
	 * Consumes the transient `HourGate::activate()` sets, so a newly
	 * activated site lands on Settings instead of the plain Plugins
	 * list with no orientation. Skips bulk activation (multiple plugins
	 * activated at once) and AJAX/REST/cron requests, matching the standard
	 * WP convention for activation redirects.
	 *
	 * @return void
	 */
	public static function maybe_redirect_after_activation() {

		$user_id = get_transient( 'hourgate_activation_redirect' );

		if ( ! $user_id ) {
			return;
		}

		delete_transient( 'hourgate_activation_redirect' );

		if ( wp_doing_ajax() || HOURGATE_REST_REQUEST || defined( 'DOING_CRON' ) ) {
			return;
		}

		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only (WordPress sets it after a bulk activation); it can only skip the redirect.
			return;
		}

		if ( get_current_user_id() !== (int) $user_id ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . AdminMenu::PARENT_SLUG ) );
		exit;
	}
}
