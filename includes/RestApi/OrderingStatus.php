<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\RestApi;

use HourGate\Core\Behaviour;
use HourGate\Core\OrderingPolicy;
use HourGate\Core\Ordering\PauseState;
use HourGate\Core\Ordering\Situation;
use HourGate\Core\Pause as CorePause;
use HourGate\Core\StoreSchedule;
use HourGate\Installation\Settings\Behaviour as BehaviourSettings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `GET /ordering/status`: what the Ordering tab shows at its top (OW-033).
 *
 * It reads the same decision checkout gets (`OrderingPolicy::check()`), so the
 * owner sees what shoppers get. Times come preformatted in the site's format,
 * zone and locale, so the browser never converts them. An admin route (the
 * manage capability); the response is never cached.
 */
class OrderingStatus extends BaseController {

	/**
	 * Route.
	 *
	 * @var string
	 */
	const ROUTE = '/ordering/status';

	/**
	 * Register the routes on `rest_api_init`.
	 *
	 * @return void
	 */
	public static function init() {

		$instance = new self();

		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * The status:
	 * - `enforced`, `mode`: the saved Behaviour settings;
	 * - `reason`, `outcome`: the checkout decision now (`Decision` reasons and outcomes);
	 * - `nextOpen` / `nextClose` (UTC or null) with `…Label` (short: "6:00 pm",
	 *   "Tue 9:00 am"); `nextClose` only while open;
	 * - `hoursAlwaysOpen`: the saved hours are open now and stay open past the
	 *   look-ahead (nothing to enforce);
	 * - `pause`: as `GET /pause`, plus `untilLabel`;
	 * - `nextOpening`: the opening after the current window, as "pause until
	 *   the next opening" would use (null when there is none);
	 * - `preview`: token values for the message preview (`next_open`, `closures_label`),
	 *   and `scheduled_for` for the scheduled-order note's (OW-062: the time an
	 *   order placed now would be scheduled for, or the next one);
	 * - `defaultMessage`: the translated default closed message;
	 * - `defaultScheduledNote`: the translated default scheduled-order note.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status() {

		$now      = time();
		$decision = OrderingPolicy::check( Situation::CONTEXT_CHECKOUT );
		$engine   = StoreSchedule::engine();
		$open     = $engine->is_open( $now );
		$close    = $open ? $engine->next_close( $now ) : null;
		$opening  = PauseState::next_opening( $engine, $now );
		$next     = $open ? null : $engine->next_open( $now );
		$pause    = Pause::describe( CorePause::get() );

		$pause['untilLabel'] = null === $pause['until'] ? null : OrderingPolicy::format_when( (int) $pause['until'] );

		// The message shows only while closed; while open, preview the next time it would.
		$preview = $decision->is_closed()
			? OrderingPolicy::tokens( $decision->get_next_open(), $decision->get_closure_label() )
			: OrderingPolicy::tokens( $opening, '' );

		// An order is scheduled for the next opening, so the note's preview uses the same time.
		$preview['scheduled_for'] = $preview['next_open'];

		$response = $this->success_response(
			array(
				'enforced'             => Behaviour::is_enforced(),
				'mode'                 => Behaviour::closed_mode(),
				'reason'               => $decision->get_reason(),
				'outcome'              => $decision->get_outcome(),
				'fallback'             => StoreSchedule::is_fallback(),
				'open'                 => $open,
				'hoursAlwaysOpen'      => $open && null === $close,
				'nextOpen'             => $next,
				'nextOpenLabel'        => null === $next ? null : OrderingPolicy::format_when( $next ),
				'nextClose'            => $close,
				'nextCloseLabel'       => null === $close ? null : OrderingPolicy::format_when( $close ),
				'nextOpening'          => $opening,
				'nextOpeningLabel'     => null === $opening ? null : OrderingPolicy::format_when( $opening ),
				'pause'                => $pause,
				'preview'              => $preview,
				'defaultMessage'       => BehaviourSettings::get_default_closed_message(),
				'defaultScheduledNote' => BehaviourSettings::get_default_scheduled_order_note(),
			)
		);

		$response->header( 'Cache-Control', 'no-store, max-age=0' );

		return $response;
	}
}
