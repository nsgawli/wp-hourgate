<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core\Enforcement;

use HourGate\Core\OrderingPolicy;
use HourGate\Core\Ordering\Decision;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The closed message as the enforcement hooks hand it to WooCommerce.
 */
class Message {

	/**
	 * The plain-text message of a blocked decision, or null when it isn't blocked.
	 *
	 * @param Decision $decision Decision.
	 * @return string|null
	 */
	public static function for_blocked( Decision $decision ) {

		if ( ! $decision->is_blocked() ) {
			return null;
		}

		$message = $decision->get_message();

		// A filter may return a blocked decision without a message.
		return '' === $message ? OrderingPolicy::message( $decision ) : $message;
	}

	/**
	 * Plain text as HTML for WooCommerce notices and Store API errors, which
	 * WooCommerce shows as HTML element content.
	 *
	 * Only `&`, `<` and `>` are escaped. `esc_html()` would also turn quotes
	 * into entities, and the block checkout announces errors to screen
	 * readers through `wp.a11y.speak()`, which doesn't decode them: the
	 * default "We're closed" would be read as "We&#039;re closed". Quotes
	 * are safe in element content. Every `&` is escaped, so text typed as
	 * `&amp;` shows as typed.
	 *
	 * @param string $message Plain text.
	 * @return string
	 */
	public static function html( $message ) {
		return htmlspecialchars( (string) $message, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8', true );
	}
}
