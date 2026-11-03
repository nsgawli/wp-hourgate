<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core;

use HourGate\Installation\Settings\Behaviour as BehaviourSettings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Behaviour settings group, read side: merged with the defaults,
 * type-checked per key, and memoized for the request.
 *
 * Nothing runs at boot except hook registration. Saving the option resets
 * the memo in the same request.
 */
class Behaviour {

	/**
	 * The memoized settings.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Register the hooks that keep the memo fresh. Called from `Core\Autoloader`; no queries.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		add_action( 'add_option_' . BehaviourSettings::OPTION, array( __CLASS__, 'flush' ), 10, 0 );
		add_action( 'update_option_' . BehaviourSettings::OPTION, array( __CLASS__, 'flush' ), 10, 0 );
		add_action( 'delete_option_' . BehaviourSettings::OPTION, array( __CLASS__, 'flush' ), 10, 0 );
	}

	/**
	 * Forget the memoized settings.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$settings = null;
	}

	/**
	 * The settings: `{ enforce: bool, closedMode: string, closedMessage: string,
	 * noticeBar: bool, noticeBarScope: string, scheduledOrderNote: bool,
	 * scheduledOrderNoteText: string }`.
	 *
	 * Each key that is missing or has the wrong type or value reads as its
	 * default; a non-array option reads as all defaults. `closedMessage` and
	 * `scheduledOrderNoteText` are the stored text, `''` meaning the default.
	 *
	 * @return array
	 */
	public static function get() {

		if ( null !== self::$settings ) {
			return self::$settings;
		}

		$defaults = BehaviourSettings::get_default_settings();
		$stored   = get_option( BehaviourSettings::OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = $defaults;

		if ( isset( $stored['enforce'] ) && is_bool( $stored['enforce'] ) ) {
			$settings['enforce'] = $stored['enforce'];
		}

		if ( isset( $stored['closedMode'] ) && in_array( $stored['closedMode'], BehaviourSettings::MODES, true ) ) {
			$settings['closedMode'] = $stored['closedMode'];
		}

		if ( isset( $stored['closedMessage'] ) && self::is_valid_message( $stored['closedMessage'] ) ) {
			$settings['closedMessage'] = $stored['closedMessage'];
		}

		if ( isset( $stored['noticeBar'] ) && is_bool( $stored['noticeBar'] ) ) {
			$settings['noticeBar'] = $stored['noticeBar'];
		}

		if ( isset( $stored['noticeBarScope'] ) && in_array( $stored['noticeBarScope'], BehaviourSettings::BAR_SCOPES, true ) ) {
			$settings['noticeBarScope'] = $stored['noticeBarScope'];
		}

		if ( isset( $stored['scheduledOrderNote'] ) && is_bool( $stored['scheduledOrderNote'] ) ) {
			$settings['scheduledOrderNote'] = $stored['scheduledOrderNote'];
		}

		if ( isset( $stored['scheduledOrderNoteText'] ) && self::is_valid_message( $stored['scheduledOrderNoteText'] ) ) {
			$settings['scheduledOrderNoteText'] = $stored['scheduledOrderNoteText'];
		}

		self::$settings = $settings;

		return self::$settings;
	}

	/**
	 * Whether the storefront notice bar is on (OW-038).
	 *
	 * @return bool
	 */
	public static function notice_bar() {
		return self::get()['noticeBar'];
	}

	/**
	 * Where the notice bar shows: `woocommerce` or `all`.
	 *
	 * @return string
	 */
	public static function notice_bar_scope() {
		return self::get()['noticeBarScope'];
	}

	/**
	 * Whether "Enforce opening hours" is on.
	 *
	 * @return bool
	 */
	public static function is_enforced() {
		return self::get()['enforce'];
	}

	/**
	 * The closed mode: `block`, `schedule` or `catalogue`.
	 *
	 * @return string
	 */
	public static function closed_mode() {
		return self::get()['closedMode'];
	}

	/**
	 * The closed message template as plain text, tokens not yet replaced:
	 * the stored text, or the translated default when none is stored.
	 *
	 * @return string
	 */
	public static function closed_message() {

		$message = self::get()['closedMessage'];

		return '' === $message ? BehaviourSettings::get_default_closed_message() : $message;
	}

	/**
	 * Whether orders accepted while closed get the note in the customer's emails (OW-062).
	 *
	 * @return bool
	 */
	public static function scheduled_order_note() {
		return self::get()['scheduledOrderNote'];
	}

	/**
	 * The scheduled-order note template as plain text, `{scheduled_for}` not
	 * yet replaced: the stored text, or the translated default when none is stored.
	 *
	 * @return string
	 */
	public static function scheduled_order_note_text() {

		$text = self::get()['scheduledOrderNoteText'];

		return '' === $text ? BehaviourSettings::get_default_scheduled_order_note() : $text;
	}

	/**
	 * Sanitize a closed message for storage: plain text, tags stripped,
	 * line breaks kept, trimmed.
	 *
	 * After a lone `<` (as in "orders < 100") `sanitize_textarea_field()`
	 * HTML-escapes the rest of the text (`&amp;`, `&quot;`), so the result is
	 * decoded back to plain text, because the message is escaped at output. Decoding
	 * could turn typed entities (`&lt;b&gt;`) into a tag, so sanitizing and
	 * decoding repeat until the text stops changing: the result never
	 * contains a tag. Text still changing after a few rounds (deeply nested
	 * entities) is kept in its sanitized, escaped form.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_message( $value ) {

		if ( ! is_string( $value ) ) {
			return '';
		}

		for ( $round = 0; $round < 5; $round++ ) {
			$clean = sanitize_textarea_field( $value );
			$next  = wp_specialchars_decode( $clean, ENT_QUOTES );

			if ( $next === $value ) {
				return $next;
			}

			$value = $next;
		}

		return $clean;
	}

	/**
	 * Whether a stored message has the right shape: a string within the limit.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_valid_message( $value ) {
		return is_string( $value ) && mb_strlen( $value ) <= BehaviourSettings::MESSAGE_MAX_LENGTH;
	}
}
