<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Frontend;

use HourGate\Core\OpeningHours;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "Opening hours" markup (OW-041), shared by the block and the
 * `[hourgate_hours]` shortcode.
 *
 * One `<table>` row per weekday from `Core\OpeningHours`, today marked with
 * `aria-current="date"` and `is-today`. The script (`hours-block.js`) rebuilds
 * the rows from `/status?include=hours` on a cached page, so a changed
 * schedule and a new day show without a purge. Everything is escaped here;
 * the script uses `textContent`.
 */
class HoursTable {

	/**
	 * Longest title accepted, in characters.
	 *
	 * @var int
	 */
	const MAX_LENGTH = 100;

	/**
	 * A title from a block or shortcode attribute: plain text, one line.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function clean_title( $value ) {

		if ( ! is_string( $value ) ) {
			return '';
		}

		return mb_substr( sanitize_text_field( $value ), 0, self::MAX_LENGTH );
	}

	/**
	 * Whether an attribute value means "on" (`true`, `"true"`, `"1"`, `"yes"`, `"on"`).
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function flag( $value ) {

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return true === $value || 1 === $value;
	}

	/**
	 * One closures-list line.
	 *
	 * @param array $entry An `OpeningHours::upcoming()` entry.
	 * @return string Plain text.
	 */
	public static function closure_text( array $entry ) {

		$label = isset( $entry['label'] ) ? (string) $entry['label'] : '';

		if ( 'closure' === $entry['type'] ) {
			$what = '' === $label ? self::closed_word() : $label;
		} else {
			$hours = isset( $entry['hours'] ) ? (string) $entry['hours'] : '';
			$what  = '' === $label ? $hours : $label . self::hours_sep() . $hours;
		}

		return $entry['dates'] . self::date_sep() . $what;
	}

	/**
	 * Between a date and what happens on it.
	 *
	 * @return string
	 */
	private static function date_sep() {
		/* translators: separator in a closures list line, e.g. "March 3: Diwali". */
		return __( ': ', 'hourgate' );
	}

	/**
	 * Between a special day's name and its hours.
	 *
	 * @return string
	 */
	private static function hours_sep() {
		/* translators: separator between a special day's name and its hours, e.g. "Christmas Eve · 10:00 am – 2:00 pm". */
		return __( ' · ', 'hourgate' );
	}

	/**
	 * The word for a closure without a name.
	 *
	 * @return string
	 */
	private static function closed_word() {
		return __( 'Closed', 'hourgate' );
	}

	/**
	 * The table, with today's rows. Asks for the status script, with hours.
	 *
	 * @param mixed $title        Optional heading, the table's caption.
	 * @param mixed $show_closures Whether to list closures and special hours.
	 * @return string HTML, escaped; '' when the hours can't be built.
	 */
	public static function render( $title, $show_closures ) {

		$title    = self::clean_title( $title );
		$closures = self::flag( $show_closures );

		try {
			$hours = OpeningHours::build();
		} catch ( \Throwable $e ) {
			// Never break a page over a table: print nothing.
			return '';
		}

		Assets::need( 'hours' );
		Assets::add_style( '.hourgate-hours table{border-collapse:collapse}.hourgate-hours th,.hourgate-hours td{text-align:start;padding:.25em 1em .25em 0}.hourgate-hours .is-today{font-weight:600}.hourgate-hours [hidden]{display:none}' );

		$rows = '';

		foreach ( $hours['days'] as $day ) {
			$rows .= sprintf(
				'<tr class="%1$s"%2$s data-day="%3$s"><th scope="row">%4$s</th><td>%5$s</td></tr>',
				$day['today'] ? 'is-today' : '',
				$day['today'] ? ' aria-current="date"' : '',
				esc_attr( $day['day'] ),
				esc_html( $day['name'] ),
				esc_html( $day['label'] )
			);
		}

		$list = '';

		if ( $closures ) {
			foreach ( $hours['upcoming'] as $entry ) {
				$list .= '<li>' . esc_html( self::closure_text( $entry ) ) . '</li>';
			}
		}

		return sprintf(
			'<div class="hourgate-hours" data-closures="%1$s" data-date-sep="%2$s" data-hours-sep="%3$s" data-closed="%4$s"><table><caption%5$s>%6$s</caption><tbody>%7$s</tbody></table>%8$s</div>',
			$closures ? '1' : '0',
			esc_attr( self::date_sep() ),
			esc_attr( self::hours_sep() ),
			esc_attr( self::closed_word() ),
			'' === $title ? ' class="screen-reader-text"' : '',
			esc_html( '' === $title ? __( 'Opening hours', 'hourgate' ) : $title ),
			$rows,
			$closures
				? sprintf(
					'<div class="hourgate-hours__closures"%1$s><h4>%2$s</h4><ul>%3$s</ul></div>',
					'' === $list ? ' hidden' : '',
					esc_html__( 'Upcoming closures and special hours', 'hourgate' ),
					$list
				)
				: ''
		);
	}
}
