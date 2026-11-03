<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storefront shortcodes. Registering one is a single `add_shortcode()` call;
 * nothing runs until a page uses it.
 */
class Shortcodes {

	/**
	 * Register the shortcodes. Called from `Frontend\Autoloader`; no I/O.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_shortcode( 'hourgate_status', array( __CLASS__, 'status' ) );
		add_shortcode( 'hourgate_hours', array( __CLASS__, 'hours' ) );
	}

	/**
	 * `[hourgate_status open_text="" closed_text=""]`: the same output
	 * as the "Store status" block. Unknown attributes are ignored; both
	 * values are cleaned to plain text and escaped on output.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function status( $atts ) {

		$atts = shortcode_atts(
			array(
				'open_text'   => '',
				'closed_text' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'hourgate_status'
		);

		return StoreStatus::render( $atts['open_text'], $atts['closed_text'] );
	}

	/**
	 * `[hourgate_hours title="" show_closures="no"]`: the same output
	 * as the "Opening hours" block. Unknown attributes are ignored.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function hours( $atts ) {

		$atts = shortcode_atts(
			array(
				'title'         => '',
				'show_closures' => 'no',
			),
			is_array( $atts ) ? $atts : array(),
			'hourgate_hours'
		);

		return HoursTable::render( $atts['title'], $atts['show_closures'] );
	}
}
