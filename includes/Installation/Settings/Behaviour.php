<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Installation\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Behaviour settings defaults: whether opening hours are enforced, what
 * happens to orders while closed, the closed message, the notice bar
 * (on, and where it shows), and the note in the customer's emails for an
 * order accepted while closed (OW-062).
 *
 * Enforcement is off after install, so activating HourGate changes
 * nothing for shoppers until the merchant turns it on (study 10, D4).
 */
class Behaviour {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'hourgate_behaviour_settings';

	/**
	 * Closed mode: reject checkout; browsing and add-to-cart stay on.
	 *
	 * @var string
	 */
	const MODE_BLOCK = 'block';

	/**
	 * Closed mode: accept the order and schedule it for the next opening.
	 *
	 * @var string
	 */
	const MODE_SCHEDULE = 'schedule';

	/**
	 * Closed mode: reject add-to-cart and checkout; browsing stays on.
	 *
	 * @var string
	 */
	const MODE_CATALOGUE = 'catalogue';

	/**
	 * Every closed mode.
	 *
	 * @var string[]
	 */
	const MODES = array( self::MODE_BLOCK, self::MODE_SCHEDULE, self::MODE_CATALOGUE );

	/**
	 * Notice bar scope: WooCommerce pages only (shop, product archives,
	 * product, cart, checkout, My Account).
	 *
	 * @var string
	 */
	const BAR_SCOPE_WOOCOMMERCE = 'woocommerce';

	/**
	 * Notice bar scope: every front-end page.
	 *
	 * @var string
	 */
	const BAR_SCOPE_ALL = 'all';

	/**
	 * Every notice bar scope.
	 *
	 * @var string[]
	 */
	const BAR_SCOPES = array( self::BAR_SCOPE_WOOCOMMERCE, self::BAR_SCOPE_ALL );

	/**
	 * Longest closed message, in characters.
	 *
	 * @var int
	 */
	const MESSAGE_MAX_LENGTH = 500;

	/**
	 * Install the defaults. `add_option()` never overwrites saved settings,
	 * even if first-time setup runs again.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function install() {

		add_option( self::OPTION, self::get_default_settings() );
	}

	/**
	 * Get default settings.
	 *
	 * An empty `closedMessage` means "the default message", which is
	 * translated when it's shown (`Core\Behaviour::closed_message()`), so it
	 * follows the site's language instead of the one active at install. An
	 * empty `scheduledOrderNoteText` works the same way.
	 *
	 * @version 1.0.0
	 * @return array
	 */
	public static function get_default_settings() {

		return array(
			'enforce'                => false,
			'closedMode'             => self::MODE_BLOCK,
			'closedMessage'          => '',
			// On by default: it shows only while ordering is closed or paused, so nothing changes until enforcement is on (OW-038).
			'noticeBar'              => true,
			'noticeBarScope'         => self::BAR_SCOPE_WOOCOMMERCE,
			// On by default: only orders accepted while closed (closed mode "schedule") get it (OW-062).
			'scheduledOrderNote'     => true,
			'scheduledOrderNoteText' => '',
		);
	}

	/**
	 * The default closed message, translated. Tokens: `{next_open}`, `{closures_label}`.
	 *
	 * @return string
	 */
	public static function get_default_closed_message() {

		/* translators: Keep {next_open} as is: it is replaced with the date and time the store next opens. */
		return __( "We're closed right now. We open {next_open}.", 'hourgate' );
	}

	/**
	 * The default note for an order accepted while closed, translated. Token: `{scheduled_for}`.
	 *
	 * @return string
	 */
	public static function get_default_scheduled_order_note() {

		/* translators: Keep {scheduled_for} as is: it is replaced with the date and time the order is scheduled for. */
		return __( "Your order was placed while we were closed. We'll start on it from {scheduled_for}.", 'hourgate' );
	}
}
