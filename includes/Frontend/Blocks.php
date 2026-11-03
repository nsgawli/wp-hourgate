<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Frontend;

use HourGate\Core\Behaviour;
use HourGate\Core\OpeningHours;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HourGate's blocks (OW-040). Dynamic: nothing is saved in the post.
 *
 * The block type is registered from an array, not from `block.json`, so a
 * request pays no file read at boot; `src/blocks/<name>/block.json` is the
 * source for the editor script (it imports it) and must match the
 * attributes below. The editor script is registered and enqueued only in the
 * block editor.
 */
class Blocks {

	/**
	 * Editor script handle.
	 *
	 * @var string
	 */
	const EDITOR_HANDLE = 'hourgate-store-status-editor';

	/**
	 * Editor script handle of the "Opening hours" block.
	 *
	 * @var string
	 */
	const HOURS_EDITOR_HANDLE = 'hourgate-opening-hours-editor';

	/**
	 * Register the hooks. Called from the front-end and admin autoloaders; no I/O.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ) );
	}

	/**
	 * Register the block types.
	 *
	 * @return void
	 */
	public static function register_blocks() {

		register_block_type(
			'hourgate/store-status',
			array(
				'api_version'     => 3,
				// The same strings and contexts as block.json, so translators see each once.
				'title'           => _x( 'Store status', 'block title', 'hourgate' ),
				'description'     => _x( 'Shows whether the store is open now, and when it closes or opens again.', 'block description', 'hourgate' ),
				'category'        => 'woocommerce',
				'keywords'        => array(
					_x( 'open', 'block keyword', 'hourgate' ),
					_x( 'closed', 'block keyword', 'hourgate' ),
					_x( 'hours', 'block keyword', 'hourgate' ),
					_x( 'hourgate', 'block keyword', 'hourgate' ),
				),
				'attributes'      => array(
					'openText'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'closedText' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'        => array( 'html' => false ),
				'render_callback' => array( __CLASS__, 'render_store_status' ),
			)
		);

		self::register_hours_block();
	}

	/**
	 * Register the "Opening hours" block (kept apart so the array stays readable).
	 *
	 * @return void
	 */
	private static function register_hours_block() {

		register_block_type(
			'hourgate/opening-hours',
			array(
				'api_version'     => 3,
				'title'           => _x( 'Opening hours', 'block title', 'hourgate' ),
				'description'     => _x( 'Shows the store’s weekly opening hours, with today highlighted.', 'block description', 'hourgate' ),
				'category'        => 'woocommerce',
				'keywords'        => array(
					_x( 'hours', 'block keyword', 'hourgate' ),
					_x( 'open', 'block keyword', 'hourgate' ),
					_x( 'schedule', 'block keyword', 'hourgate' ),
					_x( 'hourgate', 'block keyword', 'hourgate' ),
				),
				'attributes'      => array(
					'title'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'showClosures' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'supports'        => array( 'html' => false ),
				'render_callback' => array( __CLASS__, 'render_opening_hours' ),
			)
		);
	}

	/**
	 * Render the "Opening hours" block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_opening_hours( $attributes ) {

		$attributes = is_array( $attributes ) ? $attributes : array();

		return HoursTable::render(
			isset( $attributes['title'] ) ? $attributes['title'] : '',
			isset( $attributes['showClosures'] ) ? $attributes['showClosures'] : false
		);
	}

	/**
	 * Render the "Store status" block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_store_status( $attributes ) {

		$attributes = is_array( $attributes ) ? $attributes : array();

		return StoreStatus::render(
			isset( $attributes['openText'] ) ? $attributes['openText'] : '',
			isset( $attributes['closedText'] ) ? $attributes['closedText'] : ''
		);
	}

	/**
	 * Load the editor script, in the block editor only.
	 *
	 * @return void
	 */
	public static function enqueue_editor() {

		$file = HOURGATE_ABSPATH . 'build/blocks/store-status/index.asset.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		$asset = require $file;

		wp_enqueue_script(
			self::EDITOR_HANDLE,
			HOURGATE_PLUGIN_URL . 'build/blocks/store-status/index.js',
			isset( $asset['dependencies'] ) ? (array) $asset['dependencies'] : array(),
			isset( $asset['version'] ) ? $asset['version'] : HOURGATE_VERSION,
			array( 'in_footer' => false )
		);

		wp_set_script_translations( self::EDITOR_HANDLE, 'hourgate' );

		wp_add_inline_script(
			self::EDITOR_HANDLE,
			'window.hourGateBlocks = ' . wp_json_encode(
				array(
					'closedMessage'   => Behaviour::closed_message(),
					'defaultOpenText' => StoreStatus::default_open_text(),
				),
				JSON_HEX_TAG | JSON_HEX_AMP
			) . ';',
			'before'
		);

		self::enqueue_hours_editor();
	}

	/**
	 * The "Opening hours" editor script, with the saved hours for its preview.
	 *
	 * @return void
	 */
	private static function enqueue_hours_editor() {

		$file = HOURGATE_ABSPATH . 'build/blocks/opening-hours/index.asset.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		$asset = require $file;

		wp_enqueue_script(
			self::HOURS_EDITOR_HANDLE,
			HOURGATE_PLUGIN_URL . 'build/blocks/opening-hours/index.js',
			isset( $asset['dependencies'] ) ? (array) $asset['dependencies'] : array(),
			isset( $asset['version'] ) ? $asset['version'] : HOURGATE_VERSION,
			array( 'in_footer' => false )
		);

		wp_set_script_translations( self::HOURS_EDITOR_HANDLE, 'hourgate' );

		try {
			$hours = OpeningHours::build();
		} catch ( \Throwable $e ) {
			return;
		}

		$upcoming = array();

		foreach ( $hours['upcoming'] as $entry ) {
			$upcoming[] = array( 'text' => HoursTable::closure_text( $entry ) );
		}

		wp_add_inline_script(
			self::HOURS_EDITOR_HANDLE,
			'window.hourGateBlocks = Object.assign( window.hourGateBlocks || {}, ' . wp_json_encode(
				array(
					'hours'    => $hours['days'],
					'upcoming' => $upcoming,
				),
				JSON_HEX_TAG | JSON_HEX_AMP
			) . ' );',
			'before'
		);
	}
}
