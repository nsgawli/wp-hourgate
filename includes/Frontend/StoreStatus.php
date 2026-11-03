<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Frontend;

use HourGate\Core\OrderingPolicy;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "Store status" markup (OW-040), shared by the block and the
 * `[hourgate_status]` shortcode.
 *
 * PHP prints the current text for visitors without JavaScript, in a
 * `role="status"` container whose `data-` attributes carry the templates, so
 * the status script (`status-block.js`) can replace the text with a fresh
 * state on a cached page. `text()` and `src/storefront-status/status-text.js`
 * follow the same rules.
 */
class StoreStatus {

	/**
	 * Longest template accepted, in characters.
	 *
	 * @var int
	 */
	const MAX_LENGTH = 200;

	/**
	 * The default text while open.
	 *
	 * @return string
	 */
	public static function default_open_text() {
		/* translators: Keep {closes_at} as is: it is replaced with the time the store next closes. */
		return __( 'Open now · closes {closes_at}', 'hourgate' );
	}

	/**
	 * A template from a block attribute or shortcode attribute: plain text, no
	 * markup, no line breaks, limited in length. Escaping still happens at output.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function clean( $value ) {

		if ( ! is_string( $value ) ) {
			return '';
		}

		return mb_substr( sanitize_text_field( $value ), 0, self::MAX_LENGTH );
	}

	/**
	 * The text for a status.
	 *
	 * @param array  $status Result of `OrderingPolicy::shopper_status()`.
	 * @param string $open   Open template ('' for the default).
	 * @param string $closed Closed template ('' for the store's closed message).
	 * @return string Plain text.
	 */
	public static function text( array $status, $open, $closed ) {

		if ( 'open' === $status['state'] ) {

			$open = '' === $open ? self::default_open_text() : $open;

			if ( false !== strpos( $open, '{closes_at}' ) && ! is_string( $status['closesAtLabel'] ) ) {
				return self::open_fallback();
			}

			return trim( str_replace( '{closes_at}', (string) $status['closesAtLabel'], $open ) );
		}

		if ( '' === $closed ) {
			return is_string( $status['message'] ) ? $status['message'] : '';
		}

		$next_open = is_string( $status['nextOpenLabel'] ) ? $status['nextOpenLabel'] : self::later();

		return trim( str_replace( '{next_open}', $next_open, $closed ) );
	}

	/**
	 * Plain text when the store is open but has no closing time to show.
	 *
	 * @return string
	 */
	private static function open_fallback() {
		return __( 'Open now', 'hourgate' );
	}

	/**
	 * The word for an unknown next opening.
	 *
	 * @return string
	 */
	private static function later() {
		return __( 'later', 'hourgate' );
	}

	/**
	 * The container, with the current text. Asks for the status script.
	 *
	 * @param mixed $open_text   Open template (block or shortcode attribute).
	 * @param mixed $closed_text Closed template.
	 * @return string HTML, escaped; '' when the status can't be built.
	 */
	public static function render( $open_text, $closed_text ) {

		$open   = self::clean( $open_text );
		$closed = self::clean( $closed_text );

		try {
			$status = OrderingPolicy::shopper_status();
		} catch ( \Throwable $e ) {
			// Never break a page over a status line: print nothing.
			return '';
		}

		Assets::need();
		Assets::add_style( '.hourgate-store-status[hidden]{display:none}' );

		$is_open = 'open' === $status['state'];

		return sprintf(
			'<div class="hourgate-store-status %1$s" role="status" data-state="%2$s" data-open="%3$s" data-closed="%4$s" data-open-fallback="%5$s" data-later="%6$s">%7$s</div>',
			$is_open ? 'is-open' : 'is-closed',
			esc_attr( $status['state'] ),
			esc_attr( '' === $open ? self::default_open_text() : $open ),
			esc_attr( $closed ),
			esc_attr( self::open_fallback() ),
			esc_attr( self::later() ),
			esc_html( self::text( $status, $open, $closed ) )
		);
	}
}
